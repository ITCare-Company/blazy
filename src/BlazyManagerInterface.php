<?php

namespace Drupal\blazy;

/**
 * Defines re-usable services and functions for blazy plugins.
 */
interface BlazyManagerInterface {

  /**
   * Gets the supported lightboxes.
   *
   * @return array
   *   The supported lightboxes.
   */
  public function getLightboxes();

  /**
   * Sets the lightboxes.
   *
   * @param string $lightbox
   *   The lightbox name, expected to be the module name.
   */
  public function setLightboxes($lightbox);

  /**
   * Cleans up empty, or not so empty, breakpoints.
   *
   * @param array $settings
   *   The settings being modified.
   */
  public function cleanUpBreakpoints(array &$settings = []);

  /**
   * Checks for Blazy formatter such as from within a Views style plugin.
   *
   * Ensures the settings traverse up to the container where Blazy is clueless.
   * The supported plugins can add [data-blazy] attribute into its container
   * containing $settings['blazy_data'] converted into [data-blazy] JSON.
   *
   * @param array $settings
   *   The settings being modified.
   * @param array $item
   *   The item containing settings or item keys.
   */
  public function isBlazy(array &$settings, array $item = []);

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
   * @param object|mixed $item
   *   The \Drupal\image\Plugin\Field\FieldType\ImageItem item, or array when
   *   dealing with Video Embed Field.
   */
  public function buildDataBlazy(array &$settings, $item = NULL);

  /**
   * Returns the Responsive image cache tags.
   *
   * @param object $responsive
   *   The responsive image style entity.
   *
   * @return array
   *   The responsive image cache tags, or empty array.
   */
  public function getResponsiveImageCacheTags($responsive);

}
