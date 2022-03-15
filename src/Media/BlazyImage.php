<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\media\MediaInterface;
use Drupal\blazy\Blazy;

/**
 * Provides image-related methods.
 */
class BlazyImage {

  /**
   * The image style ID.
   *
   * @var array
   */
  private static $styleId;

  /**
   * Provides original unstyled image dimensions based on the given image item.
   */
  public static function dimensions(array &$settings, $item = NULL, $initial = FALSE): void {
    $width = $initial ? '_width' : 'width';
    $height = $initial ? '_height' : 'height';
    $uri = $initial ? '_uri' : 'uri';

    if (empty($settings[$height]) && $item) {
      $settings[$width] = $item->width ?? NULL;
      $settings[$height] = $item->height ?? NULL;
    }

    // Only applies when Image style is empty, no file API, no $item,
    // with unmanaged VEF/ WYSIWG/ filter image without image_style.
    if (empty($settings['image_style']) && empty($settings[$height]) && !empty($settings[$uri])) {
      $abs = empty($settings['uri_root']) ? $settings[$uri] : $settings['uri_root'];
      // Must be valid URI, or web-accessible url, not: /modules|themes/...
      if (!BlazyFile::isValidUri($abs) && mb_substr($abs, 0, 1) == '/') {
        if ($request = Blazy::requestStack()) {
          $abs = $request->getCurrentRequest()->getSchemeAndHttpHost() . $abs;
        }
      }

      // Prevents 404 warning when video thumbnail missing for a reason.
      if ($data = @getimagesize($abs)) {
        [$settings[$width], $settings[$height]] = $data;
      }
    }

    // Sometimes they are string, cast them integer to reduce JS logic.
    $settings[$width] = empty($settings[$width]) ? NULL : (int) $settings[$width];
    $settings[$height] = empty($settings[$height]) ? NULL : (int) $settings[$height];
  }

  /**
   * Returns fake image item based on the given $settings.
   */
  public static function fake(array $settings = []) {
    $item = new \stdClass();
    foreach (['uri', 'width', 'height', 'target_id', 'alt', 'title'] as $key) {
      if (isset($settings[$key])) {
        $item->{$key} = $settings[$key];
      }
    }
    return $item;
  }

  /**
   * Returns the image item out of File entity, ER, etc., or just $settings.
   *
   * @param object $object
   *   The optional Media, File entity, or ER, etc. to get image item from.
   * @param array $settings
   *   The optional settings.
   *
   * @return array
   *   The array of image item and settings if a file image, else empty.
   *
   * @todo this is likely to be removed for anything Media, still kept for
   * BlazyFilter and few legacy file entity integrations such as Views file.
   * @todo compare and merge with BlazyMedia::imageItem(), and the two below.
   * @todo simplify this, like everything else. An obvious confusion here.
   */
  public static function fromAny($object = NULL, array $settings = []): array {
    // If Media entity, we must have a File entity, and likely ImageItem.
    if ($object instanceof MediaInterface) {
      $entity = $object;
    }
    else {
      // Extracts File entity from any object or settings, if applicable.
      $entity = BlazyFile::item($object, $settings);

      // Called by BlazyFilter file upload and legacy BlazyViewsFieldFile.
      if ($entity instanceof FileInterface
        && $factory = Blazy::service('image.factory')) {
        if ($image = $factory->get($entity->getFileUri())) {
          return self::fakeWithdata($entity, $image);
        }
      }
    }

    // Called by formatters.
    $options = [
      'entity' => $entity,
      'source' => $entity == $object ? NULL : $object,
      'settings' => $settings,
    ];

    // We have a Media entity.
    if ($item = self::item(NULL, $options)) {
      $blazies = $settings['blazies'];
      $settings['uri'] = $uri = BlazyFile::uri($item);
      $blazies->set('uri', $uri);
      return ['item' => $item, 'settings' => $settings];
    }

    return [];
  }

