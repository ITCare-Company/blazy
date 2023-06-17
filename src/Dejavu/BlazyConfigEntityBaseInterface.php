<?php

namespace Drupal\blazy\Dejavu;

use Drupal\blazy\Config\Entity\BlazyConfigEntityBaseInterface as ConfigEntityInterface;

/**
 * Provides a common config entity for Slick, Splide, ElevateZoomPLus, etc.
 *
 * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:8.x-3.0. Use
 *   Drupal\blazy\Config\Entity\BlazyConfigEntityBaseInterface instead.
 * @see https://www.drupal.org/node/3367304
 */
interface BlazyConfigEntityBaseInterface extends ConfigEntityInterface {}
