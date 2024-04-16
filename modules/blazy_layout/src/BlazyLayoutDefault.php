<?php

namespace Drupal\blazy_layout;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines shared plugin default settings for field formatter and Views style.
 */
class BlazyLayoutDefault {

  /**
   * Defines region count.
   */
  const REGION_COUNT = 9;

  /**
   * Returns display style options, different from core Blazy for layouts.
   */
  public static function displayStyle() {
    return [
      'column' => 'CSS3 Columns',
      'grid' => 'Grid Foundation',
      'flexbox' => 'Flexbox',
      'nativegrid' => 'Native Grid',
    ];
  }

  /**
   * Returns the layout settings.
   */
  public static function layoutSettings() {
    return [
      'regions' => [],
      'count' => static::REGION_COUNT,
      'percentage' => '',
      'style' => 'nativegrid',
      'grid' => '4x4 4x3 2x2 2x4 2x2 2x3 2x3 4x2 4x2',
      'grid_medium' => '3',
      'grid_small' => '1',
      'grid_auto_rows' => '',
      'align_items' => '',
    ];
  }

  /**
   * Returns the region layout settings.
   */
  public static function regionSettings() {
    return [
      'attributes' => '',
      'name'       => '',
      // 'regions'         => [],
      // 'styles'          => [],
      // 'wrapper'         => '',
      // 'wrapper_classes' => '',
      // 'row_classes'     => '',
    ];
  }

  /**
   * Returns align items options.
   */
  public static function aligItems() {
    return [
      'normal' => 'normal',
      'stretch' => 'stretch',
      'center' => 'center',
      'start' => 'start',
      'end' => 'end',
      'flex-start' => 'flex-start',
      'flex-end' => 'flex-end',
      'self-start' => 'self-start',
      'self-end' => 'self-end',
      'baseline' => 'baseline',
      'first baseline' => 'first baseline',
      'last baseline' => 'last baseline',
      'safe center' => 'safe center',
      'unsafe center' => 'unsafe center',
      'inherit' => 'inherit',
      'initial' => 'initial',
      'revert' => 'revert',
      'revert-layer' => 'revert-layer',
      'unset' => 'unset',
    ];
  }

  /**
   * Returns region ID.
   */
  public static function regionId($id): string {
    return "blzyr_$id";
  }

  /**
   * Returns region label.
   */
  public static function regionLabel($id): string {
    return "Region $id";
  }

  /**
   * Returns region label.
   */
  public static function regionTranslatableLabel($label): TranslatableMarkup {
    return new TranslatableMarkup('@label', ['@label' => $label], [
      'context' => 'layout_region',
    ]);
    // Must follow LB convention without arguments.
    /* @phpstan-ignore-next-line */
    /*
    return new TranslatableMarkup("$label", [], [
    'context' => 'layout_region',
    ]);
     */
  }

}
