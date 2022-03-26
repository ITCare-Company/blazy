<?php

namespace Drupal\blazy\Media;

/**
 * Provides placeholder thumbnail image.
 */
class Placeholder {

  /**
   * Defines constant placeholder Data URI image.
   */
  const DATA = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

  /**
   * Build out the blur image.
   *
   * Provides image effect if so configured unless being sandboxed.
   * Being a separated .b-blur with .b-lazy, this should work for any lazy.
   * Ensures at least a hook_alter is always respected. This still allows
   * Blur and hook_alter for Views rewrite issues, unless global UI is set
   * which was already warned about anyway.
   */
  public static function blur(array &$variables, array &$settings) {
    $attributes = &$variables['attributes'];
    $blazies = $settings['blazies'];

    if (!$blazies->get('blur.data')) {
      return;
    }

    $blur = [
      '#theme' => 'image',
      '#uri' => $blazies->get('placeholder.url'),
      '#attributes' => [
        'class' => ['b-lazy', 'b-blur', 'b-blur--tmp'],
        'data-src' => $blazies->get('blur.data'),
        'loading' => 'lazy',
        'decoding' => 'async',
      ],
    ];

    $width = (int) ($settings['width'] ?? 0);
    if ($width > 980) {
      $attributes['class'][] = 'media--fx-lg';
    }

    // Reset as already stored.
    $blazies->set('blur.data', '');
    $variables['preface']['blur'] = $blur;
  }

  /**
   * Generates an SVG Placeholder.
   *
   * @param string $width
   *   The image width.
   * @param string $height
   *   The image height.
   *
   * @return string
   *   Returns a string containing an SVG.
   */
  public static function generate($width, $height): string {
    $width = $width ?: 100;
    $height = $height ?: 100;
    return 'data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D\'http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg\'%20viewBox%3D\'0%200%20' . $width . '%20' . $height . '\'%2F%3E';
  }

  /**
   * Build thumbnails, also to provide placeholder for blur effect.
   */
  public static function prepare(array &$attributes, array &$settings) {
    $blazies = $settings['blazies'];
    $uri     = $settings['uri'] ?? '';
    $uri     = $uri ?: $blazies->get('uri');
    $tn_uri  = $settings['thumbnail_uri'] ?? $blazies->get('thumbnail.uri');
    $width   = $height = 1;
    $style   = NULL;
    $path    = $tn_url = '';

    // Supports unique thumbnail different from main image, such as logo for
    // thumbnail and main image for company profile.
    if ($tn_uri) {
      $path = $tn_uri;
      $tn_url = BlazyFile::transformRelative($path);
    }
    else {
      // This one uses the same non-unique image like the main stage image.
      if (!$blazies->is('external') && $style = $blazies->get('thumbnail.style')) {
        $path = $style->buildUri($uri);
        $tn_url = BlazyFile::transformRelative($uri, $style);

        [
          'width' => $width,
          'height' => $height,
        ] = BlazyImage::transformDimensions($style, $settings);
      }
    }

    // With CSS background, IMG may be empty, add thumbnail to the container.
    if ($tn_url) {
      $attributes['data-thumb'] = $tn_url;
      $blazies->set('thumbnail.url', $tn_url);

      if (BlazyFile::isValidUri($path)) {
        $blazies->set('thumbnail.uri', $path);

        if (!$blazies->get('thumbnail.checked')) {
          if ($style && !is_file($path)) {
            $style->createDerivative($uri, $path);
          }
          $blazies->set('thumbnail.checked', TRUE);
        }
      }
    }

    // @todo use the thumbnail size, not original ones, see: #3210759?
    $blazies->set('placeholder.width', $width)
      ->set('placeholder.height', $height);

    // Accepts configurable placeholder, alter, and fallback.
    $default = self::generate($width, $height);
    $placeholder = $blazies->get('ui.placeholder') ?: $default;
    $blazies->set('placeholder.url', $placeholder);

    // Provides image effect if so configured unless being sandboxed.
    // Being a separated .b-blur with .b-lazy, this should work for any lazy.
    // Slick/ Splide lazy loads won't work, needs Blazy to make animation.
    if ($blazies->is('blazy') && $fx = $blazies->get('fx')) {
      $attributes['class'][] = 'media--fx';
      $attributes['data-animation'] = $fx;

      if ($blazies->is('blur')) {
        // Ensures at least a hook_alter is always respected. This still allows
        // Blur and hook_alter for Views rewrite issues, unless global UI is set
        // which was already warned about anyway.
        self::dataImage($settings, $style, $path);
      }
    }

    if ($blazies->get('resimage.id')) {
      // Mimicks private _responsive_image_image_style_url, #3119527.
      BlazyResponsiveImage::fallback($settings, $placeholder);
    }
  }

  /**
   * Build thumbnails, also to provide placeholder for blur effect.
   */
  private static function dataImage(array &$settings, $style = NULL, $path = ''): string {
    $blur = '';
    $uri = $settings['uri'];
    $blazies = $settings['blazies'];

    // Provides default path, in case required by global, but not provided.
    $style = $style ?: \blazy()->entityLoad('thumbnail', 'image_style');
    if (empty($path) && $style && BlazyFile::isValidUri($uri)) {
      $path = $style->buildUri($uri);
    }

    if (BlazyFile::isValidUri($path)) {
      // Ensures the thumbnail exists before creating a dataURI.
      if (!is_file($path) && $style) {
        $style->createDerivative($uri, $path);
      }

      // Overrides placeholder with data URI based on configured thumbnail.
      if (is_file($path) && $content = file_get_contents($path)) {
        $blur = 'data:image/' . pathinfo($path, PATHINFO_EXTENSION) . ';base64,' . base64_encode($content);

        // Prevents double animations.
        $blazies->set('blur.data', $blur);
        $blazies->set('use.loader', FALSE);
      }
    }
    return $blur;
  }

}
