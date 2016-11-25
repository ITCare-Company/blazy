<?php

namespace Drupal\blazy;

/**
 * Provides extra media utilities.
 */
class BlazyMedia {

  /**
   * Builds the media field which cannot be displayed using theme_blazy().
   */
  public static function build($media, $settings = []) {
    if (empty($settings['type'])) {
      $build = $media->get($settings['source_field'])->view($settings['view_mode']);
      $build['#settings'] = $settings;

      return self::wrap($build);
    }

    return FALSE;
  }

  /**
   * Converts field with #type iframe into a responsive Blazy with ratio.
   */
  public static function wrap($field = []) {
    // Media entity is a single being, reasonable to work with multi-value?
    $item       = $field[0];
    $settings   = isset($field['#settings']) ? $field['#settings'] : [];
    $attributes = &$item['#attributes'];
    $iframe     = isset($item['#tag']) && $item['#tag'] == 'iframe';

    if ($iframe && !empty($attributes['src'])) {
      $attributes['data-src'] = $attributes['src'];
      $attributes['class'][] = 'b-lazy media__iframe media__element';
      $attributes['src'] = 'about:blank';
      $attributes['allowfullscreen'] = TRUE;
    }

    // Wraps the media item to allow consistency for EB/SB.
    $build = [
      '#theme'      => 'container',
      '#children'   => $item,
      '#attributes' => ['class' => ['media']],
      '#settings'   => $settings,
    ];

    if (!empty($settings['bundle'])) {
      $build['#attributes']['class'][] = 'media--' . str_replace('_', '-', $settings['bundle']);
    }

    // Adds helper for Entity Browser small thumbnail selection.
    if (!empty($settings['thumbnail_style']) && !empty($settings['uri'])) {
      $build['#attributes']['data-thumb'] = Blazy::buildThumbnailUrl($settings);
    }

    // Currently known media entities using iframe: Instagram.
    if ($iframe) {
      $build['#attributes']['class'][] = 'media--ratio';

      if (!empty($attributes['width']) && !empty($attributes['height'])) {
        $padding_bottom = round((($attributes['height'] / $attributes['width']) * 100), 2);
        $build['#attributes']['style'] = 'padding-bottom: ' . $padding_bottom . '%';
      }
    }
    else {
      $build['#attributes']['class'][] = 'media--rendered';
    }

    // Clone relevant keys as field wrapper is no longer in use.
    foreach (['attached', 'cache'] as $key) {
      if (isset($field["#$key"])) {
        $build["#$key"] = $field["#$key"];
      }
    }

    return $build;
  }

}
