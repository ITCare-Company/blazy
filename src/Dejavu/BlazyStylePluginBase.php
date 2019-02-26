<?php

namespace Drupal\blazy\Dejavu;

use Drupal\views\Views;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazyManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base for blazy views integration to have re-usable methods in one place.
 *
 * @see \Drupal\mason\Plugin\views\style\MasonViews
 * @see \Drupal\gridstack\Plugin\views\style\GridStackViews
 * @see \Drupal\slick_views\Plugin\views\style\SlickViews
 */
abstract class BlazyStylePluginBase extends StylePluginBase {

  use BlazyStylePluginTrait;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * Constructs a GridStackManager object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, BlazyManagerInterface $blazy_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->blazyManager = $blazy_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('blazy.manager'));
  }

  /**
   * Returns an individual row/element content.
   */
  public function buildElement(array &$element, $row, $index) {
    $settings = &$element['settings'];
    $item_id = empty($settings['item_id']) ? 'box' : $settings['item_id'];

    // Add main image fields if so configured.
    if (!empty($settings['image'])) {
      // Supports individual grid/box image style either inline IMG, or CSS.
      $image             = $this->getImageRenderable($settings, $row, $index);
      $element['item']   = $this->getImageItem($image);
      $element[$item_id] = empty($image['rendered']) ? [] : $image['rendered'];
    }

    // Add caption fields if so configured.
    $element['caption'] = $this->getCaption($index, $settings);

    // Add layout field, may be a list field, or builtin layout options.
    if (!empty($settings['layout'])) {
      $this->getLayout($settings, $index);
    }
  }

  /**
   * Provides commons settings for the style plugins.
   */
  protected function buildSettings() {
    $view      = $this->view;
    $count     = count($view->result);
    $settings  = $this->options;
    $view_name = $view->storage->id();
    $view_mode = $view->current_display;
    $plugin_id = $this->getPluginId();
    $instance  = str_replace('_', '-', "{$view_name}-{$view_mode}");
    $id        = empty($settings['id']) ? '' : $settings['id'];
    $id        = Blazy::getHtmlId("{$plugin_id}-views-{$instance}", $id);
    $settings += [
      'cache_metadata' => [
        'keys' => [$id, $view_mode, $count],
      ],
    ];

    // Prepare needed settings to work with.
    $settings['id']                = $id;
    $settings['cache_tags']        = $view->getCacheTags();
    $settings['count']             = $count;
    $settings['current_view_mode'] = $view_mode;
    $settings['instance_id']       = $instance;
    $settings['plugin_id']         = $plugin_id;
    $settings['view_name']         = $view_name;
    $settings['view_display']      = $view->style_plugin->displayHandler->getPluginId();
    $settings['_views']            = TRUE;

    return $settings + BlazyDefault::lazySettings();
  }

  /**
   * Returns an array of views for option list.
   *
   * Cannot use Views::getViewsAsOptions() as we need to limit to something.
   */
  protected function getViewsAsOptions($plugin = 'html_list') {
    $options = [];

    // Convert list of objects to options for the form.
    foreach (Views::getEnabledViews() as $view_name => $view) {
      foreach ($view->get('display') as $id => $display) {
        $valid = isset($display['display_options']['style']['type']) && $display['display_options']['style']['type'] == $plugin;
        if ($valid) {
          $options[$view_name . ':' . $id] = $view->label() . ' (' . $display['display_title'] . ')';
        }
      }
    }
    return $options;
  }

}
