<?php

namespace Drupal\blazy\Theme;

use Drupal\blazy\Blazy;
use Drupal\blazy\Utility\Check;

/**
 * Provides grid utilities.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module ecosystem.
 */
class Grid {

  /**
   * Returns items wrapped by theme_item_list(), can be a grid, or plain list.
   *
   * @param array $items
   *   The grid items.
   * @param array $settings
   *   The given settings.
   *
   * @return array
   *   The modified array of grid items.
   */
  public static function build(array $items, array $settings): array {
    // Might be called outside the workflow like Slick/ Splide list builders.
    Blazy::verify($settings);

    // If the workflow is by-passed, by calling this directly, re-check grids.
    $blazies = $settings['blazies'];
    if (!$blazies->get('namespace')) {
      Check::grids($settings);
    }

    $style = $settings['style'];
    $is_grid = $blazies->is('grid');
    $item_class = $is_grid ? 'grid' : 'blazy__item';
    $contents = [];

    // Slick/ Splide may trick count to disable grid slides when lacking,
    // although not necessarily needed by flat grid like Blazy's.
    $count = $settings['count'] ?? count($items);
    $settings['count'] = $count = $blazies->get('count', $count);

    // Update for the rest.
    $blazies->set('count', $count);

    foreach ($items as $key => $item) {
      if (!is_array($item)) {
        continue;
      }

      // Support non-Blazy which normally uses item_id.
      // @todo remove the last two after migrations at 3.x.
      $wrapper_attrs = $item['#attributes'] ?? $item['attributes'] ?? [];
      $content_attrs = $item['#content_attributes'] ?? $item['content_attributes'] ?? [];
      $item_sets = $item['#settings'] ?? $item['settings'] ?? [];
      $sets = Blazy::merge($item_sets, $settings);
      $sets = Blazy::merge($item['#build']['settings'] ?? [], $sets);

      $blazy = $sets['blazies']->reset($sets);
      $sets['delta'] = $key;
      $blazy->set('delta', $key);

      // Supports both single formatter field and complex fields such as Views.
      $classes = (array) ($wrapper_attrs['class'] ?? []);
      $wrapper_attrs['class'] = array_merge([$item_class], $classes);

      self::itemAttributes($wrapper_attrs, $content_attrs, $sets);

      // Good for Bootstrap .well/ .card class, must cast or BS will reset.
      $classes = (array) ($content_attrs['class'] ?? []);
      $content_attrs['class'] = array_merge(['grid__content'], $classes);

      // Remove known unused array.
      // @todo remove after 3.x refactors to use hashes instead.
      unset($item['settings'], $item['attributes'], $item['content_attributes']);
      if (is_object($item['item'] ?? NULL)) {
        unset($item['item']);
      }

      $content['content'] = $is_grid ? [
        '#theme'      => 'container',
        '#children'   => $item,
        '#attributes' => $content_attrs,
      ] : $item;

      $content['#wrapper_attributes'] = $wrapper_attrs;
      $contents[] = $content;
    }

    $attrs = [];
    self::attributes($attrs, $settings);

    $wrapper = ['item-list--blazy', 'item-list--blazy-' . $style];
    $wrapper = $style ? $wrapper : ['item-list--blazy'];
    $wrapper = array_merge(['item-list'], $wrapper);

    return [
      '#theme'              => 'item_list',
      '#items'              => $contents,
      '#context'            => ['settings' => $settings],
      '#attributes'         => $attrs,
      '#wrapper_attributes' => ['class' => $wrapper],
      '#title'              => self::label($blazies),
    ];
  }

  /**
   * Provides reusable container attributes.
   */
  public static function attributes(array &$attributes, array $settings): void {
    $blazies    = $settings['blazies'];
    $gallery_id = $blazies->get('lightbox.gallery_id');
    $is_gallery = $blazies->is('gallery');
    $namespace  = $blazies->get('namespace');

    // Provides data-attributes to avoid conflict with original implementations.
    BlazyAttribute::container($attributes, $settings);

    // Provides gallery ID, although Colorbox works without it, others may not.
    // Uniqueness is not crucial as a gallery needs to work across entities.
    if ($id = $blazies->get('css.id')) {
      $id = $is_gallery && $gallery_id ? $gallery_id : $id;

      // Non-blazy may group galleries per slide like Splide or Slick.
      if ($namespace != 'blazy') {
        $id = $id . Blazy::getHtmlId('-');
      }
      $attributes['id'] = $id;
    }

    // Limit to grid only, so to be usable for plain list.
    if ($blazies->is('grid')) {
      self::containerAttributes($attributes, $settings, $blazies);
    }

    // Listens to hook_blazy_settings_alter for minor alters.
    if ($attrs_alter = ($blazies->get('grid.attributes') ?: [])) {
      $attributes = Blazy::merge($attrs_alter, $attributes);
    }

    $blazies->set('grid.attributes', $attributes);
  }

