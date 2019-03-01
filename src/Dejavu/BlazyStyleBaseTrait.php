<?php

namespace Drupal\blazy\Dejavu;

use Drupal\Component\Utility\NestedArray;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;

/**
 * A Trait common for optional views style plugins.
 */
trait BlazyStyleBaseTrait {

  /**
   * The dynamic html settings.
   *
   * @var array
   */
  protected $htmlSettings = [];

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * Returns the blazy manager.
   */
  public function blazyManager() {
    return $this->blazyManager;
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

    if (!empty($this->htmlSettings)) {
      $settings = NestedArray::mergeDeep($settings, $this->htmlSettings);
    }

    return $settings + BlazyDefault::lazySettings();
  }

  /**
   * Sets dynamic html settings.
   */
  protected function setHtmlSettings(array $settings = []) {
    $this->htmlSettings = $settings;
    return $this;
  }

}
