<?php

namespace Drupal\blazy;

use Drupal\Component\Serialization\Json;

/**
 * Provides grid utilities.
 */
class BlazyGrid {

  /**
   * Returns items as a grid display.
   */
  public static function buildGrid($items = [], $settings = []) {
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
   * Returns a grid item.
   */
  public static function buildGridItemAttributes(array &$grid = [], $settings = []) {
    $grid['#wrapper_attributes']['class'][] = 'grid';

    if (!empty($settings['type'])) {
      $grid['#wrapper_attributes']['class'][] = 'grid--' . $settings['type'];
    }

    if (!empty($settings['media_switch'])) {
      $grid['#wrapper_attributes']['class'][] = 'grid--' . $settings['media_switch'];
      if (strpos($settings['media_switch'], 'box') !== FALSE) {
        $grid['#wrapper_attributes']['class'][] = 'grid--litebox';
      }
    }

    $grid['#wrapper_attributes']['class'][] = 'grid--' . $settings['delta'];

    if ($settings['delta'] == 0) {
      $grid['#settings'] = array_filter($settings);
    }
  }

}
