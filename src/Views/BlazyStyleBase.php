<?php

namespace Drupal\blazy\Views;

use Drupal\views\Plugin\views\style\StylePluginBase;
// @todo enable use Drupal\blazy\Field\BlazyElementTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base for blazy views integration to support plain old images, or none.
 */
abstract class BlazyStyleBase extends StylePluginBase implements BlazyStyleBaseInterface {

  // @todo enable if similar to field formatters: use BlazyElementTrait;
  // @todo remove it after its contents moved here at/by blazy:2.17.
  use BlazyStyleBaseTrait;

  /**
   * The main module namespace.
   *
   * @var string
   * @see https://www.php.net/manual/en/reserved.keywords.php
   */
  protected static $namespace = 'blazy';

  /**
   * The item property to store image or media: content, slide, box, etc.
   *
   * Prioritize sub-modules in case mismatched versions.
   *
   * @var string
   */
  protected static $itemId = 'slide';

  /**
   * The item prefix for captions, e.g.: blazy__caption, slide__caption, etc.
   *
   * @var string
   */
  protected static $itemPrefix = 'slide';

  /**
   * The caption property to store captions.
   *
   * @var string
   */
  protected static $captionId = 'caption';

  /**
   * Whether using the SVG.
   *
   * @var bool
   */
  protected static $useSvg = FALSE;

  /**
   * The blazy formatter service manager.
   *
   * @var \Drupal\blazy\BlazyFormatterInterface
   */
  protected $formatter;

  /**
   * The blazy formatter service manager, dups but no dups for sub-modules.
   *
   * @var \Drupal\blazy\BlazyFormatterInterface
   */
  protected $manager;

  /**
   * The svg manager service.
   *
   * @var \Drupal\blazy\Media\Svg\SvgInterface
   */
  protected $svgManager;

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

    // For consistent call against ecosystem shared methods, Blazy has straight
    // inheritance, sub-modules deviate:
    $instance->manager = $instance->formatter = $container->get('blazy.formatter');
    $instance->svgManager = $container->get('blazy.svg');

    // @todo remove for consistent call against sub-modules shared methods:
    $instance->blazyManager = $instance->manager;

    return $instance;
  }

}
