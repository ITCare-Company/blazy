<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\blazy\BlazyGrid;

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
   * Returns the overridable blazy manager service.
   */
  public function manager() {
    return $this->blazyManager;
  }

  /**
   * Returns the overridable blazy field formatter service.
   */
  public function formatter() {
    return $this->blazyManager;
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
    $settings['_grid']     = !empty($settings['style']) && !empty($settings['grid']);

    // Sets dimensions once to reduce method ::transformDimensions() calls.
    // @todo: A more flexible way to also support paragraphs at one go.
    $media = array_values($media);
    if (!empty($settings['image_style']) && ($media[0]->getEntityTypeId() == 'media')) {
      $fields = $media[0]->getFields();

      if (isset($fields['thumbnail'])) {
        $item             = $fields['thumbnail']->get(0);
        $settings['item'] = $item;
        $settings['uri']  = $item->entity->getFileUri();
      }
    }

    // Build the settings.
    $build = ['settings' => $settings];

    // Modifies settings.
    $this->formatter()->buildSettings($build, $items);

    // Build the elements.
    $this->buildElements($build, $media, $langcode);

    // Updates settings.
    $settings = $build['settings'];
    unset($build['settings']);

    // With pass by reference, we hardly modify base classes, just re-arrange.
    // As opposed to file/ image formatters with direct indices, blazy-formatted
    // entities are stored within `items` with extra usages like thumbnail navs.
    // If not a grid, pass the items as regular index children to theme_field()
    // and provide a #blazy to identify Blazy formatters at hook_preprocess().
    if (empty($settings['_grid'])) {
      $build = $build['items'];
      $build['#blazy'] = $settings;
    }
    else {
      // Build grid if provided.
      $build = BlazyGrid::build($build['items'], $settings);
      unset($build['items']);
    }

    $build['#attached'] = $this->manager()->attach($settings);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildElement(array &$build, $entity, $langcode) {
    parent::buildElement($build, $entity, $langcode);

    $settings = $build['settings'];
    $delta = isset($settings['delta']) ? $settings['delta'] : 0;
    $element = $build['items'][$delta];

    // Item ID is to allow contextual grouping relevant to (sub-)modules:
    // Slick `slide`, GridStack `box`, Blazy 'blazy', etc.
    $item_id = $settings['item_id'] = empty($settings['item_id']) ? 'box' : $settings['item_id'];

    // Blazy can just collect items directly without further themeing.
    if (!empty($element[$item_id])) {
      $build['items'][$delta] = $element[$item_id];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getScopedFormElements() {
    $field = $this->fieldDefinition;
    $multiple = $field->getFieldStorageDefinition()->isMultiple();

    return [
      'fieldable_form' => FALSE,
      'grid_form' => $multiple,
      'layouts' => [],
      'settings' => $this->buildSettings(),
      'style' => $multiple,
      'vanilla' => FALSE,
    ] + parent::getScopedFormElements();
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'media';
  }

}
