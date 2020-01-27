<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

/**
 * A Trait common for all blazy formatters.
 */
trait BlazyFormatterTrait {

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyFormatterManager
   */
  protected $formatter;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * Returns the blazy formatter manager.
   */
  public function formatter() {
    return $this->formatter;
  }

  /**
   * Returns the blazy manager.
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * Returns the blazy admin service.
   */
  public function admin() {
    return \Drupal::service('blazy.admin.formatter');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return $this->admin()->getSettingsSummary($this->getScopedFormElements());
  }

  /**
   * Builds the settings.
   */
  public function buildSettings() {
    $settings = array_merge($this->getCommonFieldDefinition(), $this->getSettings());
    $settings['third_party'] = $this->getThirdPartySettings();
    return $settings;
  }

  /**
   * Defines the common scope for both front and admin.
   */
  public function getCommonFieldDefinition() {
    $field = $this->fieldDefinition;
    return [
      'current_view_mode' => $this->viewMode,
      'field_name'        => $field->getName(),
      'field_type'        => $field->getType(),
      'entity_type'       => $field->getTargetEntityTypeId(),
      'plugin_id'         => $this->getPluginId(),
      'target_type'       => $this->getFieldSetting('target_type'),
    ];
  }

  /**
   * Defines the common scope for the form elements.
   */
  public function getCommonScopedFormElements() {
    return ['settings' => $this->getSettings()] + $this->getCommonFieldDefinition();
  }

}
