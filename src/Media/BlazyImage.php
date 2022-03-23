<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\file\FileInterface;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\media\MediaInterface;
use Drupal\blazy\Blazy;

/**
 * Provides image-related methods.
 */
class BlazyImage {

  /**
   * Checks if the image style contains crop in the effect name.
   *
   * @var array
   */
  private static $crop;

  /**
   * Checks if image dimensions are set.
   *
   * @var array
   */
  private static $isCropSet;

  /**
   * The image style ID.
   *
   * @var array
   */
  private static $styleId;

  /**
   * Provides original unstyled image dimensions based on the given image item.
   *
   * This one is original image, not styled like self:transformDimensions().
   */
  public static function dimensions(array &$settings, $item = NULL, $initial = FALSE): void {
    $_width  = $initial ? '_width' : 'width';
    $_height = $initial ? '_height' : 'height';
    $_uri    = $initial ? '_uri' : 'uri';
    $width   = $settings[$_width] ?? NULL;
    $height  = $settings[$_height] ?? NULL;
    $uri     = $settings[$_uri] ?? '';

    if (empty($height) && $item) {
      $width = $item->width ?? NULL;
      $height = $item->height ?? NULL;
    }

    // Only applies when Image style is empty, no file API, no $item,
    // with unmanaged VEF/ WYSIWG/ filter image without image_style.
    if ($uri && empty($settings['image_style']) && empty($height)) {
      $abs = empty($settings['uri_root']) ? $uri : $settings['uri_root'];
      // Must be valid URI, or web-accessible url, not: /modules|themes/...
      if (!BlazyFile::isValidUri($abs) && mb_substr($abs, 0, 1) == '/') {
        if ($request = Blazy::requestStack()) {
          $abs = $request->getCurrentRequest()->getSchemeAndHttpHost() . $abs;
        }
      }

      // Prevents 404 warning when video thumbnail missing for a reason.
      if ($data = @getimagesize($abs)) {
        [$width, $height] = $data;
      }
    }

    // Sometimes they are string, cast them integer to reduce JS logic.
    $settings[$_width] = $width;
    $settings[$_height] = $height;
    self::toInt($settings, $_width, $_height);
  }

  /**
   * Returns fake image item based on the given $settings.
   */
  public static function fake(array $settings) {
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
   * @todo compare and merge with BlazyMedia::imageItem(), and the two below.
   * @todo simplify this, like everything else. An obvious confusion here.
   */
  public static function fromAny($object = NULL, array $settings = []): array {
    // @todo remove check at 3.x after sub-modules and VEF removed.
    Blazy::verify($settings);

    $blazies = $settings['blazies'];
    $uri     = NULL;
    $output  = [];

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
        $uri = $entity->getFileUri();
        if ($uri && $image = $factory->get($uri)) {
          $output = self::fakeWithdata($entity, $image);
        }
      }
    }

    // Called by formatters.
    if (empty($output)) {
      $options = [
        'entity' => $entity,
        'source' => $entity == $object ? NULL : $object,
        'settings' => $settings,
      ];

      // We have a Media entity.
      if ($item = self::item(NULL, $options)) {
        $uri = BlazyFile::uri($item);

        // @todo remove.
        $settings['uri'] = $uri;
        $output = ['item' => $item, 'settings' => $settings];
      }
    }

    if ($uri) {
      $blazies->set('uri', $uri);
    }
    return $output;
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
    $blazies  = $settings['blazies'] ?? NULL;
    $poster   = $settings['image'] ?? FALSE;
    $name     = $name ?: $poster;

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
    $blazy   = Blazy::service('blazy.manager');
    $blazies = $settings['blazies'];
    $exist   = $blazies->is('resimage');

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
  public static function thumbnail(array $settings, $item = NULL): array {
    // @todo remove check after another check.
    $blazies = $settings['blazies'] ?? NULL;
    $default = $settings['uri'] ?? NULL;
    $uri     = $blazies ? $blazies->get('uri', $default) : $default;

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
    $_uri = $initial ? '_uri' : 'uri';
    $key  = hash('md2', ($style->id() . $data[$_uri] . $initial));

    if (!isset(static::$styleId[$key])) {
      $_width  = $initial ? '_width' : 'width';
      $_height = $initial ? '_height' : 'height';
      $width   = $data[$_width] ?? NULL;
      $height  = $data[$_height] ?? NULL;
      $dim     = ['width' => $width, 'height' => $height];

      // Funnily $uri is ignored at all core image effects.
      $style->transformDimensions($dim, $data[$_uri]);

      // Sometimes they are string, cast them integer to reduce JS logic.
      self::toInt($dim, 'width', 'height');

      // Keys here are hard-coded, so to be inherited by children as intended.
      // The underscore prefix is to identify the source/ original unstyled
      // image properties, not related to the final output printed here.
      // See self::initialDimensions().
      // @todo re-check if the container needs image style dimensions.
      static::$styleId[$key] = [
        'width' => $dim['width'],
        'height' => $dim['height'],
      ];
    }
    return static::$styleId[$key];
  }

