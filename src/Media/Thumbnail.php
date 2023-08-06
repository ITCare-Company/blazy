<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\blazy\Blazy;

/**
 * Provides thumbnail-related methods.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class Thumbnail {

  /**
   * Returns the thumbnail image using theme_image(), or theme_image_style().
   *
   * Alt and SRC will be auto-escaped when entering Twig, this is just to make
   * sure no unknown edge cases get in the way.
   *
   * @see https://www.drupal.org/node/2489544
   */
  public static function view(array $settings, $item = NULL, array $captions = []): array {
    Blazy::verify($settings);

    $blazies       = $settings['blazies'];
    $prefix        = $blazies->get('item.prefix', 'slide');
    $caption       = $blazies->get('item.caption', 'caption');
    $thumb_class   = $prefix . '__thumbnail';
    $caption_class = $prefix . '__caption';
    $output        = [];

    // At 3.x, to minimize more dups, not implemented, yet.
    // @todo make a theme_blazy_thumbnail(), if any worth.
    if ($blazies->use('theme_thumbnail')) {
      // @todo remove debug:
      $thumb_class = 'blazy__thumbnail ' . $thumb_class;
      if ($thumbnail = self::image($settings, $item, $thumb_class)) {
        $output[$prefix] = $thumbnail;
      }
      if ($captions) {
        $output[$caption] = Blazy::content($captions, 'div', $caption_class);
      }

      // @todo remove or keep it after another check.
      $output['#settings'] = $settings;
      return $output;
    }
    return self::image($settings, $item);
  }

  /**
   * Returns the thumbnail image using theme_image(), or theme_image_style().
   *
   * Given SVG and co, data URI, UGC, even thumbnails are no longer peaceful.
   */
  private static function image(array $settings, $item = NULL, $class = NULL): array {
    $blazies = $settings['blazies'];
    $uri     = $blazies->get('thumbnail.uri') ?: $blazies->get('image.uri');

    if (!$uri) {
      return [];
    }

    $unstyled = $blazies->is('unstyled');
    $style    = $blazies->get('thumbnail.id') ?: $settings['thumbnail_style'] ?? NULL;
    $alt      = $blazies->get('image.alt');
    $valid    = $blazies->get('image.valid') ?: BlazyFile::isValidUri($uri);

    // Thumbnails can use image styles, except for SVG for now.
    // @todo check for any modules (ImageMagick) which convert SVG to image,
    // and remove this check if present, leaving it for external URL + data URI.
    if ($valid && !$blazies->is('svg')) {
      $unstyled = FALSE;
    }

    $content = [
      '#theme'      => $unstyled ? 'image' : 'image_style',
      '#style_name' => $style ?: 'thumbnail',
      '#uri'        => $valid ? $uri : UrlHelper::stripDangerousProtocols($uri),
      '#item'       => $item,
      '#alt'        => $alt ? Html::escape(strip_tags($alt)) : '',
    ];

    return Blazy::content($content, 'div', $class);
  }

}
