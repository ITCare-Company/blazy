<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\NestedArray;

/**
 * Provides extra utilities to work with core Media.
 *
 * This class makes it possible to have a mixed display of all media entities,
 * useful for Blazy Grid, Slick Carousel, GridStack contents as mixed media.
 * This approach is alternative to regular preprocess overrides, still saner
 * than iterating over unknown like template_preprocess_media_entity_BLAH, etc.
 *
 * @todo rework this for core Media, and refine for theme_blazy().
 */
class BlazyMedia {

  /**
   * Builds the media field which is not understood by theme_blazy().
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
      try {
        \Drupal::httpClient()->get($settings['input_url'], ['timeout' => 7]);
      }
      catch (\Exception $e) {
        return FALSE;
      }
    }

    $settings['type'] = 'rich';
    $options = $settings['view_mode'];
    if ($settings['media_source'] == 'video_file') {
      $options = ['type' => 'file_video', 'view_mode' => $settings['view_mode']];
    }

    $build = $media->get($settings['source_field'])->view($options);
    $build['#settings'] = $settings;

    return isset($build[0]) ? self::wrap($build) : $build;
  }

  /**
   * Returns a field item/ content to be wrapped by theme_blazy().
   *
   * @param array $field
   *   The source renderable array $field.
   *
   * @return array
   *   The renderable array of the media item to be wrapped by theme_blazy().
   */
  public static function wrap(array $field = []) {
    $item       = $field[0];
    $settings   = $field['#settings'];
    $iframe     = isset($item['#tag']) && $item['#tag'] == 'iframe';
    $attributes = [];

    if (isset($item['#attributes'])) {
      $attributes = &$item['#attributes'];
    }

    // Update iframe/video dimensions based on configurable image style, if any.
    foreach (['width', 'height'] as $key) {
      if (!empty($settings[$key])) {
        $attributes[$key] = $settings[$key];
      }
    }

    // Converts iframes into lazyloaded ones.
    // Iframes: Googledocs, SlideShare. Hardcoded: Soundcloud, Spotify.
    if ($iframe && !empty($attributes['src'])) {
      $settings['embed_url'] = $attributes['src'];
      $attributes = NestedArray::mergeDeep($attributes, Blazy::iframeAttributes($settings));
    }
    // Media with local files: video.
    elseif (isset($item['#files'], $item['#files'][0]['file'])) {
      // Do this as $item['#blazy'] is not available as file_video variables.
      foreach ($item['#files'] as &$file) {
        $file['blazy'] = new BlazySettings($settings);
      }
      $attributes->setAttribute('data-b-lazy', TRUE);
      if (!empty($settings['is_preview'])) {
        $attributes->setAttribute('data-b-preview', TRUE);
      }
    }

    // Clone relevant keys since field wrapper is no longer in use.
    foreach (['attached', 'cache', 'object', 'third_party_settings'] as $key) {
      if (!empty($field["#$key"])) {
        $item["#$key"] = isset($item["#$key"]) ? NestedArray::mergeDeep($field["#$key"], $item["#$key"]) : $field["#$key"];
      }
    }
    // Keep original formatter configurations intact here for custom works.
    $item['#blazy'] = new BlazySettings(array_filter($settings));
    return $item;
  }

}
