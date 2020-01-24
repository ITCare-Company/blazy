<?php

namespace Drupal\blazy;

/**
 * Defines re-usable services and functions for blazy plugins.
 */
interface BlazyInterface {

  /**
   * Defines constant placeholder Data URI image.
   */
  const PLACEHOLDER = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

  /**
   * Modifies variables for responsive image.
   *
   * Responsive images with height and width save a lot of calls to
   * image.factory service for every image and breakpoint in
   * _responsive_image_build_source_attributes(). Very necessary for
   * external file system like Amazon S3.
   *
   * @param array $settings
   *   The settings being modified.
   *
   * @return array
   *   The responsive_image render elements.
   */
  public static function buildResponsiveImage(array &$settings);

  /**
   * Returns common iframe attributes, including those not handled by blazy.
   *
   * @param array $settings
   *   The given settings.
   *
   * @return array
   *   The iframe attributes.
   */
  public static function iframeAttributes(array $settings);

  /**
   * Modifies variables for iframes, those only handled by theme_blazy().
   *
   * Prepares a media player, and allows a tiny video preview without iframe.
   * image : If iframe switch disabled, fallback to iframe, remove image.
   * player: If no colorbox/photobox, it is an image to iframe switcher.
   * data- : Gets consistent with colorbox to share JS manipulation.
   *
   * @param array $variables
   *   The variables being modified.
   */
  public static function buildIframe(array &$variables);

  /**
   * Defines attributes, builtin, or supported lazyload such as Slick.
   *
   * These attributes can be applied to either IMG or DIV as CSS background.
   * The [data-(src|lazy)] attributes are applivable for (Responsive) image.
   * While [data-src] is reserved by Blazy, [data-lazy] by Slick.
   *
   * @param array $attributes
   *   The attributes being modified.
   * @param array $settings
   *   The given settings.
   */
  public static function lazyAttributes(array &$attributes, array $settings = []);

  /**
   * Builds URLs, cache tags, and dimensions for an individual image.
   *
   * Respects a few scenarios:
   * 1. Blazy Filter or unmanaged file with/ without valid URI.
   * 2. Hand-coded image_url with/ without valid URI.
   * 3. Respects first_uri without image_url such as colorbox/zoom-like.
   * 4. File API via field formatters or Views fields/ styles with valid URI.
   * If we have a valid URI, provides the correct image URL.
   * Otherwise leave it as is, likely hotlinking to external/ sister sites.
   * Hence URI validity is not crucial in regards to anything but #4.
   * The image will fail silently at any rate given non-expected URI.
   *
   * @param array $settings
   *   The given settings being modified.
   * @param object $item
   *   The image item.
   */
  public static function urlAndDimensions(array &$settings, $item = NULL);

}
