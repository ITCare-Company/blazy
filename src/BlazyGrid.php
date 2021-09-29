<?php

namespace Drupal\blazy;

/**
 * Provides grid utilities.
 */
class BlazyGrid {

  /**
   * Returns items wrapped by theme_item_list(), can be a grid, or plain list.
   *
   * @param array $items
   *   The grid items being modified.
   * @param array $settings
   *   The given settings.
   *
   * @return array
   *   The modified array of grid items.
   */
  public static function build(array $items = [], array $settings = []) {
    $settings += BlazyDefault::htmlSettings();
    $style = empty($settings['style']) ? '' : $settings['style'];
    $is_grid = isset($settings['_grid']) ? $settings['_grid'] : (!empty($settings['style']) && !empty($settings['grid']));
    $class_item = $is_grid ? 'grid' : 'blazy__item';
    $settings['count'] = empty($settings['count']) ? count($items) : $settings['count'];

    $contents = [];
    foreach ($items as $key => $item) {
      // Support non-Blazy which normally uses item_id.
      $attributes    = isset($item['attributes']) ? $item['attributes'] : [];
      $item_settings = isset($item['settings']) ? $item['settings'] : $settings;
      $item_settings = isset($item['#build']) && isset($item['#build']['settings']) ? $item['#build']['settings'] : $item_settings;

      unset($item['settings'], $item['attributes']);
      if (isset($item['item']) && is_object($item['item'])) {
        unset($item['item']);
      }

      // Good for Bootstrap .well/ .card class, must cast or BS will reset.
      $classes = empty($item_settings['grid_content_class']) ? [] : (array) $item_settings['grid_content_class'];
      $item_settings['delta'] = $key;

      // Supports both single formatter field and complex fields such as Views.
      $content['content'] = $is_grid ? [
        '#theme'      => 'container',
        '#children'   => $item,
        '#attributes' => ['class' => array_merge(['grid__content'], $classes)],
      ] : $item;

      if (!empty($item_settings['grid_item_class'])) {
        $attributes['class'][] = $item_settings['grid_item_class'];
      }

      $classes = isset($attributes['class']) ? $attributes['class'] : [];
      $attributes['class'] = array_merge([$class_item], $classes);

      // Provides grid item attributes.
      self::gridItemAttributes($attributes, $item_settings);

      $content['#wrapper_attributes'] = $attributes;

      $contents[] = $content;
    }

    $wrapper = ['item-list--blazy', 'item-list--blazy-' . $style];
    $wrapper = $style ? $wrapper : ['item-list--blazy'];
    $wrapper = array_merge(['item-list'], $wrapper);
    $element = [
      '#theme'              => 'item_list',
      '#items'              => $contents,
      '#context'            => ['settings' => $settings],
      '#attributes'         => [],
      '#wrapper_attributes' => ['class' => $wrapper],
    ];

    // Supports field label via Field UI, unless use_field takes place.
    if (empty($settings['use_field']) && isset($settings['label'], $settings['label_display']) && $settings['label_display'] != 'hidden') {
      $element['#title'] = $settings['label'];
    }

    self::attributes($element['#attributes'], $settings);
    return $element;
  }

  /**
   * Provides reusable container attributes.
   */
  public static function attributes(array &$attributes, array $settings = []) {
    $is_gallery = !empty($settings['lightbox']) && !empty($settings['gallery_id']);

    // Provides data-attributes to avoid conflict with original implementations.
    Blazy::containerAttributes($attributes, $settings);

    // Provides gallery ID, although Colorbox works without it, others may not.
    // Uniqueness is not crucial as a gallery needs to work across entities.
    if (!empty($settings['id'])) {
      $attributes['id'] = $is_gallery ? $settings['gallery_id'] : $settings['id'];
    }

    // Provides grid container attributes.
    self::gridContainerAttributes($attributes, $settings);
  }

