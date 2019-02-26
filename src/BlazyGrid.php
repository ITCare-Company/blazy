<?php

namespace Drupal\blazy;

use Drupal\Component\Serialization\Json;

/**
 * Provides grid utilities.
 */
class BlazyGrid {

  /**
   * Returns items as a grid display wrapped by theme_item_list().
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
    $blazy = empty($settings['blazy_data']) ? '' : Json::encode($settings['blazy_data']);
    $settings['style'] = empty($settings['style']) ? 'grid' : $settings['style'];

    $grids = [];
    foreach ($items as $item) {
      // Support non-Blazy which normally uses item_id.
      $attributes    = isset($item['attributes']) ? $item['attributes'] : [];
      $item_settings = isset($item['settings']) ? $item['settings'] : $settings;
      $item_settings = isset($item['#build']) && isset($item['#build']['settings']) ? $item['#build']['settings'] : $item_settings;
      unset($item['settings'], $item['attributes']);

      // Supports both single formatter field and complex fields such as Views.
      $grid['content'] = [
        '#theme'      => 'container',
        '#children'   => $item,
        '#attributes' => ['class' => ['grid__content']],
      ];

      foreach (['grid_item_class', 'type', 'media_switch'] as $key) {
        if (!empty($item_settings[$key])) {
          $attributes['class'][] = 'grid--' . str_replace('_', '-', $item_settings[$key]);
        }
      }

      $classes = isset($attributes['class']) ? $attributes['class'] : [];
      $attributes['class'] = array_merge(['grid'], $classes);
      $grid['#wrapper_attributes'] = $attributes;

      $grids[] = $grid;
      unset($grid);
    }

    $count = empty($settings['count']) ? count($grids) : $settings['count'];
    $element = [
      '#theme' => 'item_list',
      '#items' => $grids,
      '#context' => ['settings' => $settings],
      '#attributes' => [
        'class' => [
          'blazy',
          'blazy--grid',
          'block-' . $settings['style'],
          'block-count-' . $count,
        ],
        'data-blazy' => $blazy,
      ],
      '#wrapper_attributes' => [
        'class' => ['item-list--blazy', 'item-list--blazy-' . $settings['style']],
      ],
    ];

    if (!empty($settings['media_switch'])) {
      $switch = str_replace('_', '-', $settings['media_switch']);
      $element['#attributes']['data-' . $switch . '-gallery'] = TRUE;
    }

    $settings['grid_large'] = $settings['grid'];
    foreach (['small', 'medium', 'large'] as $grid) {
      if (!empty($settings['grid_' . $grid])) {
        $element['#attributes']['class'][] = $grid . '-block-' . $settings['style'] . '-' . $settings['grid_' . $grid];
      }
    }

    return $element;
  }

}
