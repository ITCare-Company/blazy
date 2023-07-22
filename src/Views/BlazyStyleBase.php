<?php

namespace Drupal\blazy\Views;

use Drupal\views\Plugin\views\style\StylePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base for blazy views integration to support plain old images, or none.
 */
abstract class BlazyStyleBase extends StylePluginBase implements BlazyStyleBaseInterface {

  // @todo remove it after its contents moved here.
  use BlazyStyleBaseTrait;

  /**
   * {@inheritdoc}
   */
  protected static $namespace = 'blazy';

  /**
   * {@inheritdoc}
   */
  protected static $itemId = 'slide';

  /**
   * {@inheritdoc}
   */
  protected static $itemPrefix = 'slide';

  /**
   * {@inheritdoc}
   */
  protected static $captionId = 'caption';

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->blazyManager = $container->get('blazy.manager');

    return $instance;
  }

}
