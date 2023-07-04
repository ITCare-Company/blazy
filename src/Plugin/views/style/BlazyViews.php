<?php

namespace Drupal\blazy\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\blazy\BlazyManager;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Views\BlazyStyleBaseTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Blazy style plugin.
 */
class BlazyViews extends StylePluginBase implements BlazyViewsInterface {

  use BlazyStyleBaseTrait;

  /**
   * {@inheritdoc}
   */
  protected $namespace = 'blazy';

  /**
   * {@inheritdoc}
   */
  protected $itemId = 'content';

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * Constructs a BlazyManager object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, BlazyManager $blazy_manager) {
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
      'namespace'      => 'blazy',
      'grid_form'      => TRUE,
      'grid_required'  => TRUE,
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

    // Blazy doesn't need complex grid with multiple groups.
    unset($form['layout'], $form['preserve_keys'], $form['visible_items']);
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

      // Supports Blazy multi-breakpoint images if using Blazy formatter.
      if ($data = $this->getFirstImage($rows[0] ?? NULL)) {
        $blazies->set('first.data', $data);
      }

      $build = ['items' => $items, 'settings' => $settings];
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
