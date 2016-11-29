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
  public static function build($items = [], $settings = []) {
    $grids = [];
    foreach ($items as $delta => $item) {
      // @todo support non-Blazy which normally uses item_id.
      $item_settings = isset($item['#build']['settings']) ? $item['#build']['settings'] : $settings;
      $item_settings['delta'] = $delta;

      // Supports both single formatter field and complex fields such as Views.
      $grid = [];
      $grid['content'] = [
        '#theme'      => 'container',
        '#children'   => $item,
        '#attributes' => ['class' => ['grid__content']],
      ];

      self::buildGridItemAttributes($grid, $item_settings);

      $grids[] = $grid;
    }

    $count = empty($settings['count']) ? count($grids) : $settings['count'];
    $blazy = empty($settings['blazy_data']) ? '' : $settings['blazy_data'];
    $element = [
      '#theme' => 'item_list',
      '#items' => $grids,
      '#attributes' => [
       'class' => [
         'blazy',
         'blazy--grid',
         'block-' . $settings['style'],
         'block-count-' . $count,
        ],
        'data-blazy' => Json::encode($blazy),
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

  /**
   * Modifies the grid item wrapper attributes.
   *
   * @param array $grid
   *   The grid item being modified.
   * @param array $settings
   *   The given settings.
   */
  public static function buildGridItemAttributes(array &$grid = [], $settings = []) {
    $grid['#wrapper_attributes']['class'][] = 'grid';

    if (!empty($settings['type'])) {
      $grid['#wrapper_attributes']['class'][] = 'grid--' . $settings['type'];
    }

    if (!empty($settings['media_switch'])) {
      $grid['#wrapper_attributes']['class'][] = 'grid--' . str_replace('_', '-', $settings['media_switch']);
    }

    $grid['#wrapper_attributes']['class'][] = 'grid--' . $settings['delta'];

    // Adds settings accessible from the first item only.
    if ($settings['delta'] == 0) {
      $grid['#settings'] = array_filter($settings);
    }
  }

}
