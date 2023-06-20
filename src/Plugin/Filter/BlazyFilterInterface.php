<?php

namespace Drupal\blazy\Plugin\Filter;

/**
 * Defines re-usable services and functions for blazy plugins.
 */
interface BlazyFilterInterface {

  /**
   * Returns the main settings.
   *
   * @param string $text
   *   The provided text.
   *
   * @return array
   *   The main settings for current filter.
   */
  public function buildSettings($text);

}
