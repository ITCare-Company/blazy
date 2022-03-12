<?php

namespace Drupal\blazy_test;

use Drupal\Core\Render\Element\RenderCallbackInterface;

/**
 * Provides a render callback to sets blazy_test related URL attributes.
 *
 * @see blazy_test_blazy_alter()
 * @see blazy_photoswipe_blazy_alter()
 */
class BlazyTestAlter implements RenderCallbackInterface {

  /**
   * The #pre_render callback: Sets lightbox image URL.
   */
  public static function preRender($image) {
    $settings = $image['#settings'];
    $blazies  = $settings['blazies'];

    // @todo remove settings.
    $embed   = $settings['embed_url'] ?? '';
    $box_url = $settings['box_url'] ?? '';
    $box_url = $blazies->get('box.url', $box_url);

    // Video's HREF points to external site, adds URL to local image.
    if ($box_url && $blazies->get('media.embed_url', $embed)) {
      $image['#url_attributes']['data-box-url'] = $box_url;
    }

    return $image;
  }

}
