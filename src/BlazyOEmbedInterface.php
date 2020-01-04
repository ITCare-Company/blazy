<?php

namespace Drupal\blazy;

use Drupal\media\OEmbed\Resource;

/**
 * Provides OEmbed integration.
 */
interface BlazyOEmbedInterface {

  /**
   * Returns the oEmbed Resource.
   *
   * @param string $input_url
   *   The video url.
   *
   * @return Drupal\media\OEmbed\Resource[]
   *   The oEmbed resource.
   */
  public function getResource($input_url);

  /**
   * Builds media-related settings based on the given media url.
   *
   * Need internet, else `Could not retrieve the oEmbed provider database from
   * //oembed.com/providers.json in Drupal\media\OEmbed\ProviderRepository.
   *
   * @param array $settings
   *   The settings array being modified.
   *
   * @return Drupal\media\OEmbed\Resource
   *   The oEmbed resource.
   */
  public function build(array &$settings = []);

  /**
   * Provides the autoplay url suitable for lightboxes, or custom video trigger.
   *
   * @param Drupal\media\OEmbed\Resource $resource
   *   The oEmbed resource.
   *
   * @return array
   *   The settings array.
   */
  public function getAutoPlayUrl(Resource $resource);

  /**
   * Gets the Media item thumbnail.
   *
   * @param array $data
   *   The modified array containing settings, and to be video thumbnail item.
   * @param object $media
   *   The core Media entity.
   */
  public function getMediaItem(array &$data = [], $media = NULL);

}
