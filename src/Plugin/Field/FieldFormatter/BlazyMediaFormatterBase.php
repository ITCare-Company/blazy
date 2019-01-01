<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazyFormatterManager;
use Drupal\blazy\BlazyOEmbed;
use Drupal\blazy\Dejavu\BlazyVideoTrait;
use Drupal\blazy\Dejavu\BlazyEntityReferenceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for blazy/slick media ER formatters.
 *
 * @see Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatter.
 */
abstract class BlazyMediaFormatterBase extends BlazyEntityReferenceBase implements ContainerFactoryPluginInterface {

  use BlazyFormatterBaseTrait;
  use BlazyVideoTrait;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a BlazyFormatter object.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    LoggerChannelFactoryInterface $logger_factory,
    BlazyOEmbed $blazy_oembed,
    BlazyFormatterManager $blazy_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->loggerFactory = $logger_factory;
    $this->blazyOembed = $blazy_oembed;
    $this->blazyManager = $blazy_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('logger.factory'),
      $container->get('blazy.oembed'),
      $container->get('blazy.formatter.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return BlazyDefault::extendedSettings() + BlazyDefault::gridSettings();
  }

}
