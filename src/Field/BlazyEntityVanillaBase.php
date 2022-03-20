<?php

namespace Drupal\blazy\Field;

use Drupal\blazy\Blazy;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;

/**
 * Base class for entity reference formatters without field details.
 *
 * @see \Drupal\blazy\Field\BlazyEntityMediaBase
 */
abstract class BlazyEntityVanillaBase extends EntityReferenceFormatterBase {

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
        $this->formatter()
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
    $blazies   = $settings['blazies']->reset($settings);
    $view_mode = $blazies->get('field.view_mode', 'full');

    $build['items'][] = $this->formatter()
      ->getEntityTypeManager()
      ->getViewBuilder($entity->getEntityTypeId())
      ->view($entity, $view_mode, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element    = [];
    $definition = $this->getScopedFormElements();

    $definition['_views'] = isset($form['field_api_classes']);

    $this->admin()->buildSettingsForm($element, $definition);
    return $element;
  }

  /**
   * Builds the settings.
   */
  public function buildSettings() {
    $settings = array_merge($this->getCommonFieldDefinition(), $this->getSettings());
    Blazy::verify($settings);
    $blazies = $settings['blazies'];

    $third_party = $this->getThirdPartySettings();
    $blazies->set('field.third_party', $third_party);

    return $settings;
  }

  /**
   * Defines the common scope for both front and admin.
   */
  public function getCommonFieldDefinition() {
    $field = $this->fieldDefinition;

    return [
      'field_name'  => $field->getName(),
      'field_type'  => $field->getType(),
      'entity_type' => $field->getTargetEntityTypeId(),
      'plugin_id'   => $this->getPluginId(),
      'target_type' => $this->getFieldSetting('target_type'),
    ];
  }

  /**
   * Defines the scope for the form elements.
   */
  public function getScopedFormElements() {
    // @todo move common/ reusable properties somewhere.
    return [
      'settings'       => $this->getSettings(),
      'target_bundles' => $this->getAvailableBundles(),
      'view_mode'      => $this->viewMode,
    ] + $this->getCommonFieldDefinition();
  }

  /**
   * Returns available bundles.
   */
  protected function getAvailableBundles() {
    $target_type = $this->getFieldSetting('target_type');
    $views_ui = $this->getFieldSetting('handler') == 'default';
    $bundles = $views_ui ? [] : $this->getFieldSetting('handler_settings')['target_bundles'];

    // Fix for Views UI not recognizing Media bundles, unlike Formatters.
    if (empty($bundles)) {
      $service = Blazy::service('entity_type.bundle.info');
      $bundles = $service->getBundleInfo($target_type);
    }

    return $bundles;
  }

  /**
   * Prepare item contents.
   */
  protected function prepareElement(array &$build, $entity, $langcode, $delta) {
    $settings = $build['settings'];
    $blazies  = $settings['blazies'];
    $bundle   = $entity->bundle();

    $blazies->set('bundles.' . $bundle, $bundle)
      ->set('language.code', $langcode)
      ->set('delta', $delta);

    // @todo remove after sub-modules.
    $settings['delta'] = $delta;

    $build['settings'] = $settings;
    $this->buildElement($build, $entity, $langcode);
  }

}
