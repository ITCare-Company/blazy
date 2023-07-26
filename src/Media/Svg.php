<?php

namespace Drupal\blazy\Media;

/**
 * Provides Svg utility.
 */
class Svg {

  /**
   * Provides svg dimensions, if any.
   */
  public static function dimensions(array &$settings, $uri): void {
    $blazies = $settings['blazies'];
    $fluid   = $blazies->is('fluid');
    $valid   = BlazyFile::isValidUri($uri) && $blazies->is('svg');

    if (!$valid) {
      return;
    }

    // Checks SVG dimensions, if any.
    $blazies->set('image.fluid', NULL);
    if ($blazies->use('svg_dimensions')) {
      $svg = simplexml_load_file($uri);

      if ($svg && isset($svg['viewBox'])) {
        [,, $width, $height] = array_map('trim', explode(' ', $svg['viewBox']));

        if ($width && $height) {
          $blazies->set('image.width', ceil($width))
            ->set('image.height', ceil($height));

          // Image styles might be left empty, and aspect ratio is used.
          if ($fluid) {
            $dims = ['width' => $width, 'height' => $height];
            $dims['ratios'] = $blazies->get('css.ratio');

            // The result is normally used for non-inline style, via CSS rules.
            $data = Ratio::fluid($dims);
            $blazies->set('image.fluid', $data);
          }
        }
      }
    }
    else {
      $blazies->set('image.width', NULL)
        ->set('image.height', NULL);
    }
  }

}
