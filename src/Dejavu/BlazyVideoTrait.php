<?php

/**
 * @file
 * Contains \Drupal\blazy\Dejavu\BlazyVideoTrait.
 */

namespace Drupal\blazy\Dejavu;

use Drupal\Core\Url;

/**
 * A Trait common for Video embed field integration.
 */
trait BlazyVideoTrait {

  /**
   * {@inheritdoc}
   */
  public function buildVideo(array &$settings = [], $media_url) {
    /** @var \Drupal\video_embed_field\ProviderManagerInterface $provider */
    $provider    = $this->providerManager->loadProviderFromInput($media_url);
    $definitions = $this->providerManager->loadDefinitionFromInput($media_url);

    // @todo extract URL from the SRC of final rendered TWIG instead.
    $render = $provider->renderEmbedCode(640, 360, '0');
    $query  = $render['#query'];

    // Prevents complication by now.
    unset($query['autoplay'], $query['auto_play']);

    // No file API with unmanaged VEF image without image_style.
    if (empty($settings['image_style']) && !empty($settings['image_url'])) {
      list($settings['width'], $settings['height']) = getimagesize($settings['image_url']);
    }

    $settings['url']           = Url::fromUri($render['#url'], ['query' => $query])->toString();
    $settings['scheme']        = $definitions['id'];
    $settings['thumbnail_uri'] = $provider->getLocalThumbnailUri();
    $settings['type']          = 'video';
    $settings['video_id']      = $provider::getIdFromInput($media_url);
  }

}
