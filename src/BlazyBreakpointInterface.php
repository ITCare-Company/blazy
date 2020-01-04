<?php

namespace Drupal\blazy;

/**
 * Defines methods for custom breakpoints.
 */
interface BlazyBreakpointInterface {

  /**
   * Checks if an image style contains crop effect.
   *
   * @param string $style
   *   The image style to check for.
   *
   * @return object|bool
   *   Returns the image style instance if it contains crop effect, else FALSE.
   */
  public static function isCrop($style);

  /**
   * Provides re-usable breakpoint data-attributes.
   *
   * These attributes can be applied to either IMG or DIV as CSS background.
   *
   * $settings['breakpoints'] must contain: xs, sm, md, lg breakpoints with
   * the expected keys: width, image_style.
   *
   * @param array $attributes
   *   The attributes being modified.
   * @param array $settings
   *   The given settings being modified.
   *
   * @see self::preprocessBlazy()
   */
  public static function buildBreakpointAttributes(array &$attributes, array &$settings = []);

  /**
   * Cleans up empty, or not so empty, breakpoints.
   *
   * @param array $settings
   *   The settings being modified.
   */
  public static function cleanUpBreakpoints(array &$settings = []);

  /**
   * Builds breakpoints suitable for top-level [data-blazy] wrapper attributes.
   *
   * The hustle is because we need to define dimensions once, if applicable, and
   * let all images inherit. Each breakpoint image may be cropped, or scaled
   * without a crop. To set dimensions once requires all breakpoint images
   * uniformly cropped. But that is not always the case.
   *
   * @param array $settings
   *   The settings being modified.
   * @param object $item
   *   The \Drupal\image\Plugin\Field\FieldType\ImageItem item.
   */
  public static function buildDataBlazy(array &$settings, $item = NULL);

}
