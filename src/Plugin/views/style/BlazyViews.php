<?php

namespace Drupal\blazy\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Views\BlazyStyleBase;

/**
 * Blazy style plugin.
 */
class BlazyViews extends BlazyStyleBase implements BlazyViewsInterface {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * {@inheritdoc}
   */
  public function admin() {
    return \Drupal::service('blazy.admin');
  }

  /**
   * Overrides StylePluginBase::buildOptionsForm().
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $definition = [
      'plugin_id'      => $this->getPluginId(),
      'namespace'      => 'blazy',
      'grid_form'      => TRUE,
      'grid_required'  => TRUE,
      'grid_simple'    => TRUE,
      'no_image_style' => TRUE,
      'opening_class'  => 'form--views',
      'settings'       => $this->options,
      'style'          => TRUE,
      '_views'         => TRUE,
    ];

    // Build the form.
    $this->admin()->openingForm($form, $definition);
    $this->admin()->gridForm($form, $definition);
    $this->admin()->finalizeForm($form, $definition);
  }

  /**
   * Overrides StylePluginBase::render().
   */
  public function render() {
    $settings = $this->buildSettings();
    $blazies = $settings['blazies'];
    $view = $this->view;

    $blazies->set('namespace', $this->namespace)
      ->set('item.id', $this->itemId)
      ->set('is.grid', TRUE);

    $elements = [];
    foreach ($this->renderGrouping($view->result, $settings['grouping']) as $rows) {
      $items = [];
      foreach ($rows as $index => $row) {
        $view->row_index = $index;

        $items[$index] = $view->rowPlugin->render($row);
      }

      // Supports lightbox gallery if using Blazy formatter.
      $build = ['items' => $items];
      $this->checkBlazy($settings, $build, $rows);

      $build['settings'] = $settings;
      $elements = $this->blazyManager->build($build);

      unset($view->row_index, $items);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = [];
    foreach (BlazyDefault::gridSettings() as $key => $value) {
      $options[$key] = ['default' => $value];
    }
    return $options + parent::defineOptions();
  }

}
