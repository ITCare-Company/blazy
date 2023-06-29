<?php

namespace Drupal\blazy\Plugin\views\field;

use Drupal\media\Entity\Media;
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
    /** @var \Drupal\media\Entity\Media $entity */
    $entity = $values->_entity;

    if ($entity instanceof Media) {
      $settings = $this->mergedViewsSettings();

      // Due to minimal settings, assumed core fields are in use.
      $settings['image'] = 'field_media_image';

      $data['#entity'] = $entity;
      $data['settings'] = $this->mergedSettings = $settings;
      $data['delta'] = $values->index;
      $data['fallback'] = $entity->label();

      // Pass results to \Drupal\blazy\BlazyEntity.
      return $this->blazyEntity->build($data);
    }
    return [];
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
