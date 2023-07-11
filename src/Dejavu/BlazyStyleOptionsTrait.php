<?php

namespace Drupal\blazy\Dejavu;

use Drupal\blazy\Views\BlazyStyleOptionsTrait as StyleOptionsTrait;

/**
 * Deprecated in blazy:8.x-2.14.
 *
 * Used by sub-modules.
 *
 * @todo deprecated in blazy:8.x-2.14 and is removed from blazy:8.x-3.0. Use
 *   Drupal\blazy\Views\StyleOptionsTrait instead.
 * @see https://www.drupal.org/node/3367304
 */
trait BlazyStyleOptionsTrait {

  use StyleOptionsTrait;

}
