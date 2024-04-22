<?php

namespace Drupal\blazy_layout;

use Drupal\blazy\BlazyManagerInterface;

/**
 * Defines re-usable services and functions for BlazyLayoutManager.
 */
interface BlazyLayoutManagerInterface extends BlazyManagerInterface {

  /**
   * Returns the region array based on the region amount.
   *
   * @param int|null $count
   *   The amount of region, default to 9.
   *
   * @return array
   *   The region array.
   */
  public function getRegions($count = NULL): array;

  /**
   * Returns the element keys.
   *
   * @param array $elements
   *   The array elements.
   *
   * @return array
   *   The element keys.
   */
  public function getKeys(array $elements): array;

}
