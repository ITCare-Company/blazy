<?php

namespace Drupal\blazy_layout;

use Drupal\blazy\BlazyManager;
use Drupal\blazy_layout\BlazyLayoutDefault as Defaults;

/**
 * Provides BlazyLayoutManager utility.
 */
class BlazyLayoutManager extends BlazyManager implements BlazyLayoutManagerInterface {

  /**
   * {@inheritdoc}
   */
  protected static $namespace = 'blazy';

  /**
   * {@inheritdoc}
   */
  protected static $itemId = 'box';

  /**
   * {@inheritdoc}
   */
  protected static $itemPrefix = 'blazy';

  /**
   * {@inheritdoc}
   */
  public function getRegions($count = NULL): array {
    $regions = [];
    $count = $count ?: Defaults::REGION_COUNT;

    foreach (range(1, $count) as $delta => $value) {
      $id    = Defaults::regionId($delta);
      $label = Defaults::regionLabel($delta);

      $regions[$id]['id']    = $id;
      $regions[$id]['delta'] = $delta;
      $regions[$id]['label'] = Defaults::regionTranslatableLabel($label);
      $regions[$id]['name']  = $id;
    }

    return $regions;
  }

  /**
   * {@inheritdoc}
   */
  public function getKeys(array $elements): array {
    return array_keys(
      array_filter(
        $elements,
        fn($k) => strpos($k, '#') === FALSE,
        ARRAY_FILTER_USE_KEY
      )
    );
  }

}
