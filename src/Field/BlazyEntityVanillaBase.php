<?php

namespace Drupal\blazy\Field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\blazy\Blazy;
use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatterTrait;

/**
 * Base class for entity reference formatters without field details.
 *
 * @see \Drupal\blazy\Field\BlazyEntityMediaBase
 */
abstract class BlazyEntityVanillaBase extends EntityReferenceFormatterBase {

  // Since 2.9 Blazy adapts to sub-module self::viewElements() to DRY so they
  // can remove their own FormatterViewTrait later thanks to similarities.
  use BlazyFormatterTrait;

  /**
   * Returns media contents.
   */
  public function buildElements(array &$build, $entities, $langcode) {
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
          ->getRenderer()
          ->addCacheableDependency($build['items'][$delta], $entity);
      }

      $depth = 0;
    }
  }

  /**
   * Build item contents.
   */
  public function buildElement(array &$build, $entity, $langcode) {
    $settings  = $build['settings'];
    $view_mode = $settings['view_mode'] ?? 'full';

    // Sub-modules always flag `vanilla` as required, -- configurable, or not.
    if (!empty($settings['vanilla'])) {
      $build['items'][] = $this->formatter
        ->getEntityTypeManager()
        ->getViewBuilder($entity->getEntityTypeId())
        ->view($entity, $view_mode, $langcode);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element    = [];
    $definition = $this->getScopedFormElements();

    // @todo remove after sub-modules.
    $definition['view_mode'] = $this->viewMode;
    $definition['plugin_id'] = $this->getPluginId();
    $definition['target_type'] = $this->getFieldSetting('target_type');

    $definition['_views'] = isset($form['field_api_classes']);

    $this->admin()->buildSettingsForm($element, $definition);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    return [
      'target_bundles' => $this->getAvailableBundles(),
    ];
  }

  /**
   * Returns available bundles.
   */
  protected function getAvailableBundles(): array {
    $target_type = $this->getFieldSetting('target_type');
    $views_ui    = $this->getFieldSetting('handler') == 'default';
    $bundles     = $views_ui
      ? [] : $this->getFieldSetting('handler_settings')['target_bundles'];

    // Fix for Views UI not recognizing Media bundles, unlike Formatters.
    if (empty($bundles)
      && $service = Blazy::service('entity_type.bundle.info')) {
      $bundles = $service->getBundleInfo($target_type);
    }

    return $bundles;
  }

  /**
   * Returns fields as options. Passing empty array will return them all.
   */
  protected function getFieldOptions(array $names = [], $target_type = NULL): array {
    $target_type = $target_type ?: $this->getFieldSetting('target_type');
    $bundles     = $this->getAvailableBundles();

    return $this->admin()->getFieldOptions($bundles, $names, $target_type);
  }

  /**
   * Returns TRUE if a multi-value field.
   */
  protected function isMultiple(): bool {
    return $this->fieldDefinition
      ->getFieldStorageDefinition()
      ->isMultiple();
  }

  /**
   * Prepare item contents.
   *
   * Alternative for self::buildElement() with extra params for convenient.
   */
  protected function prepareElement(array &$build, $entity, $langcode, $delta): void {
    $settings = $build['settings'];
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
   * Defines the common scope for both front and admin.
   *
   * @todo convert all these into BlazySettings as well at 3.x.
   */
  public function getCommonFieldDefinition() {
    $field = $this->fieldDefinition;

    // @todo remove for blazies after admin updated and sub-modules.
    $settings = [
      'namespace'   => 'blazy',
      'field_name'  => $field->getName(),
      'field_type'  => $field->getType(),
      'entity_type' => $field->getTargetEntityTypeId(),
      'plugin_id'   => $this->getPluginId(),
      'target_type' => $this->getFieldSetting('target_type'),
      'view_mode'   => $this->viewMode,
      'blazies'     => Blazy::settings(),
    ];

    // Exposes few basic formatter settings w/o use_field.
    $blazies = $settings['blazies'];

    $blazies->set('field.label', $field->getLabel())
      ->set('field.label_display', $this->label)
      ->set('field.name', $field->getName())
      ->set('field.type', $field->getType())
      ->set('field.plugin_id', $this->getPluginId())
      ->set('field.entity_type', $field->getTargetEntityTypeId())
      ->set('field.target_type', $this->getFieldSetting('target_type'))
      ->set('field.view_mode', $this->viewMode)
      ->set('field.third_party', $this->getThirdPartySettings());

    return $settings;
  }

}
