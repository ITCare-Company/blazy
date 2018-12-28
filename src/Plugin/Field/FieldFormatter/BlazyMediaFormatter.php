<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin for blazy media formatter.
 *
 * @FieldFormatter(
 *   id = "blazy_media",
 *   label = @Translation("Blazy"),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions",
 *   }
 * )
 *
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatterBase
 * @see \Drupal\media\Plugin\Field\FieldFormatter\MediaThumbnailFormatter
 */
class BlazyMediaFormatter extends BlazyMediaFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'media';
  }

}
