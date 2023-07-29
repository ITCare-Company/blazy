<?php

namespace Drupal\blazy\Media;

use Drupal\blazy\Blazy;

/**
 * Provides deprecated video embed field utility for easy removal.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class Vef {

  /**
   * Returns modified embed url from VEF.
   *
   * @todo remove at 3.x, and or after BVEF adopted BlazyVideoFormatter.
   */
  public static function toEmbedUrl(array &$settings, array $options, $oembed): string {
    $blazies   = $settings['blazies'];
    $embed_url = $options['embed_url'];
    $is_player = $options['is_player'];

    // VEF has no TITLE, nor ALT, for images provide them.
    $oembed->getThumbnail($settings);

    // For consistency and security, yet ensure to not mess up url.
    $input_url = $blazies->get('media.input_url');
    if ($input_url) {
      if (strpos($embed_url, '?url') === FALSE) {
        $autoplay  = $is_player ? ['autoplay' => 1] : [];
        $embed_url = $oembed->toEmbedUrl($blazies, $input_url, $autoplay);
      }
      elseif ($is_player) {
        $embed_url = Blazy::autoplay($embed_url);
      }
    }

    // The multimedia is defined for core Media, not VEF, so set it here.
    $blazies->set('is.multimedia', TRUE)
      ->set('media.input_url', $input_url)
      ->set('media.bundle', 'remote_video')
      ->set('media.source', 'video_embed_field');

    return $embed_url;
  }

}
