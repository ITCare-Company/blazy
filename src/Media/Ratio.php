<?php

namespace Drupal\blazy\Media;

use Drupal\blazy\BlazyDefault;

/**
 * Provides aspect ratio insanity.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class Ratio {

  /**
   * Returns whether aspect ratio padding hack applicable, or not.
   *
   * Prevents double padding hacks with AMP which also uses similar technique.
   */
  public static function hack(array $settings): array {
    $blazies  = $settings['blazies'];
    $disabled = $blazies->is('amp');
    $fluid    = $blazies->is('fluid');
    $unstyled = $blazies->is('svg');
    $ratio    = $disabled ? '' : $settings['ratio'] ?? NULL;
    $hack     = $ratio && $fluid;
    $resimage = $blazies->get('resimage.id');

    // Skip padding hacks if fluid is supported by plain CSS, to avoid JS.
    if ($hack) {
      $check = $blazies->get('image.fluid');

      // @todo recheck against esponsive image, gif, apng, alike.
      if (!$check && $unstyled) {
        $ratio = NULL;
        $hack = FALSE;
      }
      // Do not mess up with responsive image for now, or you'll be sorry.
      elseif ($check && !$resimage) {
        $ratio = $check;
        $hack = FALSE;
      }
    }
    return ['ratio' => $ratio, 'hack' => $hack];
  }

  /**
   * Provides a computed image ratio aka fluid ratio.
   *
   * Addresses multi-image-style Responsive image or, plain old one.
   * A failsafe for BG, else collapsed.
   *
   * @todo decide if to provide NULL or 0 instead.
   * @todo converts to blazies at/by 3.x.
   */
  public static function compute(array $data) {
    $no_dims = empty($data['height']) || empty($data['width']);
    return $no_dims ? 0 : round((($data['height'] / $data['width']) * 100), 2);
  }

  /**
   * Provides a computed image ratio aka fluid ratio.
   */
  public static function fluid(array $data, $force = FALSE): ?string {
    $width  = $data['width'];
    $height = $data['height'];
    $ratios = $data['ratios'] ?? BlazyDefault::RATIO;
    $output = NULL;

    if (empty($width) || empty($height)) {
      return $output;
    }

    $width  = (int) $width;
    $height = (int) $height;

    try {
      $check  = self::toRatio($width, $height);
      $result = ($width / $check) . ':' . ($height / $check);

      if (in_array($result, $ratios) || $force) {
        $output = $result;
      }
    }
    catch (\DivisionByZeroError $e) {
      // Do nothing, optional features should not mess up the rest.
    }
    catch (\Exception $e) {
      // Do nothing also.
    }

    return $output;
  }

  /**
   * Provides a computed image ratio aka fluid ratio.
   */
  private static function toRatio($width, $height) {
    if ($width == 0 || $height == 0) {
      return abs(max(abs($width), abs($height)));
    }

    $result = $width % $height;
    return ($result != 0) ? self::toRatio($height, $result) : abs($height);
  }

}