  /**
   * Returns the image item from any sources, if available.
   *
   * PHP 7.2 accepts object. D8 >= PHP 7.3. Not good for D7 backport.
   */
  public static function item($item = NULL, array $options = [], $name = NULL): ?object {
    if ($item instanceof ImageItem) {
      return $item;
    }

    $settings = $options['settings'] ?? [];
    $blazies = $settings['blazies'] ?? NULL;
    $poster = $settings['image'] ?? FALSE;
    $name = $name ?: $poster;

    // Title is NULL from thumbnail, likely core bug, so use source.
    if ($blazies && !$name && $source = $blazies->get('media.source')) {
      $name = $source == 'image' ? $blazies->get('media.source_field') : 'thumbnail';
    }

    $func = function ($key, $property) use ($options) {
      $object = ($options[$key] ?? NULL);
      if ($object instanceof ContentEntityInterface && $object->hasField($property)) {
        $item = $object->get($property)->first();
        $valid = $item instanceof ImageItem;

        // Specific for Remote video, it has meaningful label from OEmbed, OOTB.
        if ($valid && trim($item->title ?? '') == '') {
          $item->title = $object->label();
        }
        return $valid ? $item : NULL;
      }
      return NULL;
    };

    // \Drupal\paragraphs\Entity\Paragraph, Media, Node, etc.
    $item = $func('entity', $name) ?: $func('source', $name);
    $item = $name ? $item : NULL;
    if (!$item) {
      $item = $func('entity', 'thumbnail') ?: $func('source', 'thumbnail');
    }

    return $item;
  }

  /**
   * Checks if we have image item.
   *
   * Both ImageItem and fake stdClass are valid, no problem.
   */
  public static function isValidItem($item): bool {
    $item = is_array($item) ? ($item['item'] ?? NULL) : $item;
    return is_object($item) && (isset($item->uri) || isset($item->target_id));
  }

  /**
   * Checks for [Responsive] image styles.
   */
  public static function styles(array &$settings, $multiple = FALSE): void {
    $blazy = Blazy::service('blazy.manager');
    $blazies = $settings['blazies'];
    $exist = $blazies->is('resimage');

    // Multiple is a flag for various styles: Blazy Filter, GridStack, etc.
    // While fields can only have one image style per field.
    if (!$blazies->get('resimage.style') || $multiple) {
      $style = $settings['responsive_image_style'] ?? NULL;
      $applicable = $exist && $style;
      $resimage = $settings['resimage'] ?? NULL;

      if (empty($resimage) && $applicable) {
        $resimage = $blazy->entityLoad($style, 'responsive_image_style');
      }

      $blazies->set('resimage.style', $exist ? $resimage : NULL);
    }

    // Might be set via BlazyFilter, but not enough data passed.
    if (!$blazies->get('resimage.id') || $multiple) {
      if ($resimage = $blazies->get('resimage.style')) {
        BlazyResponsiveImage::define($blazies, $resimage);
      }
    }

    // Specific for lightbox, it can be (Responsive) image.
    foreach (['box', 'box_media', 'image', 'thumbnail'] as $key) {
      if (!$blazies->get($key . '.style') || $multiple) {
        $image_style = NULL;
        if ($style = ($settings[$key . '_style'] ?? '')) {
          if ($key == 'box' && $exist) {
            $resimage = $blazy->entityLoad($style, 'responsive_image_style');
            $blazies->set($key . '.resimage.style', $resimage)
              ->set($key . '.resimage.id', $resimage ? $resimage->id() : NULL);
          }
          $image_style = $blazy->entityLoad($style, 'image_style');
        }

        $blazies->set($key . '.style', $image_style);
      }
    }
  }

  /**
   * Returns the thumbnail image using theme_image(), or theme_image_style().
   */
  public static function thumbnail(array $settings = [], $item = NULL): array {
    // @tod remove check after another check.
    $blazies = $settings['blazies'] ?? NULL;
    $default = $settings['uri'] ?? NULL;
    $uri = $blazies ? $blazies->get('uri', $default) : $default;

    if ($uri) {
      $external = UrlHelper::isExternal($uri);
      $style = $settings['thumbnail_style'] ?? NULL;

      return [
        '#theme'      => $external ? 'image' : 'image_style',
        '#style_name' => $style ?: 'thumbnail',
        '#uri'        => $uri,
        '#item'       => $item,
        '#alt'        => $item instanceof ImageItem ? $item->getValue()['alt'] : '',
      ];
    }
    return [];
  }

  /**
   * A wrapper for ImageStyle::transformDimensions().
   *
   * @param object $style
   *   The given image style.
   * @param array $data
   *   The data settings: _width, _height, _uri, width, height, and uri.
   * @param bool $initial
   *   Whether particularly transforms once for all, or individually.
   */
  public static function transformDimensions($style, array $data, $initial = FALSE): array {
    $uri = $initial ? '_uri' : 'uri';
    $key = hash('md2', ($style->id() . $data[$uri] . $initial));

    if (!isset(static::$styleId[$key])) {
      $_width  = $initial ? '_width' : 'width';
      $_height = $initial ? '_height' : 'height';
      $width   = $data[$_width] ?? NULL;
      $height  = $data[$_height] ?? NULL;
      $dim     = ['width' => $width, 'height' => $height];

      // Funnily $uri is ignored at all core image effects.
      $style->transformDimensions($dim, $data[$uri]);

      // Sometimes they are string, cast them integer to reduce JS logic.
      if ($dim['width'] != NULL) {
        $dim['width'] = (int) $dim['width'];
      }
      if ($dim['height'] != NULL) {
        $dim['height'] = (int) $dim['height'];
      }

      static::$styleId[$key] = [
        'width' => $dim['width'],
        'height' => $dim['height'],
      ];
    }
    return static::$styleId[$key];
  }

