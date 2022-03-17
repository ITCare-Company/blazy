<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\blazy\BlazyDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The blazy entity service.
   *
   * @var \Drupal\blazy\BlazyEntityInterface
   */
  protected $blazyEntity;

  /**
   * The blazy oembed service.
   *
   * @var \Drupal\blazy\Media\BlazyOEmbedInterface
   */
  protected $blazyOembed;

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
   * Returns the blazy entity manager.
   */
  public function blazyEntity() {
    return $this->blazyEntity;
  }

  /**
   * Returns the blazy oembed manager.
   */
  public function blazyOembed() {
    return $this->blazyOembed;
  }

  /**
   * Returns the blazy admin service.
   */
  public function admin() {
    return \Drupal::service('blazy.admin.formatter');
  }

  /**
   * Injects DI services.
   */
  protected static function injectServices($instance, ContainerInterface $container, $type = '') {
    $instance->formatter = $instance->blazyManager = $container->get('blazy.formatter');

    // Provides optional services.
    if ($type == 'entity') {
      $instance->loggerFactory = $instance->loggerFactory ?? $container->get('logger.factory');
      $instance->blazyEntity = $instance->blazyEntity ?? $container->get('blazy.entity');
      $instance->blazyOembed = $instance->blazyOembed ?? $instance->blazyEntity->oembed();
    }

    return $instance;
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
    $blazies = $settings['blazies'];
    $field = $this->fieldDefinition;
    $is_grid = !empty($settings['style']) && !empty($settings['grid']);

    // Exposes few basic formatter settings w/o use_field.
    $blazies->set('field.label', $field->getLabel())
      ->set('field.label_display', $this->label)
      ->set('field.name', $field->getName())
      ->set('field.type', $field->getType())
      ->set('field.plugin_id', $this->getPluginId())
      ->set('field.entity_type', $field->getTargetEntityTypeId())
      ->set('field.target_type', $this->getFieldSetting('target_type'))
      ->set('field.third_party', $this->getThirdPartySettings())
      ->set('field.view_mode', $this->viewMode)
      ->set('is.grid', $is_grid);

    return $settings;
  }

  /**
   * Builds the specific Blazy settings.
   */
  protected function blazySettings(array &$settings) {
    $blazies = $settings['blazies'];
    $id = 'blazy';

    $blazies->set('item.id', $id)
      ->set('is.blazy', TRUE)
      ->set('lazy.id', $id)
      ->set('namespace', $id);

    // @todo remove settings after migration and sub-modules.
    $settings['item_id'] = $id;
  }

  /**
   * Defines the common scope for both front and admin.
   *
   * @todo convert all these into BlazySettings as well at 3.x.
   */
  public function getCommonFieldDefinition() {
    $field = $this->fieldDefinition;

    // @todo remove for blazies after sub-modules.
    $settings = [
      'namespace'   => 'blazy',
      'field_name'  => $field->getName(),
      'field_type'  => $field->getType(),
      'entity_type' => $field->getTargetEntityTypeId(),
      'plugin_id'   => $this->getPluginId(),
      'target_type' => $this->getFieldSetting('target_type'),
    ];
    $settings += BlazyDefault::htmlSettings();
    return $settings;
  }

  /**
   * Defines the common scope for the form elements.
   */
  public function getCommonScopedFormElements() {
    return ['settings' => $this->getSettings()] + $this->getCommonFieldDefinition();
  }

}
