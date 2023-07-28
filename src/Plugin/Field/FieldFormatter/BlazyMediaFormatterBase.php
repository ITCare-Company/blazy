<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Field\BlazyEntityMediaBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for blazy-related media ER formatters.
 *
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatter
 * @see \Drupal\gridstack\Plugin\Field\FieldFormatter\GridStackMediaFormatter
 */
abstract class BlazyMediaFormatterBase extends BlazyEntityMediaBase {

  /**
   * {@inheritdoc}
   */
  protected static $fieldType = 'entity';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    return self::injectServices($instance, $container, static::$fieldType);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return BlazyDefault::extendedSettings() + BlazyDefault::gridSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'media';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $entities = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($entities)) {
      return [];
    }

    return $this->commonViewElements($items, $langcode, $entities);
  }

}
