<?php

namespace Drupal\blazy\Dejavu;

use Drupal\blazy\Views\BlazyStylePluginTrait as StylePluginTrait;

/**
 * Deprecated in blazy:8.x-2.14.
 *
 * Used by sub-modules.
 *
 * @todo enable post blazy:2.17.
 * @todo deprecated in blazy:8.x-2.14 and is removed from blazy:8.x-3.0. Use
 *   Drupal\blazy\Views\BlazyStylePluginBase instead.
 * @see https://www.drupal.org/node/3367304
 */
trait BlazyStylePluginTrait {

  use StylePluginTrait;

}
