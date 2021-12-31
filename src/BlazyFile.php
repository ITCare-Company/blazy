<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Site\Settings;
use Drupal\image\Entity\ImageStyle;

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
   * Provides image url based on the given settings.
   */
  public static function imageUrl(array &$settings): string {
    // Provides image_url, not URI, expected by lazyload.
    $uri = $settings['uri'];
    $valid = self::isValidUri($uri);
    $styled = $valid && empty($settings['unstyled']);
    $url = isset($settings['image_url']) ? $settings['image_url'] : '';

    // Image style modifier can be multi-style images such as GridStack.
    if ($valid && !empty($settings['image_style']) && ($style = ImageStyle::load($settings['image_style']))) {
      $url = self::transformRelative($uri, ($styled ? $style : NULL));
      $settings['cache_tags'] = $style->getCacheTags();

      // Only re-calculate dimensions if not cropped, nor already set.
      if (empty($settings['_dimensions']) && empty($settings['responsive_image_style'])) {
        $settings = array_merge($settings, self::transformDimensions($style, $settings));
      }
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

    $settings['image_url'] = $url;
    return $url;
  }

  /**
   * Provides original unstyled image dimensions based on the given image item.
   */
  public static function imageDimensions(array &$settings, $item = NULL, $initial = FALSE): void {
    $width = $initial ? '_width' : 'width';
    $height = $initial ? '_height' : 'height';
    $uri = $initial ? '_uri' : 'uri';

    if (empty($settings[$width])) {
      $settings[$width] = $item && isset($item->width) ? $item->width : NULL;
      $settings[$height] = $item && isset($item->height) ? $item->height : NULL;
    }
    // Only applies when Image style is empty, no file API, no $item,
    // with unmanaged VEF/ WYSIWG/ filter image without image_style.
    // Prevents 404 warning when video thumbnail missing for a reason.
    if (empty($settings['image_style']) && empty($settings[$width]) && !empty($settings[$uri])) {
      $abs = empty($settings['uri_root']) ? $settings[$uri] : $settings['uri_root'];
      if ($data = @getimagesize($abs)) {
        list($settings[$width], $settings[$height]) = $data;
      }
    }

    // Sometimes they are string, cast them integer to reduce JS logic.
    $settings[$width] = empty($settings[$width]) ? NULL : (int) $settings[$width];
    $settings[$height] = empty($settings[$height]) ? NULL : (int) $settings[$height];
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
    $key = hash('md2', ($style->id() . $data[$uri]));

    if (!isset(static::$styleId[$key])) {
      $width  = $initial ? '_width' : 'width';
      $height = $initial ? '_height' : 'height';

      $width  = isset($data[$width]) ? $data[$width] : NULL;
      $height = isset($data[$height]) ? $data[$height] : NULL;
      $dim    = ['width' => $width, 'height' => $height];

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

}