  /**
   * Provides image url based on the given settings.
   */
  public static function url(array &$settings, $style = NULL): string {
    $blazies = $settings['blazies'];
    // Provides image_url, not URI, expected by lazyload.
    $uri = $settings['uri'] ?? $settings['_uri'] ?? NULL;
    $url = '';
    if ($uri) {
      ['url' => $url, 'style' => $style] = self::urlAndStyle($uri, $settings, $style);

      // @tdo remove any settngs like this after migraton and sub-modules.
      $settings['image_url'] = $url;
      $blazies->set('image.url', $url);

      // @todo move it out here.
      if ($style) {
        $blazies->set('cache.tags', $style->getCacheTags(), TRUE);

        // Only re-calculate dimensions if not cropped, nor already set.
        if (!$blazies->is('dimensions')
          && empty($settings['responsive_image_style'])) {
          $settings = array_merge($settings, self::transformDimensions($style, $settings));
        }
      }
    }

    return $url;
  }

  /**
   * Returns image url, not URI, expected by lazyload, and style.
   *
   * @todo simplify this.
   */
  public static function urlAndStyle($uri, array $settings, $style = NULL): array {
    $blazies = $settings['blazies'];
    $valid = BlazyFile::isValidUri($uri);
    $styled = $valid && !$blazies->is('unstyled');
    // Image style modifier can be multi-style images such as UGC or GridStack.
    $_style = $settings['image_style'] ?? '';
    $style = $style ?: $blazies->get('image.style');
    // @todo remove after another check, might be needed by non-API custom work.
    $style = $style ?: (empty($_style) ? NULL : ImageStyle::load($_style));
    $url = $settings['image_url'] ?? '';

    $sanitize = !empty($settings['_check_protocol']);
    $options = ['url' => $url, 'sanitize' => $sanitize];
    $url = BlazyFile::transformRelative($uri, ($styled ? $style : NULL), $options);
    $no_dims = empty($settings['height']) || empty($settings['width']);

    // Currently doesn't affect option.ratio, a failsafe for BG, else collapsed.
    // @todo decide if to provide NULL or 0 instead.
    $ratio = $no_dims ? 100 : round((($settings['height'] / $settings['width']) * 100), 2);

    return [
      'url' => $url,
      'style' => $style,
      'ratio' => $ratio,
    ];
  }

  /**
   * Builds URLs, cache tags, and dimensions for an individual image.
   *
   * Respects a few scenarios:
   * 1. Blazy Filter or unmanaged file with/ without valid URI.
   * 2. Hand-coded image_url with/ without valid URI.
   * 3. Respects first_uri without image_url such as colorbox/zoom-like.
   * 4. File API via field formatters or Views fields/ styles with valid URI.
   * If we have a valid URI, provides the correct image URL.
   * Otherwise leave it as is, likely hotlinking to external/ sister sites.
   * Hence URI validity is not crucial in regards to anything but #4.
   * The image will fail silently at any rate given non-expected URI.
   *
   * @param array $settings
   *   The given settings being modified.
   * @param object $item
   *   The image item.
   */
  public static function urlAndDimensions(array &$settings, $item = NULL): void {

    // BlazyFilter, or image style with crop, may already set these.
    self::dimensions($settings, $item);

    // Provides image url based on the given settings.
    self::url($settings);
  }

