<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Site\Settings;
use Drupal\image\Entity\ImageStyle;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyUtil;

/**
 * Provides file_BLAH BC for D8 - D10+ till D11 rules.
 *
 * @todo remove deprecated functions post D11, not D10, and when D8 is dropped.
 */
class BlazyFile {

  /**
   * The image style ID.
   *
   * @var array
   */
  private static $styleId;

  /**
   * Determines whether the URI has a valid scheme for file API operations.
   *
   * @param string $uri
   *   The URI to be tested.
   *
   * @return bool
   *   TRUE if the URI is valid.
   */
  public static function isValidUri($uri): bool {
    if (!empty($uri) && $manager = Blazy::streamWrapperManager()) {
      return $manager->isValidUri($uri);
    }
    return FALSE;
  }

  /**
   * Creates an absolute web-accessible URL string.
   *
   * @param string $uri
   *   The file uri.
   *
   * @return string
   *   Returns an absolute web-accessible URL string.
   */
  public static function createUrl($uri): string {
    if ($gen = Blazy::fileUrlGenerator()) {
      return $gen->generateAbsoluteString($uri);
    }

    $function = 'file_create_url';
    return is_callable($function) ? $function($uri) : '';
  }

  /**
   * Transforms an absolute URL of a local file to a relative URL.
   *
   * @param string $uri
   *   The file uri.
   * @param object $style
   *   The optional image style instance.
   *
   * @return string
   *   Returns an absolute URL of a local file to a relative URL.
   */
  public static function transformRelative($uri, $style = NULL): string {
    $url = $style ? $style->buildUrl($uri) : self::createUrl($uri);

    if ($gen = Blazy::fileUrlGenerator()) {
      return $gen->transformRelative($url);
    }

    $function = 'file_url_transform_relative';
    return is_callable($function) ? $function($url) : '';
  }

  /**
   * Returns the URI from the given image URL, relevant for unmanaged files.
   */
  public static function buildUri($url): ?string {
    if (!UrlHelper::isExternal($url) && $normal_path = UrlHelper::parse($url)['path']) {
      // If the request has a base path, remove it from the beginning of the
      // normal path as it should not be included in the URI.
      $base_path = \Drupal::request()->getBasePath();
      if ($base_path && mb_strpos($normal_path, $base_path) === 0) {
        $normal_path = str_replace($base_path, '', $normal_path);
      }

      $public_path = Settings::get('file_public_path', 'sites/default/files');

      // Only concerns for the correct URI, not image URL which is already being
      // displayed via SRC attribute. Don't bother language prefixes for IMG.
      if ($public_path && mb_strpos($normal_path, $public_path) !== FALSE) {
        $rel_path = str_replace($public_path, '', $normal_path);
        return Blazy::streamWrapperManager()->normalizeUri($rel_path);
      }
    }
    return NULL;
  }

  /**
   * Extracts uris from file/ media entity.
   *
   * @todo merge urls here as well once puzzles are solved: URI may be fed by
   * field formatters like this, blazy_filter, or manual call.
   */
  public static function urisFromField(array &$settings, $items, array $entities = []): array {
    $blazies = &$settings['blazies'];
    if ($uris = $blazies->get('uris')) {
      return $uris;
    }

    $func = function ($item, $entity = NULL) use (&$settings) {
      $uri = $image = NULL;
      if ($settings['field_type'] == 'image') {
        $image = $item;
        $uri = ($file = $item->entity) && empty($item->uri) ? $file->getFileUri() : $item->uri;
      }
      elseif ($entity && $entity->hasField('thumbnail') && $image = $entity->get('thumbnail')->first()) {
        if ($file = ($image->entity ?? NULL)) {
          $uri = $file->getFileUri();
        }
      }

      // Only needed the first found image item.
      if (!isset($settings['_item']) || empty($settings['_item'])) {
        $settings['_item'] = $image;
      }
      return $uri;
    };

    $output = [];
    foreach ($items as $key => $item) {
      // Respects empty URI to keep indices intact for correct mixed media.
      $output[] = $func($item, $entities[$key] ?? NULL);
    }

    $blazies->set('uris', $output);
    return $output;
  }

