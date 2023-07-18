<?php

namespace Drupal\blazy\Plugin\views\field;

use Drupal\file\Entity\File;
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

    if ($entity instanceof File) {
      $settings = $this->mergedViewsSettings();

      $data['#entity']   = $entity;
      $data['#settings'] = $this->mergedSettings = $settings;
      $data['#delta']    = $values->index;
      $data['fallback']  = $entity->getFilename();

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
