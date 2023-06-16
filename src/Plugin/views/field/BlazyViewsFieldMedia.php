<?php

namespace Drupal\blazy\Plugin\views\field;

use Drupal\views\ResultRow;

/**
 * Defines a custom field that renders a preview of a media.
 *
 * @ViewsField("blazy_media")
 */
class BlazyViewsFieldMedia extends BlazyViewsFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $values->_entity;
    $settings = $this->mergedViewsSettings();

    // Due to minimal settings, assumed core fields are in use.
    $settings['image'] = 'field_media_image';

    $data['settings'] = $this->mergedSettings = $settings;
    $data['entity'] = $media;
    $data['delta'] = $values->index;
    $data['fallback'] = $media->label();

    // Pass results to \Drupal\blazy\BlazyEntity.
    return $this->blazyEntity->build($data);
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    return [
      'multimedia' => TRUE,
      'view_mode' => 'default',
    ] + parent::getPluginScopes();
  }

}
