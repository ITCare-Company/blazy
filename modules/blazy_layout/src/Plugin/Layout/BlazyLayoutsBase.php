<?php

namespace Drupal\blazy_layout\Plugin\Layout;

use Drupal\blazy_layout\BlazyLayoutDefault as Defaults;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Layout\LayoutDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a BlazyLayoutsBase class for Layout plugins.
 */
class BlazyLayoutsBase extends LayoutDefault implements BlazyLayoutsInterface {

  /**
   * The blazy admin service.
   *
   * @var \Drupal\blazy\Form\BlazyAdminInterface
   */
  protected $admin;

  /**
   * The blazy layout service.
   *
   * @var \Drupal\blazy_layout\BlazyLayoutInterface
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  protected static $namespace = 'blazy';

  /**
   * {@inheritdoc}
   */
  protected static $itemId = 'box';

  /**
   * {@inheritdoc}
   */
  protected static $itemPrefix = 'blazy';

  /**
   * {@inheritdoc}
   */
  protected static $captionId = 'blazy';

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );

    $instance->admin = $container->get('blazy.admin');
    $instance->manager = $container->get('blazy_layout');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return Defaults::layoutSettings() + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if ($settings = $form_state->getValue('settings')) {
      foreach ($settings as $key => $value) {
        $this->configuration[$key] = $value;
      }
    }

    $regions = [];
    if ($values = $form_state->getValue('regions')) {
      foreach ($values as $name => &$region) {
        foreach ($region as $key => &$value) {
          $regions[$name][$key] = $value;
        }
      }
    }

    $this->configuration['regions'] = $regions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // This form may be loaded as a subform Layout Builder, etc.
    // More info: #2536646, #2798261, #2774077, #2897557.
    $form_state2 = $form_state instanceof SubformStateInterface
      ? $form_state->getCompleteFormState()
      : $form_state;

    $config     = $this->getConfiguration();
    $definition = $this->getPluginDefinition();
    $name       = $definition->get('blazy_layout') ?: 'blazy_layout';
    $settings   = [];

    if (isset($form['label'])) {
      $name_nice = Unicode::ucfirst($name);
      $form['label']['#attributes']['placeholder'] = $this->t('@name', ['@name' => $name_nice]);
      $form['label']['#wrapper_attributes']['class'][] = 'is-blz-aside';
      $form['label']['#description'] = $this->t('A region has direct contents. A container contains multiple regions.');
      $default = empty($config['label']) ? str_replace('_', ' ', $name_nice) : $config['label'];
      $form['label']['#default_value'] = $form_state2->getValue('label', $default);
    }

    // The main grid setttings.
    foreach (Defaults::layoutSettings() as $key => $value) {
      $default = $config[$key] ?? $value;
      $settings[$key] = $form_state2->getValue(['settings', $key], $default);
    }

    // Allows regions being modified by variants.
    $regions = $this->manager->getRegions($settings['count']);

    $form['settings'] = [
      '#type'        => 'details',
      '#tree'        => TRUE,
      '#open'        => TRUE,
      '#weight'      => 30,
      '#title'       => $this->t('Global settings'),
      '#description' => $this->t('Options require saving the form first.'),
      '#parents'     => ['layout_settings', 'settings'],
    ];

    $form['settings']['style'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Layout engine'),
      '#options'       => $this->manager->getStyles(),
      '#required'      => TRUE,
      '#default_value' => $settings['style'],
      '#description'   => $this->admin->openingDescriptions()['style'],
    ];

    $form['settings']['count'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Region amount'),
      '#required'      => TRUE,
      '#default_value' => $settings['count'],
      '#description'   => $this->t('The amount of regions to override. Default to 9. Specific for Native Grid, be sure to match the amount of designated grid boxes.'),
    ];

    $form['settings']['align_items'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Align items'),
      '#options'       => Defaults::aligItems(),
      '#empty_option'  => $this->t('- None -'),
      '#default_value' => $settings['align_items'],
      '#description'   => $this->t('Flexbox and Native Grid only. Try <code>start</code> to have floating elements, but might break Blazy CSS background. The CSS align-items property sets the align-self value on all direct children as a group. In Flexbox, it controls the alignment of items on the Cross Axis. In Grid Layout, it controls the alignment of items on the Block Axis within their grid area. <a href="@url">Read more</a>', [
        '@url' => 'https://developer.mozilla.org/en-US/docs/Web/CSS/align-items',
      ]),
    ];

    $form['settings']['grid_auto_rows'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Grid auto rows'),
      '#default_value' => $settings['grid_auto_rows'],
      '#description'   => $this->t('Native Grid only. Accepted values: auto, min-content, max-content, minmax. Spefiic for minmax, it requires additional arguments, e.g.: minmax(80px, auto). Default to use the CSS rule <code>var(--bn-row-height-native)</code> or 80px. <a href="@url">Read more</a>', [
        '@url' => 'https://developer.mozilla.org/en-US/docs/Web/CSS/grid-auto-rows',
      ]),
      '#states'        => [
        'select[name="[style]"]' => ['value' => 'nativegrid'],
      ],
    ];

    $definition = [
      'grid_simple' => TRUE,
      'no_grid_header' => TRUE,
      'blazy_layout' => TRUE,
    ];

    $grid_form = [];
    $this->admin->gridForm($grid_form, $definition);

    foreach ($grid_form as $key => $element) {
      $form['settings'][$key] = $element;
      $form['settings'][$key]['#default_value'] = $settings[$key];

      if ($key == 'grid') {
        if (isset($form['settings'][$key]['#description'])) {
          $form['settings'][$key]['#description'] .= $this->admin->nativeGridDescription();
        }
      }
    }

    $form['regions'] = [
      '#type'    => 'container',
      '#tree'    => TRUE,
      '#parents' => ['layout_settings', 'regions'],
    ];

    // Region settings.
    $settings2 = [];
    foreach ($regions as $region => $info) {
      foreach (Defaults::regionSettings() as $key => $value) {
        $default = $config['regions'][$region][$key] ?? $value;
        $default = $form_state2->getValue(['regions', $region, $key], $default);
        $settings2['regions'][$region][$key] = $default;
      }

      $label = $this->t('@type: <em>@label</em>', [
        '@type'  => $info['type'],
        '@label' => $info['label'],
      ]);
      $form['regions'][$region] = [
        '#type'    => 'details',
        '#title'   => $label,
        '#open'    => FALSE,
        '#tree'    => TRUE,
        '#parents' => ['layout_settings', 'regions', $region],
      ];

      $subsets = &$settings2['regions'][$region];
      $form['regions'][$region]['attributes'] = [
        '#type'          => 'textfield',
        '#title'         => $this->t('Attributes'),
        '#default_value' => $subsets['attributes'],
        '#description'   => $this->t('The region attributes.'),
        // @todo enable when available:
        '#access'        => FALSE,
      ];

      $form['regions'][$region]['name'] = [
        '#type'          => 'textfield',
        '#title'         => $this->t('Region name'),
        '#default_value' => $subsets['name'],
        '#description'   => $this->t('The human-readable region name for theming.'),
      ];
    }

    return parent::buildConfigurationForm($form, $form_state2);
  }

}
