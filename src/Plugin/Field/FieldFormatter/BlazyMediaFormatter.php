<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;

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
   * Returns the overridable blazy field formatter service.
   */
  public function formatter() {
    return $this->formatter;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $media = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($media)) {
      return [];
    }

    // Collects specific settings to this formatter.
    $settings              = $this->buildSettings();
    $settings['blazy']     = TRUE;
    $settings['namespace'] = $settings['item_id'] = $settings['lazy'] = 'blazy';

    // Sets dimensions once to reduce method ::transformDimensions() calls.
    $media = array_values($media);
    if ($media[0]->getEntityTypeId() == 'media' && $fields = $media[0]->getFields()) {
      if (isset($fields['thumbnail'])) {
        $item = $fields['thumbnail']->get(0);
        $settings['first_item'] = $item;
        $settings['first_uri'] = $item->entity->getFileUri();
      }
    }

    // Build the settings.
    $build = ['settings' => $settings];

    // Modifies settings.
    $this->formatter->buildSettings($build, $items);

    // Build the elements.
    $this->buildElements($build, $media, $langcode);

    // Pass to manager for easy updates to all Blazy formatters.
    return $this->formatter->build($build);
  }

  /**
   * {@inheritdoc}
   */
  public function getScopedFormElements() {
    $multiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();

    return [
      'fieldable_form'  => FALSE,
      'grid_form'       => $multiple,
      'layouts'         => [],
      'settings'        => $this->getSettings(),
      'style'           => $multiple,
      'thumbnail_style' => TRUE,
      'vanilla'         => FALSE,
    ] + parent::getScopedFormElements();
  }

}
