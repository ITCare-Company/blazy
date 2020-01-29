<?php

namespace Drupal\blazy\Plugin\views\field;

use Drupal\views\ResultRow;

/**
 * Defines a custom field that renders a preview of a file.
 *
 * @ViewsField("blazy_file")
 *
 * @todo TBD; deprecated in blazy:8.x-2.0 and is removed from blazy:8.x-3.0.
 *   Use \Drupal\blazy\Plugin\views\field\BlazyViewsFieldMedia::getMediaItem()
 *   instead.
 * @todo remove anything file entity for pure Media post blazy:8.x-3.0.
 */
class BlazyViewsFieldFile extends BlazyViewsFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\file\Entity\File $entity */
    $entity   = $values->_entity;
    $settings = $this->mergedViewsSettings();

    $settings['delta'] = $values->index;

    $data = $this->blazyEntity->oembed()->getImageItem($entity);
    $data['settings'] = isset($data['settings']) ? array_merge($settings, $data['settings']) : $settings;

    // Pass results to \Drupal\blazy\BlazyEntity.
    return $this->blazyEntity->build($data, $entity, $entity->getFilename());
  }

  /**
   * Defines the scope for the form elements.
   */
  public function getScopedFormElements() {
    return ['multimedia' => TRUE, 'view_mode' => 'default'] + parent::getScopedFormElements();
  }

}
