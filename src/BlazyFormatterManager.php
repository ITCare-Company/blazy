<?php

/**
 * @file
 * Contains \Drupal\blazy\BlazyFormatterManager.
 */

namespace Drupal\blazy;

/**
 * Provides common field formatter-related methods: Blazy, Slick.
 */
class BlazyFormatterManager extends BlazyManager {

  /**
   * {@inheritdoc}
   */
  public function buildSettings(array &$build = [], $items) {
    $settings       = &$build['settings'];
    $field          = $items->getFieldDefinition();
    $entity         = $items->getEntity();
    $entity_type_id = $entity->getEntityTypeId();
    $entity_id      = $entity->id();
    $field_name     = $field->getName();
    $field_clean    = str_replace("field_", '', $field_name);
    $target_type    = $field->getFieldStorageDefinition()->getSetting('target_type');
    $optionset_name = empty($settings['optionset']) ? 'default' : $settings['optionset'];
    $unique         = empty($settings['skin']) ? '-' . $optionset_name : '-' . $optionset_name . '-' . $settings['skin'];
    $view_mode      = empty($settings['current_view_mode']) ? '_custom' : $settings['current_view_mode'];
    $namespace      = empty($settings['namespace']) ? 'blazy' : $settings['namespace'];
    $id             = parent::getHtmlId("{$namespace}-{$entity_type_id}-{$entity_id}-{$field_clean}{$unique}");
    $internal_path  = $absolute_path = $url = NULL;

    // Deals with UndefinedLinkTemplateException such as paragraphs type.
    // @see #2596385, or fetch the host entity.
    if (!$entity->isNew() && method_exists($entity, 'hasLinkTemplate')) {
      if ($entity->hasLinkTemplate('canonical')) {
        $url = $entity->toUrl();
        $internal_path = $url->getInternalPath();
        $absolute_path = $url->setAbsolute()->toString();
      }
    }

    $settings += [
      'absolute_path'  => $absolute_path,
      'bundle'         => $entity->bundle(),
      'count'          => $items->count(),
      'entity_id'      => $entity_id,
      'entity_type_id' => $entity_type_id,
      'field_type'     => $field->getType(),
      'field_name'     => $field_name,
      'id'             => $id,
      'internal_path'  => $internal_path,
      'lightbox'       => !empty($settings['media_switch']) && strpos($settings['media_switch'], 'box') !== FALSE,
      'target_type'    => $target_type,
      'cache_metadata' => ['keys' => [$id, $view_mode, $optionset_name]],
    ];

    $settings['caption']  = empty($settings['caption']) ? [] : array_filter($settings['caption']);
    $settings['resimage'] = function_exists('responsive_image_get_image_dimensions');
    unset($entity, $field);
  }

}