  /**
   * Checks for [Responsive] image styles.
   */
  public static function imageStyles(array &$settings, $multiple = FALSE): void {
    $blazies = &$settings['blazies'];

    // Multiple is a flag for various styles: Blazy Filter, GridStack, etc.
    // While fields can only have one image style per field.
    if (!$blazies->get('image.style') || $multiple) {
      $image_style = NULL;
      if (!empty($settings['image_style'])) {
        $image_style = ImageStyle::load($settings['image_style']);
      }

      $blazies->set('image.style', $image_style);
    }

    if (!$blazies->get('resimage.style') || $multiple) {
      $style = $settings['responsive_image_style'] ?? NULL;
      $exist = $settings['_resimage'] = $settings['_resimage'] ?: \blazy()->getModuleHandler()->moduleExists('responsive_image');
      $settings['_resimage'] = $applicable = $exist && $style;
      $responsive_image_style = $settings['resimage'] ?? NULL;

      if (empty($responsive_image_style) && $applicable) {
        $responsive_image_style = \blazy()->entityLoad($style, 'responsive_image_style');
      }

      if ($responsive_image_style) {
        // @todo remove responsive_image_style_id.
        $id = $settings['responsive_image_style_id'] = $responsive_image_style->id();
        $styles = BlazyResponsiveImage::getStyles($responsive_image_style);

        $blazies->set('resimage.id', $id)
          ->set('resimage.caches', $styles['caches'])
          ->set('resimage.styles', $styles['styles']);
      }

      // @todo remove $settings['resimage'] for blazies after sub-modules.
      $settings['resimage'] = $responsive_image_style;
      $blazies->set('resimage.style', $responsive_image_style);
    }
  }

  /**
   * Provides image url based on the given settings.
   */
  public static function imageUrl(array &$settings, $style = NULL): string {
    // Provides image_url, not URI, expected by lazyload.
    $uri = $settings['uri'] ?? $settings['_uri'];
    ['url' => $url, 'style' => $style] = self::imageUrlAndStyle($uri, $settings, $style);

    $settings['image_url'] = $url;

    // @todo move it out here.
    if ($style) {
      $settings['cache_tags'] = $style->getCacheTags();

      // Only re-calculate dimensions if not cropped, nor already set.
      if (empty($settings['_dimensions']) && empty($settings['responsive_image_style'])) {
        $settings = array_merge($settings, self::transformDimensions($style, $settings));
      }
    }

    return $url;
  }

  /**
   * Returns image url and style based on the given settings.
   */
  public static function imageUrlAndStyle($uri, array $settings, $style = NULL): array {
    $blazies = $settings['blazies'];
    // Provides image_url, not URI, expected by lazyload.
    $valid = self::isValidUri($uri);
    $styled = $valid && !$blazies->get('is.unstyled');
    $_style = $settings['image_style'] ?? '';
    $style = $style ?: (empty($_style) ? NULL : ImageStyle::load($_style));
    $url = $settings['image_url'] ?? '';

    // Image style modifier can be multi-style images such as GridStack.
    if ($valid && $style) {
      $url = self::transformRelative($uri, ($styled ? $style : NULL));
    }
    else {
      $rel_url = $valid ? self::transformRelative($uri) : $uri;
      $url = empty($url) ? $rel_url : $url;
    }

    // Just in case, an attempted kidding gets in the way, relevant for UGC.
    $data_uri = mb_substr($url, 0, 10) === 'data:image';
    if (!empty($settings['_check_protocol']) && !$data_uri) {
      $url = UrlHelper::stripDangerousProtocols($url);
    }

    $ratio = empty($settings['width']) ? 100 : round((($settings['height'] / $settings['width']) * 100), 2);

    return [
      'url' => $url,
      'style' => $style,
      'ratio' => $ratio,
    ];
  }

  /**
   * Prepares CSS background image.
   *
   * @todo remove and merge it with imageUrlAndStyle.
   */
  public static function backgroundImage(array $settings, $style = NULL) {
    return [
      'src' => $style ? self::transformRelative($settings['uri'], $style) : $settings['image_url'],
      'ratio' => round((($settings['height'] / $settings['width']) * 100), 2),
    ];
  }

