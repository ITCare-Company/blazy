<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\blazy\Field\BlazyField;
use Drupal\blazy\Traits\PluginScopesTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Trait common for all blazy formatters.
 */
trait BlazyFormatterTrait {

  use PluginScopesTrait;
  use BlazyFormatterViewTrait;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyFormatterInterface
   */
  protected $formatter;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * The blazy-related manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $manager;

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
   * Returns any blazy-related manager.
   */
  public function manager() {
    return $this->manager;
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
    $blazies  = $settings['blazies'];
    $is_grid  = !empty($settings['style']) && !empty($settings['grid']);

    $blazies->set('is.grid', $is_grid);
    $blazies->set('is.multiple', $this->isMultiple());

    $this->pluginSettings($blazies, $settings);

    return $settings;
  }

  /**
   * Defines the scope for the form elements.
   *
   * Since 2.10 sub-modules can forget this, and use self::getPluginScopes().
   */
  public function getScopedFormElements() {
    // Containing settings, blazies which must be intact, and the rest, which
    // can be removed after migrations, are merged into scopes object.
    $commons = $this->getCommonScopedFormElements();

    // Compat for BVEF till updated to adopt Blazy 2.10 BlazyVideoFormatter.
    $scopes = method_exists($this, 'getPluginScopes')
      ? $this->getPluginScopes() : [];

    // @todo remove `$scopes +` at Blazy 3.x, leaving only settings + blazies.
    $definitions = $scopes + $commons;
    $definitions['scopes'] = $this->toPluginScopes($scopes + $commons);
    return $definitions;
  }

  /**
   * Injects DI services.
   */
  protected static function injectServices($instance, ContainerInterface $container, $type = '') {
    // Blazy has sequential inheritance, its sub-modules deviate.
    $instance->formatter = $instance->blazyManager = $instance->manager = $container->get('blazy.formatter');

    // Provides optional services.
    if ($type == 'entity') {
      $instance->loggerFactory = $instance->loggerFactory ?? $container->get('logger.factory');
      $instance->blazyEntity = $instance->blazyEntity ?? $container->get('blazy.entity');
      $instance->blazyOembed = $instance->blazyOembed ?? $instance->blazyEntity->oembed();
    }

    return $instance;
  }

  /**
   * Defines the common scope for both front and admin.
   */
  protected function getCommonFieldDefinition() {
    $field = $this->fieldDefinition;
    $settings = [
      'namespace' => $this->namespace ?? 'blazy',
    ];

    // Exposes few basic formatter settings w/o use_field.
    $data = [
      'label_display' => $this->label,
      'plugin_id'     => $this->getPluginId(),
      'third_party'   => $this->getThirdPartySettings(),
      'view_mode'     => $this->viewMode,
    ];

    return BlazyField::settings($settings, $field, $data);
  }

  /**
   * Defines the common scope for the form elements.
   */
  protected function getCommonScopedFormElements() {
    return ['settings' => $this->getSettings()]
      + $this->getCommonFieldDefinition();
  }

  /**
   * Returns TRUE if a multi-value field.
   *
   * @return bool
   *   TRUE if a multivalue field, else FALSE.
   */
  protected function isMultiple(): bool {
    return $this->fieldDefinition
      ->getFieldStorageDefinition()
      ->isMultiple();
  }

}