  /**
   * Checks if a grid expects a two-dimensional grid.
   */
  public static function isNativeGrid($grid): bool {
    return !empty($grid) && !is_numeric($grid);
  }

  /**
   * Checks if a grid uses a native grid, but expecting a masonry.
   */
  public static function isNativeGridAsMasonry(array $settings): bool {
    return !self::isNativeGrid($settings['grid'])
      && $settings['style'] == 'nativegrid';
  }

  /**
   * Extracts grid like: 4x4 4x3 2x2 2x4 2x2 2x3 2x3 4x2 4x2, or single 4x4.
   */
  public static function toDimensions($grid): array {
    $dimensions = [];
    if (self::isNativeGrid($grid)) {
      $values = array_map('trim', explode(" ", $grid));

      foreach ($values as $value) {
        $width = $value;
        $height = 0;

        // If multidimensional layout.
        if (Blazy::has($value, 'x')) {
          [$width, $height] = array_pad(array_map('trim', explode("x", $value, 2)), 2, NULL);
        }

        $dimensions[] = ['width' => (int) $width, 'height' => (int) $height];
      }
    }

    return $dimensions;
  }

  /**
   * Passes grid like: 4x4 4x3 2x2 2x4 2x2 2x3 2x3 4x2 4x2 to settings.
   */
  public static function toNativeGrid(array &$settings): void {
    if (empty($settings['grid'])) {
      return;
    }

    $blazies = $settings['blazies'];
    if ($settings['grid_large'] = $settings['grid']) {
      if (self::isNativeGridAsMasonry($settings)) {
        $blazies->set('libs.nativegrid__masonry', TRUE);
      }

      // If Native Grid style with numeric grid, assumed non-two-dimensional.
      foreach (['small', 'medium', 'large'] as $key) {
        $value = empty($settings['grid_' . $key]) ? NULL : $settings['grid_' . $key];
        if ($dimensions = self::toDimensions($value)) {
          $blazies->set('grid.' . $key . '_dimensions', $dimensions);
        }
      }
    }
  }

  /**
   * Limit to grid only, so to be usable for plain list.
   */
  private static function containerAttributes(array &$attributes, array $settings, $blazies): void {
    $style = $settings['style'] ?: 'grid';

    $format = 'blazy--grid block-%s block-count-%d';
    $attributes['class'][] = sprintf($format, $style, $blazies->get('count'));

    // If Native Grid style with numeric grid, assumed non-two-dimensional.
    if ($style == 'nativegrid') {
      $attributes['class'][] = self::isNativeGridAsMasonry($settings)
        ? 'is-b-masonry' : 'is-b-native';
    }

    // Adds common grid attributes for CSS3 column, Foundation, etc.
    // Only if using the plain grid column numbers (1 - 12).
    if ($settings['grid_large'] = $settings['grid']) {
      foreach (['small', 'medium', 'large'] as $key) {
        $value = $settings['grid_' . $key] ?? NULL;
        if ($value && is_numeric($value)) {
          $attributes['class'][] = $key . '-block-' . $style . '-' . $value;
        }
      }
    }
  }

  /**
   * Returns field label via Field UI, unless use.theme_field takes place.
   */
  private static function label($blazies): ?string {
    $title = '';
    $label = $blazies->get('field.label');
    if (!$blazies->use('theme_field')
      && $blazies->get('field.label_display') != 'hidden'
      && $label) {
      $title = $label;
    }
    return $title;
  }

  /**
   * Provides grid item attributes, relevant for Native Grid.
   */
  public static function itemAttributes(
    array &$attributes,
    array &$content_attributes,
    array $settings
  ): void {
    $blazies = $settings['blazies'];

    // Listens to hook_blazy_settings_alter for minor alters, such as adding
    // generic .card, etc. classes without extra legs.
    if ($attrs_alter = ($blazies->get('grid.item_attributes') ?: [])) {
      $attributes = Blazy::merge($attrs_alter, $attributes);
    }

    if ($content_attrs_alter = ($blazies->get('grid.item_content_attributes') ?: [])) {
      $content_attributes = Blazy::merge($content_attrs_alter, $content_attributes);
    }

    if ($dim = $blazies->get('grid.large_dimensions', [])) {
      $key = $blazies->get('delta');
      if (isset($dim[$key])) {
        $attributes['data-b-w'] = $dim[$key]['width'];
        if (!empty($dim[$key]['height'])) {
          $attributes['data-b-h'] = $dim[$key]['height'];
        }
      }
      else {
        // Supports a grid repeat for the lazy.
        $height = $dim[0]['height'];
        $width = $dim[0]['width'];
        if ($blazies->get('count') > count($dim) && !empty($width)) {
          $attributes['data-b-w'] = $width;
          if (!empty($height)) {
            $attributes['data-b-h'] = $height;
          }
        }
      }
    }

    $blazies->set('grid.item_attributes', $attributes);
    $blazies->get('grid.item_content_attributes', $content_attributes);
  }

}
