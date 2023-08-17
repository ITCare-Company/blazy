<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\blazy\internals\Internals;

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
   * Since 2.17, thumbnail approaches are changed too for compelling reasons:
   *   - Thumbnails are poorly informed given the new SVG, or unstyled URIs.
   *   - Captions can already be merged as part of theme_blazy().
   *   - Adding caption fields, such as File description, are easier to update
   *     than walking through each sub-modules due to their hard-coded natures.
   *   - Shortly, economy maintenance.
   */
  public static function view(array $settings, $item = NULL, array $captions = []): array {
    $blazies       = Internals::verify($settings);
    $prefix        = $blazies->get('item.prefix', 'slide');
    $caption       = $blazies->get('item.caption', 'caption');
    $use_blazy     = $blazies->use('theme_thumbnail');
    $thumb_class   = $use_blazy ? $prefix . '__thumbnail' : NULL;
    $caption_class = $use_blazy ? $prefix . '__caption' : NULL;
    $output        = [];

    // At 3.x, to minimize more dups, not implemented, yet.
    // @todo make a theme_blazy_thumbnail(), if any worth.
    if ($thumbnail = self::image($settings, $item, $thumb_class)) {
      $output[$prefix] = $thumbnail;
    }
    if ($captions) {
      $output[$caption] = Internals::toHtml($captions, 'div', $caption_class);
    }

    // Needed by sub-modules for their routines, even useless since 2.17.
    $output['#settings'] = $settings;
    return $output;
  }

  /**
   * Returns the thumbnail image using theme_image(), or theme_image_style().
   *
   * Given SVG and co, data URI, UGC, even thumbnails are no longer peaceful.
   * Alt and SRC will be auto-escaped when entering Twig, this is just to make
   * sure no unknown edge cases get in the way.
   *
   * @see https://www.drupal.org/node/2489544
   */
  private static function image(array $settings, $item = NULL, $class = NULL): array {
    $blazies = $settings['blazies'];
    $uri     = $blazies->get('thumbnail.uri') ?: $blazies->get('image.uri');

    if (!$uri) {
      // Only Views output, if not having image nor blazy formatters.
      if ($item && is_array($item)) {
        return Internals::toHtml($item, 'div', $class);
      }
      return [];
    }

    $unstyled = $blazies->is('unstyled');
    $style    = $blazies->get('thumbnail.id') ?: $settings['thumbnail_style'] ?? NULL;
    $alt      = $blazies->get('image.alt');
    $valid    = $blazies->get('image.valid') ?: BlazyFile::isValidUri($uri);

    // Thumbnail style is the only option to display thumbnails.
    if (!$style) {
      return [];
    }

    // Thumbnails can use image styles, except for SVG for now.
    // @todo check for any modules (ImageMagick) which convert SVG to image,
    // and remove this check if present, leaving it for external URL + data URI.
    if ($valid && !$blazies->is('svg')) {
      $unstyled = FALSE;
    }

    $content = [
      '#theme'      => $unstyled ? 'image' : 'image_style',
      '#style_name' => $style,
      '#uri'        => $valid ? $uri : UrlHelper::stripDangerousProtocols($uri),
      '#item'       => $item,
      '#alt'        => $alt ? Html::escape(strip_tags($alt)) : '',
    ];

    return Internals::toHtml($content, 'div', $class);
  }

}
