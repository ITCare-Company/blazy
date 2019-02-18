<?php

namespace Drupal\blazy\Dejavu;

/**
 * A Trait common for supported entities.
 *
 * This file can be imported along with Drupal\blazy\Dejavu\BlazyVideoTrait
 * to support File/ Media where available.
 *
 * @deprecated to be removed for BlazyEntity.
 */
trait BlazyEntityTrait {

  /**
   * Returns the string value of the fields: link, or text.
   *
   * @deprecated to be removed for BlazyEntity::getFieldString().
   */
  public function getFieldString($entity, $field_name, $langcode) {
    return '';
  }

  /**
   * Returns the formatted renderable array of the field.
   *
   * @deprecated to be remoived for BlazyEntity::getFieldRenderable().
   */
  public function getFieldRenderable($entity, $field_name, $view_mode) {
    return [];
  }

  /**
   * Build image/video preview either using theme_blazy(), or view builder.
   *
   * @deprecated to be remoived for BlazyEntity::build().
   */
  public function buildPreview(array $data, $entity, $fallback = '') {
    return [];
  }

}
