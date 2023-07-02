<?php

namespace Drupal\blazy\Field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatterTrait;

/**
 * Base class for entity reference formatters without field details.
 *
 * @see \Drupal\blazy\Field\BlazyEntityMediaBase
 */
abstract class BlazyEntityVanillaBase extends EntityReferenceFormatterBase {

  // Since 2.9 Blazy adapts to sub-module self::viewElements() to DRY so they
  // can remove their own FormatterViewTrait later thanks to similarities.
  use BlazyFormatterTrait {
    pluginSettings as traitPluginSettings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element    = [];
    $definition = $this->getScopedFormElements();

    $definition['_views'] = isset($form['field_api_classes']);

    // @todo remove after sub-modules.
    $definition['view_mode'] = $this->viewMode;
    $definition['plugin_id'] = $this->getPluginId();
    $definition['target_type'] = $this->getFieldSetting('target_type');

    $this->admin()->buildSettingsForm($element, $definition);
    return $element;
  }

  /**
   * Returns media contents.
   */
  protected function buildElements(array &$build, $entities, $langcode) {
    foreach ($entities as $delta => $entity) {
      // Protect ourselves from recursive rendering.
      static $depth = 0;
      $depth++;
      if ($depth > 20) {
        $this->loggerFactory->get('entity')
          ->error('Recursive rendering detected when rendering entity @entity_type @entity_id. Aborting rendering.', [
            '@entity_type' => $entity->getEntityTypeId(),
            '@entity_id' => $entity->id(),
          ]);
        return $build;
      }

      $this->prepareElement($build, $entity, $langcode, $delta);

      // Add the entity to cache dependencies so to clear when it is updated.
      if (!empty($build['items'][$delta])) {
        $this->formatter
          ->renderer()
          ->addCacheableDependency($build['items'][$delta], $entity);
      }

      $depth = 0;
    }
  }

  /**
   * Build item contents.
   */
  protected function buildElement(array &$build, $entity, $langcode) {
    $settings = &$build['settings'];
    $blazies = $settings['blazies'];
    $item_id = $blazies->get('item.id');

    // Sub-modules always flag `vanilla` as required, -- configurable, or not.
    if (!empty($settings['vanilla'])) {
      $data = [
        '#entity' => $entity,
        'settings' => $settings,
      ];

      if ($output = $this->blazyEntity->view($data)) {
        if ($blazies->get('namespace') == 'blazy') {
          $build['items'][] = $output;
        }
        else {
          $build['items'][] = [$item_id => $output];
        }
      }
    }
  }

  /**
   * Returns available bundles.
   */
  protected function getAvailableBundles(): array {
    $field = $this->fieldDefinition;
    return BlazyField::getAvailableBundles($field);
  }

  /**
   * Returns fields as options. Passing empty array will return them all.
   *
   * @return array
   *   The available fields as options.
   */
  protected function getFieldOptions(array $names = [], $target_type = NULL): array {
    $target_type = $target_type ?: $this->getFieldSetting('target_type');
    $bundles     = $this->getAvailableBundles();

    return $this->admin()->getFieldOptions($bundles, $names, $target_type);
  }

  /**
   * Prepare item contents.
   *
   * Alternative for self::buildElement() with extra params for convenient.
   */
  protected function prepareElement(array &$build, $entity, $langcode, $delta): void {
    $settings = &$build['settings'];
    $blazies  = $settings['blazies']->reset($settings);
    $bundle   = $entity->bundle();

    // @todo remove after sub-modules.
    $settings['delta'] = $delta;
    $settings['langcode'] = $langcode;

    $blazies->set('bundles.' . $bundle, $bundle, TRUE)
      ->set('language.code', $langcode)
      ->set('delta', $delta);

    $build['settings'] = $settings;
    $this->buildElement($build, $entity, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    $multiple = $this->isMultiple();
    return [
      'no_layouts'       => TRUE,
      'no_image_style'   => TRUE,
      'responsive_image' => FALSE,
      'target_bundles'   => $this->getAvailableBundles(),
      'vanilla'          => TRUE,
      'view_mode'        => $this->viewMode,
      'multiple'         => $this->isMultiple(),
      'grid_form'        => $multiple,
      'style'            => $multiple,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function pluginSettings(&$blazies, array &$settings): void {
    $this->traitPluginSettings($blazies, $settings);
    $blazies->set('is.blazy', TRUE);

    // @todo remove.
    $settings['blazy'] = TRUE;
  }

}