  /**
   * Prepares URLs, placeholder, and dimensions for an individual image.
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
  public static function prepare(array &$settings, $item = NULL): void {
    $blazies = $settings['blazies'];
    $style   = $blazies->get('image.style');

    // Might be called from Views without Blazy formatter, like Image formatter.
    // Since Blazy:2.9, image style entity is loaded once at container level,
    // but might still be needed fr adopted Image formatter by a Views style.
    if (!$style && !empty($settings['image_style'])) {
      self::styles($settings);
      $style = $blazies->get('image.style');
    }

    // BlazyFilter, or image style with crop, may already set these.
    self::dimensions($settings, $item);

    // Provides image url based on the given settings.
    $uri     = $settings['uri'] ?? $settings['_uri'] ?? NULL;
    $uri     = $blazies->get('uri', $uri);
    $valid   = BlazyFile::isValidUri($uri);
    $styled  = $valid && !$blazies->is('unstyled');
    $url     = $settings['image_url'] ?? '';
    $url     = $blazies->get('image.url', $url);
    $options = ['url' => $url, 'sanitize' => $blazies->is('unsafe')];
    $url     = BlazyFile::transformRelative($uri, ($styled ? $style : NULL), $options);

    if ($style) {
      $blazies->set('cache.tags', $style->getCacheTags(), TRUE);

      // Only re-calculate dimensions if not cropped, nor already set.
      if (!$blazies->is('dimensions')
        && empty($settings['responsive_image_style'])) {
        $settings = array_merge($settings, self::transformDimensions($style, $settings));
      }
    }

    // Currently doesn't affect option.ratio, a failsafe for BG, else collapsed.
    $ratio = self::ratio($settings);

    $blazies->set('image.ratio', $ratio);
    $blazies->set('image.url', $url);
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

  /**
   * Converts dimensions to integer unless empty.
   */
  private static function toInt(array &$settings, $width, $height): void {
    $settings[$width] = empty($settings[$width]) ? NULL : (int) $settings[$width];
    $settings[$height] = empty($settings[$height]) ? NULL : (int) $settings[$height];
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
   * @see \Drupal\blazy\Field\BlazyEntityMediaBase::buildElement
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
   * Prepares CSS background image.
   */
  public static function background(array $settings, $style = NULL) {
    $blazies = $settings['blazies'];
    $url     = $blazies->get('image.url');
    $uri     = $blazies->get('uri');
    $style   = $style ?: $blazies->get('image.style');

    // @tbd replace src with URL before 3.x, or keep it.
    return [
      'src' => $style ? BlazyFile::transformRelative($uri, $style) : $url,
      'ratio' => self::ratio($settings),
    ];
  }

  /**
   * Provides a computed image ratio aka fluid ratio.
   *
   * Addresses multi-image-style Responsive image or, plain old one.
   * A failsafe for BG, else collapsed.
   *
   * @todo decide if to provide NULL or 0 instead.
   */
  public static function ratio(array $settings) {
    $no_dims = empty($settings['height']) || empty($settings['width']);
    return $no_dims ? 100 : round((($settings['height'] / $settings['width']) * 100), 2);
  }

  /**
   * Returns the image style if it contains crop effect.
   *
   * @param object $style
   *   The image style to check for.
   *
   * @return object
   *   Returns the image style instance if it contains crop effect, else NULL.
   */
  public static function getCrop($style): ?object {
    $id = $style->id();

    if (!isset(static::$crop[$id])) {
      $output = NULL;

      foreach ($style->getEffects() as $effect) {
        if (strpos($effect->getPluginId(), 'crop') !== FALSE) {
          $output = $style;
          break;
        }
      }
      static::$crop[$id] = $output;
    }
    return static::$crop[$id];
  }

  /**
   * Sets dimensions once to reduce method calls, if image style contains crop.
   *
   * @param array $settings
   *   The settings being modified.
   * @param object $style
   *   The image style to check for crp effect.
   */
  public static function cropDimensions(array &$settings, $style): void {
    $id = $style->id();

    if (!isset(static::$isCropSet[$id])) {
      // If image style contains crop, sets dimension once, and let all inherit.
      if ($crop = self::getCrop($style)) {
        $blazies = $settings['blazies'];
        $settings = array_merge($settings, self::transformDimensions($crop, $settings, TRUE));

        // Informs individual images that dimensions are already set once.
        $blazies->set('is.dimensions', TRUE);
      }

      static::$isCropSet[$id] = TRUE;
    }
  }

}
