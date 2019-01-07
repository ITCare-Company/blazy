<?php

namespace Drupal\blazy\Dejavu;

use Drupal\file\Entity\File;

/**
 * A Trait common for Media integration.
 *
 * @see Drupal\blazy\Plugin\views\field\BlazyViewsFieldPluginBase
 * @see Drupal\slick_browser\SlickBrowser::widgetEntityBrowserFileFormAlter()
 * @see Drupal\slick_browser\Plugin\EntityBrowser\FieldWidgetDisplay\...
 */
trait BlazyVideoTrait {

  /**
   * Core Media oEmbed url resolver.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory = NULL;

  /**
   * Returns the image factory.
   */
  public function imageFactory() {
    if (is_null($this->imageFactory)) {
      $this->imageFactory = \Drupal::service('image.factory');
    }
    return $this->imageFactory;
  }

  /**
   * Gets the faked image item out of file entity, or ER, if applicable.
   *
   * @param object $file
   *   The expected file entity, or ER, to get image item from.
   *
   * @return array
   *   The array of image item and settings if a file image, else empty.
   */
  public function getImageItem($file) {
    $data = [];
    $entity = $file;

    /** @var Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $file */
    if (isset($file->entity) && !isset($file->alt)) {
      $entity = $file->entity;
    }

    if (!$entity instanceof File) {
      return $data;
    }

    /** @var \Drupal\file\Entity\File $entity */
    list($type,) = explode('/', $entity->getMimeType(), 2);
    $uri = $entity->getFileUri();

    if ($type == 'image' && ($image = $this->imageFactory()->get($uri)) && $image->isValid()) {
      $item            = new \stdClass();
      $item->target_id = $entity->id();
      $item->width     = $image->getWidth();
      $item->height    = $image->getHeight();
      $item->alt       = $entity->getFilename();
      $item->title     = $entity->getFilename();
      $item->uri       = $uri;
      $settings        = (array) $item;
      $item->entity    = $entity;

      // Build item and settings.
      $settings['type'] = 'image';
      $settings['uri']  = $uri;
      $data['item']     = $item;
      $data['settings'] = $settings;
      unset($item);
    }

    return $data;
  }

  /**
   * Gets the Media item thumbnail, or re-associate the file entity to ME.
   *
   * @param array $data
   *   An array of data containing settings, and potential video thumbnail item.
   * @param object $media
   *   The core Media entity.
   *
   * @deprecated for BlazyOEmbed::getMediaItem().
   * @todo remove post Blazy 8.2.x when blazy-plugins use core Media.
   */
  public function getMediaItem(array &$data = [], $media = NULL) {
    // If this file is imported without DI, allows a fallback to not break it.
    if (isset($this->blazyOembed) && !is_null($this->blazyOembed)) {
      $this->blazyOembed->getMediaItem($data, $media);
      return;
    }

    \Drupal::service('blazy.oembed')->getMediaItem($data, $media);
  }

  /**
   * Builds relevant Media settings based on the given media url.
   *
   * @param array $settings
   *   An array of settings to be passed into theme_blazy().
   * @param string $external_url
   *   A video URL.
   *
   * @deprecated for BlazyOEmbed::build().
   * @todo remove post Blazy 8.2.x full release. This is still kept to
   * allow changing from video_embed_field into media field without breaking it,
   * and to allow transition from blazy-related modules to depend on media.
   */
  public function buildVideo(array &$settings = [], $external_url = '') {
    // If this file is imported without DI, allows a fallback to not break it.
    $settings['input_url'] = empty($settings['input_url']) ? $external_url : $settings['input_url'];
    if (isset($this->blazyOembed) && !is_null($this->blazyOembed)) {
      return $this->blazyOembed->build($settings);
    }
    return _blazy_build_oembed($settings);
  }

}
