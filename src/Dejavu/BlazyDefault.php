<?php

/**
 * @file
 * Contains \Drupal\blazy\Dejavu\BlazyDefault.
 */

namespace Drupal\blazy\Dejavu;

/**
 * Defines shared plugin default settings for field formatter and Views style.
 */
class BlazyDefault {

  /**
   * The supported $breakpoints.
   *
   * @const $breakpoints.
   */
  private static $breakpoints = ['xs', 'sm', 'md'];

  /**
   * Returns Blazy specific breakpoints.
   */
  public static function getConstantBreakpoints() {
    return self::$breakpoints;
  }

  /**
   * Returns basic plugin settings.
   */
  public static function baseSettings() {
    return [
      'cache'             => -1,
      'current_view_mode' => '',
      'optionset'         => 'default',
      'skin'              => '',
    ];
  }

  /**
   * Returns image-related field formatter and Views settings.
   */
  public static function imageSettings() {
    return [
      'background'             => FALSE,
      'box_style'              => '',
      'breakpoints'            => [],
      'caption'                => [],
      'image_style'            => '',
      'layout'                 => '',
      'media_switch'           => '',
      'ratio'                  => '',
      'retina'                 => '',
      'responsive_image_style' => '',
      'thumbnail_style'        => '',
    ] + self::baseSettings();
  }

  /**
   * Returns fieldable entity formatter and Views settings.
   */
  public static function extendedSettings() {
    return [
      'class'       => '',
      'dimension'   => '',
      'iframe_lazy' => FALSE,
      'image'       => '',
      'link'        => '',
      'overlay'     => '',
      'stamp'       => [],
      'title'       => '',
      'view_mode'   => '',
      'vanilla'     => FALSE,
    ] + self::imageSettings();
  }

}
