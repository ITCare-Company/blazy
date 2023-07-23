<?php

namespace Drupal\blazy\Deprecated;

use Drupal\blazy\Field\BlazyField;

/**
 * Deprecated in blazy:8.x-2.9.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 *
 * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
 *   \Drupal\blazy\Field\BlazyField methods instead.
 * @see https://www.drupal.org/node/3103018
 */
trait BlazyEntityDeprecatedTrait {

  /**
   * Deprecated method to return the entity renderable array.
   *
   * No more called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   self::view() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getEntityView($entity, array $settings = [], $fallback = '') {
    $data = [
      '#entity'   => $entity,
      '#settings' => $settings,
      'fallback'  => $fallback,
    ];
    return $this->view($data);
  }

  /**
   * Deprecated method to return the field renderable array.
   *
   * No more called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::view() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getFieldRenderable($entity, $field_name, $view_mode, $multiple = TRUE) {
    return BlazyField::view($entity, $field_name, $view_mode, $multiple);
  }

  /**
   * Deprecated method to return the string value of link, or text.
   *
   * No more called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::getString() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getFieldString($entity, $field_name, $langcode, $clean = TRUE) {
    return BlazyField::getString($entity, $field_name, $langcode, $clean);
  }

  /**
   * Deprecated method to return the text or link value.
   *
   * No more called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::getTextOrLink() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getFieldTextOrLink($entity, $field_name, $settings, $multiple = TRUE) {
    $langcode  = $settings['langcode'] ?? '';
    $view_mode = $settings['view_mode'] ?? 'default';
    return BlazyField::getTextOrLink($entity, $field_name, $view_mode, $langcode, $multiple);
  }

  /**
   * Deprecated method to return the string value of link, or text.
   *
   * No more called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::getValue() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getFieldValue($entity, $field_name, $langcode) {
    return BlazyField::getValue($entity, $field_name, $langcode);
  }

  /**
   * Deprecated method to return file view or media.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::getOrViewMedia() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getFileOrMedia($file, array $settings, $rendered = TRUE) {
    return BlazyField::getOrViewMedia($file, $settings, $rendered);
  }

}
