<?php

namespace Drupal\blazy\Deprecated;

use Drupal\blazy\Field\BlazyField;

/**
 * A Trait common for deprecated methods for easy removal and declutter.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 *
 * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
 *   BlazyField::getValue() instead.
 * @see https://www.drupal.org/node/3103018
 */
trait BlazyEntityDeprecatedTrait {

  /**
   * Returns the entity renderable array, no more called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   self::view() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getEntityView($entity, array $settings = [], $fallback = '') {
    return $this->view($entity, $settings, $fallback);
  }

  /**
   * Returns the field renderable array, no more called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::view() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getFieldRenderable($entity, $field_name, $view_mode, $multiple = TRUE) {
    return BlazyField::view($entity, $field_name, $view_mode, $multiple);
  }

  /**
   * Returns the string value of link, or text, no more called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::getString() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getFieldString($entity, $field_name, $langcode, $clean = TRUE) {
    return BlazyField::getString($entity, $field_name, $langcode, $clean);
  }

  /**
   * Returns the text or link value, no more called by sub-modules.
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
   * Returns the string value of link, or text, no more called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::getValue() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getFieldValue($entity, $field_name, $langcode) {
    return BlazyField::getValue($entity, $field_name, $langcode);
  }

  /**
   * Returns file view or media due to being empty returned by view builder.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::getOrViewMedia() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getFileOrMedia($file, array $settings, $rendered = TRUE) {
    return BlazyField::getOrViewMedia($file, $settings, $rendered);
  }

}
