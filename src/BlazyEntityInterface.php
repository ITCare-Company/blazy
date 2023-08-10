<?php

namespace Drupal\blazy;

/**
 * Provides common entity utilities to work with field details.
 *
 * This is alternative to Drupal\blazy\BlazyFormatter used outside
 * field managers, such as Views field, or Slick/Entity Browser displays, etc.
 *
 * @see Drupal\blazy\Field\BlazyEntityReferenceBase
 * @see Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatterBase
 */
interface BlazyEntityInterface {

  /**
   * Returns the blazy oembed service.
   *
   * @return \Drupal\blazy\Media\BlazyOEmbedInterface
   *   The blazy oembed.
   */
  public function oembed();

  /**
   * Returns the blazy manager service.
   *
   * @return \Drupal\blazy\BlazyManagerInterface
   *   The blazy manager.
   */
  public function blazyManager();

  /**
   * Returns the blazy media.
   *
   * @return \Drupal\blazy\Media\BlazyMediaInterface
   *   The blazy media.
   */
  public function blazyMedia();

  /**
   * Build image/video preview either using theme_blazy(), or view builder.
   *
   * @param array $data
   *   The data containing:
   *     - #access, if already checked upstream, otherwise leave it undefined.
   *     - #entity, media, file entity, etc. to be associated to media.
   *     - #item, the ImageItem or fake one for video/audio cover, etc.
   *     - #settings, with view_mode, and anything else to work with, depending
   *       whether to have vanilla, or selective/ fieldable renderable array.
   *     - fallback, when all fails, probably just entity label.
   *
   * @return array
   *   The renderable array of theme_blazy(), or view builder, else empty array.
   */
  public function build(array $data): array;

  /**
   * Prepare entity once.
   *
   * This class was not designed to deal with multiple entities, but one.
   * Call this method once at the container level for multiple entities.
   *
   * @param array $data
   *   An array of data containing settings, image item, entity, and fallback.
   */
  public function prepare(array &$data): void;

  /**
   * Provides an entity.get.view output, or vanilla entity view.
   *
   * @param array $data
   *   The data containing:
   *     - #access, if already checked upstream, otherwise leave it undefined.
   *     - #entity, media or file entity, to be associated to media, or any.
   *     - #settings, with view_mode, and any/nothing else.
   *     - fallback, when all fails, probably just entity label.
   *
   * @return array
   *   The renderable array of the view builder, or empty if not applicable.
   */
  public function view(array $data): array;

}
