<?php

namespace Drupal\blazy_layout;

use Drupal\blazy\BlazyManagerInterface;

/**
 * Defines re-usable services and functions for BlazyLayout.
 */
interface BlazyLayoutInterface extends BlazyManagerInterface {

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

}
