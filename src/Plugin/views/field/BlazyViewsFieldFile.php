<?php

namespace Drupal\blazy\Plugin\views\field;

use Drupal\views\ResultRow;

/**
 * Defines a custom field that renders a preview of a file.
 *
 * @ViewsField("blazy_file")
 */
class BlazyViewsFieldFile extends BlazyViewsFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\file\Entity\File $entity */
    $entity = $values->_entity;
    $settings = $this->mergedViewsSettings();
    $blazies = $settings['blazies'];
    $settings['delta'] = $delta = $values->index;

    $blazies->set('delta', $delta);
    $data['settings'] = $this->mergedSettings = $settings;

    // Pass results to \Drupal\blazy\BlazyEntity.
    return $this->blazyEntity->build($data, $entity, $entity->getFilename());
  }

  /**
   * Defines the scope for the form elements.
   */
  public function getScopedFormElements() {
    return ['multimedia' => TRUE, 'view_mode' => 'default']
      + parent::getScopedFormElements();
  }

}