  /**
   * Limit to grid only, so to be usable for plain list.
   */
  public static function gridContainerAttributes(array &$attributes, array $settings = []) {
    $style = empty($settings['style']) ? '' : $settings['style'];
    $is_grid = isset($settings['_grid']) ? $settings['_grid'] : ($style && !empty($settings['grid']));

    if ($is_grid) {
      $attributes['class'][] = 'blazy--grid block-' . $style . ' block-count-' . $settings['count'];

      // If Native Grid style with numeric grid, assumed non-two-dimensional.
      if ($style == 'nativegrid') {
        $attributes['class'][] = empty($settings['nativegrid.masonry']) ? 'is-b-native' : 'is-b-masonry';
      }

      // Adds common grid attributes for CSS3 column, Foundation, etc.
      // Only if using the plain grid column numbers (1 - 12).
      if ($settings['grid_large'] = $settings['grid']) {
        foreach (['small', 'medium', 'large'] as $key) {
          $value = empty($settings['grid_' . $key]) ? NULL : $settings['grid_' . $key];
          if ($value && is_numeric($value)) {
            $attributes['class'][] = $key . '-block-' . $style . '-' . $value;
          }
        }
      }
    }
  }

  /**
   * LProvides grid item attributes, relevant for Native Grid.
   */
  public static function gridItemAttributes(array &$attributes, array $settings = []) {
    if (isset($settings['grid_large_dimensions']) && $dim = $settings['grid_large_dimensions']) {
      $key = $settings['delta'];
      if (isset($dim[$key])) {
        $attributes['data-b-w'] = $dim[$key]['width'];
        if (!empty($dim[$key]['height'])) {
          $attributes['data-b-h'] = $dim[$key]['height'];
        }
      }
      else {
        // Supports a grid repeat for the lazy.
        $count = empty($settings['count']) ? 0 : $settings['count'];
        $height = $dim[0]['height'];
        if ($count > count($dim)) {
          $attributes['data-b-w'] = $dim[0]['width'];
          if (!empty($height)) {
            $attributes['data-b-h'] = $height;
          }
        }
      }
    }
  }

  /**
   * Checks if a grid expects a two-dimensional grid.
   */
  public static function isNativeGrid($grid) {
    return !empty($grid) && !is_numeric($grid);
  }

  /**
   * Checks if a grid uses a native grid, but expecting a masonry.
   */
  public static function isNativeGridAsMasonry(array $settings = []) {
    $style = empty($settings['style']) ? '' : $settings['style'];
    $grid = empty($settings['grid']) ? NULL : $settings['grid'];
    return $grid && is_numeric($grid) && $style == 'nativegrid';
  }

  /**
   * Extracts grid like: 4x4 4x3 2x2 2x4 2x2 2x3 2x3 4x2 4x2.
   */
  public static function toDimensions($grid) {
    $dimensions = [];
    if (self::isNativeGrid($grid)) {
      $values = array_map('trim', explode(" ", $grid));
      foreach ($values as $value) {
        $width = $value;
        $height = 0;
        if (strpos($value, 'x') !== FALSE) {
          list($width, $height) = array_pad(array_map('trim', explode("x", $value, 2)), 2, NULL);
        }

        $dimensions[] = ['width' => $width, 'height' => $height];
      }
    }

    return $dimensions;
  }

  /**
   * Passes grid like: 4x4 4x3 2x2 2x4 2x2 2x3 2x3 4x2 4x2 to settings.
   */
  public static function toNativeGrid(array &$settings = []) {
    if ($settings['grid_large'] = $settings['grid']) {
      // If Native Grid style with numeric grid, assumed non-two-dimensional.
      if (self::isNativeGridAsMasonry($settings)) {
        $settings['nativegrid.masonry'] = TRUE;
      }

      foreach (['small', 'medium', 'large'] as $key) {
        $value = empty($settings['grid_' . $key]) ? NULL : $settings['grid_' . $key];
        if ($dimensions = self::toDimensions($value)) {
          $settings['grid_' . $key . '_dimensions'] = $dimensions;
        }
      }
    }
  }

}
