<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\blazy\BlazyFormatterInterface;
use Drupal\blazy\BlazyOEmbedInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Trait common for all blazy formatters using oembed.
 */
trait BlazyFormatterOEmbedTrait {

  /**
   * Constructs a BlazyImageFormatter instance.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, BlazyFormatterInterface $formatter, BlazyOEmbedInterface $oembed) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->formatter = $this->blazyManager = $formatter;
    $this->blazyOembed = $oembed;
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
      $container->get('blazy.formatter'),
      $container->get('blazy.oembed')
    );
  }

}
