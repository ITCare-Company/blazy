<?php

namespace Drupal\blazy\Media;

use Drupal\media\MediaInterface;
use Drupal\blazy\BlazyManagerInterface;
use GuzzleHttp\Client;

/**
 * Provides extra utilities to work with core Media.
 */
interface BlazyMediaInterface {

  /**
   * Returns the http client service.
   *
   * @return \GuzzleHttp\Client
   *   The http client.
   */
  public function httpClient(): Client;

  /**
   * Returns the blazy manager service.
   *
   * @return \Drupal\blazy\BlazyManagerInterface
   *   The blazy manager.
   */
  public function manager(): BlazyManagerInterface;

  /**
   * Returns the media field which is partly not understood by theme_blazy().
   *
   * When this output arrives at theme_blazy() as content property, Blazy can no
   * longer work with it. That's why we need to do a relatively similar routine
   * to BlazyManager::preRenderBlazy(), only to a bare mimimum.
   *
   * @param array $build
   *   The array containing:
   *     - #entity the Media entity.
   *     - #settings array.
   *
   * @return array
   *   The renderable array of the media field, or empty if not applicable.
   */
  public function view(array $build): array;

  /**
   * Returns a media entity from a field name.
   *
   * @param object $entity
   *   The entity.
   * @param string $field_name
   *   The field_name to query by.
   */
  public function fromField($entity, $field_name): ?object;

  /**
   * Prepares media item data to provide image item.
   *
   * @param array $data
   *   The array containing:
   *     - #entity the Media entity.
   *     - #settings array, etc.
   */
  public function prepare(array &$data): MediaInterface;

  /**
   * Converts input URL into embed URL.
   *
   * @param string $input
   *   The input to modify.
   * @param string $iframe_domain
   *   The iframe_domain from media.settings.
   * @param array $autoplay
   *   The optional autoplay.
   *
   * @return string
   *   The media oembed url.
   */
  public function toEmbedUrl($input, $iframe_domain, array $autoplay = []): string;

}