  /**
   * Provides original unstyled image dimensions based on the given image item.
   */
  public static function imageDimensions(array &$settings, $item = NULL, $initial = FALSE): void {
    $width = $initial ? '_width' : 'width';
    $height = $initial ? '_height' : 'height';
    $uri = $initial ? '_uri' : 'uri';

    if (empty($settings[$width]) && $item) {
      $settings[$width] = $item->width ?? NULL;
      $settings[$height] = $item->height ?? NULL;
    }

    // Only applies when Image style is empty, no file API, no $item,
    // with unmanaged VEF/ WYSIWG/ filter image without image_style.
    // Prevents 404 warning when video thumbnail missing for a reason.
    if (empty($settings['image_style']) && empty($settings[$width]) && !empty($settings[$uri])) {
      $abs = empty($settings['uri_root']) ? $settings[$uri] : $settings['uri_root'];
      if ($data = @getimagesize($abs)) {
        [$settings[$width], $settings[$height]] = $data;
      }
    }

    // Sometimes they are string, cast them integer to reduce JS logic.
    $settings[$width] = empty($settings[$width]) ? NULL : (int) $settings[$width];
    $settings[$height] = empty($settings[$height]) ? NULL : (int) $settings[$height];
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
    $blazies = &$settings['blazies'];

    // The SVG placeholder should accept either original, or styled image.
    $is_media = in_array($settings['type'], ['audio', 'video']);

    // Lazy load insanity given various features/ media types + loading option.
    // @todo re-check if any misses.
    $lazy = $settings['background'];
    $unlazy = $blazies->get('is.slider') && $settings['delta'] == $blazies->get('initial');
    $unlazy = $unlazy ? TRUE : $settings['unlazy'];
    $settings['unlazy'] = $lazy ? FALSE : $unlazy;

    // BlazyFilter, or image style with crop, may already set these.
    self::imageDimensions($settings, $item);

    // Provides image url based on the given settings.
    self::imageUrl($settings);

    // @todo remove $settings['placeholder'] after another check.
    $settings['placeholder'] = $placeholder = $blazies->get('ui.placeholder') ?: BlazyUtil::generatePlaceholder($settings['width'], $settings['height']);
    $settings['use_media'] = $settings['embed_url'] && $is_media;
    $settings['use_loading'] = $settings['unlazy'] ? FALSE : $settings['use_loading'];

    $blazies->set('ui.placeholder', $placeholder);
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
   * Build thumbnails, also to provide placeholder for blur effect.
   */
  public static function placeholder(array &$settings, $style = NULL, $path = '') {
    if (empty($path) && ($style = \blazy()->entityLoad('thumbnail', 'image_style')) && self::isValidUri($settings['uri'])) {
      $path = $style->buildUri($settings['uri']);
    }

    if ($path && self::isValidUri($path)) {
      // Ensures the thumbnail exists before creating a dataURI.
      if (!is_file($path) && $style) {
        $style->createDerivative($settings['uri'], $path);
      }

      // Overrides placeholder with data URI based on configured thumbnail.
      if (is_file($path)) {
        $settings['placeholder_fx'] = 'data:image/' . pathinfo($path, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($path));
        // Prevents double animations.
        $settings['use_loading'] = FALSE;
      }
    }
  }

  /**
   * Build thumbnails, also to provide placeholder for blur effect.
   */
  public static function thumbnailAndPlaceholder(array &$attributes, array &$settings) {
    $blazies = &$settings['blazies'];
    $settings['placeholder_ui'] = $blazies->get('ui.placeholder');
    $path = $style = '';
    // With CSS background, IMG may be empty, add thumbnail to the container.
    if (!$blazies->get('is.external') && $settings['thumbnail_style']) {
      $style = \blazy()->entityLoad($settings['thumbnail_style'], 'image_style');
      if ($style) {
        $path = $style->buildUri($settings['uri']);
        $attributes['data-thumb'] = $settings['thumbnail_url'] = self::transformRelative($settings['uri'], $style);

        if (!is_file($path) && self::isValidUri($path)) {
          $style->createDerivative($settings['uri'], $path);
        }
      }
    }

    // Supports unique thumbnail different from main image, such as logo for
    // thumbnail and main image for company profile.
    if (!empty($settings['thumbnail_uri'])) {
      $path = $settings['thumbnail_uri'];
      $attributes['data-thumb'] = $settings['thumbnail_url'] = self::transformRelative($path);
    }

    // Provides image effect if so configured unless being sandboxed.
    if (!$blazies->get('is.sandboxed') && $blazies->get('fx')) {
      $attributes['class'][] = 'media--fx';

      // Ensures at least a hook_alter is always respected. This still allows
      // Blur and hook_alter for Views rewrite issues, unless global UI is set
      // which was already warned about anyway.
      if (empty($settings['placeholder_fx']) && !$blazies->get('is.unstyled')) {
        self::placeholder($settings, $style, $path);
      }

      // Being a separated .b-blur with .b-lazy, this should work for any lazy.
      $attributes['data-animation'] = $settings['fx'];
    }

    // Mimicks private _responsive_image_image_style_url, #3119527.
    $resimage = $blazies->get('resimage.style');
    if (empty($settings['image_style']) && $resimage) {
      $fallback = $resimage->getFallbackImageStyle();
      if ($fallback == '_empty image_') {
        $placeholder = BlazyUtil::generatePlaceholder($settings['width'], $settings['height']);
        $settings['image_url'] = $blazies->get('ui.placeholder') ?: $placeholder;
      }
      else {
        $settings['image_style'] = $fallback;
      }
    }
  }

  /**
   * Preload late-discovered resources for better performance.
   *
   * @see https://web.dev/preload-critical-assets/
   * @see https://caniuse.com/?search=preload
   * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Link_types/preload
   * @see https://developer.chrome.com/blog/new-in-chrome-73/#more
   * @todo support multiple hero images like carousels.
   */
  public static function preload(array &$load, array $settings = []) {
    $blazies = $settings['blazies'];
    $uris = $blazies->get('uris', []);
    if (empty($uris)) {
      return;
    }

    $mime = mime_content_type($uris[0]);
    [$type] = array_map('trim', explode('/', $mime, 2));

    $link = function ($url, $uri = NULL, $item = NULL) use ($mime, $type): array {
      // Each field may have different mime types for each image just like URIs.
      $mime = $uri ? mime_content_type($uri) : $mime;
      if ($item) {
        $item_type = $item['type'] ?? '';
        $mime = $item_type ? $item_type->value() : $mime;
      }

      [$type] = array_map('trim', explode('/', $mime, 2));
      $key = hash('md2', $url);

      $attrs = [
        'rel' => 'preload',
        'as' => $type,
        'href' => $url,
        'type' => $mime,
      ];

      $suffix = '';
      if ($srcset = ($item['srcset'] ?? '')) {
        $suffix = '_responsive';
        $attrs['imagesrcset'] = $srcset->value();

        if ($sizes = ($item['sizes'] ?? '')) {
          $attrs['imagesizes'] = $sizes->value();
        }
      }

      return [
        [
          '#tag' => 'link',
          '#attributes' => $attrs,
        ],
        'blazy' . $suffix . '_' . $type . $key,
      ];
    };

    $links = [];

    // Supports multiple sources.
    if ($sources = $blazies->get('resimage.sources', [])) {
      foreach ($sources as $source) {
        $url = $source['fallback'];
        foreach ($source['items'] as $key => $item) {
          if (!empty($item['srcset'])) {
            $links[] = $link($url, NULL, $item);
          }
        }
      }
    }
    else {
      foreach ($uris as $uri) {
        // URI might be empty with mixed media, but indices are preserved.
        if ($uri) {
          ['url' => $url] = self::imageUrlAndStyle($uri, $settings);
          $links[] = $link($url, $uri);
        }
      }
    }

    if ($links) {
      foreach ($links as $key => $value) {
        $load['html_head'][$key] = $value;
      }
    }
  }

}
