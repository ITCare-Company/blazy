<?php

/**
 * @file
 * Provides Unit tests boostrap.
 */

use Drupal\blazy\internals\Internals;

// Make global helper available to unit tests.
if (!function_exists('blazy')) {

  /**
   * Provides a dummy function for Unit tests.
   *
   * @return \Drupal\blazy\BlazyManagerInterface
   *   The blazy.manager service.
   */
  function blazy() {
    return Internals::service('blazy.manager');
  }

}