  /**
   * Extracts image from non-media entities for the main background/ stage.
   *
   * Main image can be separate image item from video thumbnail for highres.
   * Fallback to default thumbnail if any, which has no file API. This used to
   * be for non-media File Entity Reference at 1.x, things changed since then.
   * Some core methods during Blazy 1.x are now gone at 2.x.
   * Re-purposed for Paragraphs, Node, etc. which embeds Media or File.
   *
   * @param array $data
   *   The element array might contain item and settings.
   * @param object $entity
   *   The file entity or entityreference which might have image item.
   * @param string $name
   *   The field name to extract image item.
   *
   * @see \Drupal\blazy\Dejavu\BlazyEntityMediaBase::buildElement
   *
   * Called by SplideVanillaWithNavTrait till Splide removes it for ::build().
   * This used to be for File entity (non-media).
   * Extracts image item from non-media, such as Paragraphs, Node, etc.
   * @todo re-check, some File core methods are gone at Blazy 2.x.
   * @todo remove when ::fromAny() is done right, and only after sub-modules.
   */
  public static function fromField(array &$data, $entity, $name): void {
    $settings = &$data['settings'];

    // The actual video thumbnail has already been downloaded earlier.
    // This fetches the highres image if provided and available.
    // With a mix of image and video, image is not always there.
    /** @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $field */
    if (isset($entity->{$name}) && $field = $entity->get($name)) {
      $values = $field->getValue();
      $valid = $values[0]['target_id'] ?? FALSE;

      // Do not proceed if it is a Media entity video. This means File here.
      if ($valid && $exist = method_exists($field, 'referencedEntities')) {
        // The reference can be File or Media.
        // If image, even if multi-value, we can only have one stage per slide.
        /** @var \Drupal\file\Entity\File $reference */
        /** @var Drupal\media\MediaInterface $reference */
        $reference = $field->referencedEntities()[0] ?? NULL;
        $ok = FALSE;

        if ($reference instanceof MediaInterface) {
          BlazyMedia::prepare($data, $reference);
          $ok = !empty($data['item']);
        }

        // Pass it directly if a File.
        $object = $reference instanceof FileInterface ? $reference : $field;

        // Called by BlazyFilter and legacy File entity like Views file.
        // Also vanilla Splide for the main stage.
        if (!$ok && $result = self::fromAny($object, $settings)) {
          $data = NestedArray::mergeDeep($data, $result);
        }
      }
    }
  }

  /**
   * Returns the real image item out of Media entity, if applicable.
   *
   * @param object $media
   *   The expected file entity, or ER, to get image item from.
   * @param array $settings
   *   The given settings.
   *
   * @return array
   *   The array of image item and settings if a file image, else empty.
   *
   * @todo remove when ::fromAny() is done right.
   */
  public static function fromMedia($media, array &$settings = []): array {
    $blazies = $settings['blazies'];
    $item = NULL;
    $hires = FALSE;

    // Prioritize custom high-res or poster image such as (remote|file) video.
    if ($image = ($settings['image'] ?? FALSE)) {
      $item = $media->hasField($image) ? $media->get($image)->first() : NULL;
      $hires = !empty($item);
    }

    // If Media has a defined thumbnail, add it to data item, not all has this.
    if (!$item && $media->hasField('thumbnail')) {
      /** @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      // Title is NULL from thumbnail, likely core bug, so use source.
      if ($source = $blazies->get('media.source')) {
        $image = $source == 'image' ? $blazies->get('media.source_field') : 'thumbnail';
        $item = $media->get($image)->first();
      }
    }

    // Checks if Image item is available.
    if ($item) {
      $settings['uri'] = BlazyFile::uri($item);

      if (trim($item->title ?? '') == '') {
        $item->title = $media->label();
      }
    }

    $blazies->set('is.hires', $hires);

    // Pass through image item including poster image overrides.
    return $item ? ['item' => $item, 'settings' => $settings] : [];
  }

  /**
   * Prepares CSS background image.
   *
   * @todo remove and merge it with ::urlAndStyle.
   */
  public static function background(array $settings, $style = NULL) {
    $no_dims = empty($settings['height']) || empty($settings['width']);
    return [
      'src' => $style ? BlazyFile::transformRelative($settings['uri'], $style) : $settings['image_url'],
      'ratio' => $no_dims ? 100 : round((($settings['height'] / $settings['width']) * 100), 2),
    ];
  }

  /**
   * Returns data to provide fake image item of file entity.
   */
  private static function fakeWithdata($file, $image): array {
    if ($settings = self::fromFactory($file, $image)) {
      if ($item = self::fake($settings)) {
        $item->entity = $file;
        $settings['uri'] = $item->uri;
        return ['item' => $item, 'settings' => $settings];
      }
    }
    return [];
  }

  /**
   * Returns image data via ImageFactory to provide fake image item.
   */
  private static function fromFactory($file, $image): array {
    /** @var \Drupal\file\Entity\File $file */
    [$type] = explode('/', $file->getMimeType(), 2);

    if ($type == 'image' && $image->isValid()) {
      return [
        'uri'       => $file->getFileUri(),
        'target_id' => $file->id(),
        'width'     => $image->getWidth(),
        'height'    => $image->getHeight(),
        'alt'       => $file->getFilename(),
        'title'     => $file->getFilename(),
        'type'      => 'image',
      ];
    }
    return [];
  }

}
