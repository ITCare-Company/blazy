<?php

namespace Drupal\blazy;

use Drupal\blazy\Theme\Grid;

/**
 * Deprecated in blazy:8.x-2.9.
 *
 * @todo trigger error post slick_browser: 8.x-2.4.
 * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
 *   Drupal\blazy\Blazy::grid() or Drupal\blazy\BlazyManager::toGrid() instead.
 * @see https://www.drupal.org/node/3367304
 */
class BlazyGrid extends Grid {}
