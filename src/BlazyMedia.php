<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\NestedArray;
use Drupal\image\Entity\ImageStyle;

/**
 * Provides extra utilities to work with core Media.
 *
 * @todo rework this for core Media.
 */
class BlazyMedia {

  /**
   * Builds the media field which cannot be displayed using theme_blazy().
   *
   * Some use URLs from inputs, some local files.
   *
   * @param object $media
   *   The media being rendered.
   * @param array $settings
   *   The contextual settings array.
   *
   * @return array|bool
   *   The renderable array of the media field, or false if not applicable.
   */
  public static function build($media, array $settings = []) {
    // Prevents fatal error with disconnected internet when having ME Facebook,
    // ME SlideShare, resorted to static thumbnails to avoid broken displays.
    if (!empty($settings['input_url'])) {
      // @todo: Remove when ME Facebook alike handles this.
      try {
        \Drupal::httpClient()->get($settings['input_url'], ['timeout' => 7]);
      }
      catch (\Exception $e) {
        return FALSE;
      }
    }

    $build = $media->get($settings['source_field'])->view($settings['view_mode']);
    if (empty($build) && $settings['media_source'] == 'video_file') {
      // @todo: muted, width, height.
      $build = $media->get($settings['source_field'])->view([
        'type' => 'file_video',
        'view_mode' => $settings['view_mode'],
      ]);
    }

    $build['#settings'] = $settings;

    return isset($build[0]) ? self::wrap($build) : $build;
  }

  /**
   * Returns a field to be wrapped by theme_container().
   *
   * Currently Instagram, and SlideShare are known to use iframe, and thus can
   * be converted into a responsive Blazy with fluid ratio. The rest are
   * returned as is, only wrapped by .media wrapper for consistency with complex
   * interaction like EB.
   *
   * @param array $field
   *   The source renderable array $field.
   *
   * @return array
   *   The new renderable array of the media item wrapped by theme_container().
   */
  public static function wrap(array $field = []) {
    $item       = $field[0];
    $settings   = isset($field['#settings']) ? $field['#settings'] : [];
    $iframe     = isset($item['#tag']) && $item['#tag'] == 'iframe';
    $attributes = [];
    $use_ratio  = FALSE;
    $settings  += BlazyDefault::itemSettings();

    if (isset($item['#attributes'])) {
      $attributes = &$item['#attributes'];
    }

    // Converts iframes into lazyloaded ones.
    if ($iframe && !empty($attributes['src'])) {
      $settings['embed_url'] = $attributes['src'];

      if (!empty($attributes['width']) && !empty($attributes['height'])) {
        $settings['width'] = $attributes['width'];
        $settings['height'] = $attributes['height'];
      }

      $attributes = NestedArray::mergeDeep($attributes, Blazy::iframeAttributes($settings));
      $attributes['class'][] = 'media__iframe media__element';

      // Enforces aspect ratio for responsiveness.
      $use_ratio = TRUE;
    }
    // Media with local files: video.
    elseif (isset($item['#files'], $item['#files'][0]['file'])) {
      $class = 'b-lazy';
      if ($settings['ratio']) {
        // For some reason, the setAttribute nullifies the previously set value.
        // Hence why we make it a concatenated string to put them all for now.
        $class .= ' media__element';
        $use_ratio = TRUE;
      }
      $attributes->setAttribute('class', $class);
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
      $build['#attributes']['data-thumb'] = ImageStyle::load($settings['thumbnail_style'])->buildUrl($settings['uri']);
    }

    // See comment above for known media entities using iframe.
    if (!$iframe) {
      $build['#attributes']['class'][] = 'media--rendered';
    }

    if ($use_ratio) {
      Blazy::aspectRatioAttributes($build['#attributes'], $settings);
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
