<?php

namespace Drupal\blazy_layout\Form;

use Drupal\blazy\Form\BlazyAdminBase;
use Drupal\blazy_layout\BlazyLayoutDefault as Defaults;
use Drupal\blazy_layout\BlazyLayoutManagerInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extends base form for Blazy layout instance configuration form.
 */
class BlazyLayoutAdmin extends BlazyAdminBase implements BlazyLayoutAdminInterface {

  use StringTranslationTrait;

  /**
   * The blazy layout manager service.
   *
   * @var \Drupal\blazy_layout\BlazyLayoutManagerInterface
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->setManager($container->get('blazy_layout'));

    return $instance;
  }

  /**
   * Sets manager service.
   */
  public function setManager(BlazyLayoutManagerInterface $manager) {
    $this->manager = $manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function formBase(array &$form, array $settings, array $excludes = []): void {
    $elements = [];
    $tooltip = ['class' => ['is-tooltip']];

    $elements['count'] = [
      '#type'        => 'number',
      '#title'       => $this->t('Region count'),
      '#maxlength'   => 255,
      '#description' => $this->t('The amount of regions, normally matches the amount of grids specific for Native Grid.'),
    ];

    $elements['style'] = [
      '#type'        => 'select',
      '#title'       => $this->t('Layout engine'),
      '#options'     => $this->blazyManager->getStyles(),
      '#description' => $this->openingDescriptions()['style'],
    ];

    foreach (array_keys($elements) as $key) {
      if ($excludes && in_array($key, $excludes)) {
        unset($elements[$key]);
        continue;
      }
      $elements[$key]['#default_value'] = $settings[$key] ?? '';
      $elements[$key]['#attributes'] = $tooltip;
      $elements[$key]['#required'] = TRUE;
      $elements[$key]['#weight'] = 20;
    }

    $form += $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formStyles(array &$form, array $settings, array $excludes = []): void {
    $tooltip = ['class' => ['is-tooltip']];

    if ($region = $settings['rid'] ?? NULL) {
      $parents = ['layout_settings', 'regions', $region, 'settings', 'styles'];
    }
    else {
      $parents = ['layout_settings', 'settings', 'styles'];
    }

    $form['styles'] = [
      '#type'    => 'details',
      '#tree'    => TRUE,
      '#open'    => FALSE,
      '#title'   => $this->t('Styles'),
      '#parents' => $parents,
      '#weight'  => 10,
    ];

    // Colors.
    $form['styles']['colors'] = [
      '#type'        => 'details',
      '#tree'        => TRUE,
      '#open'        => TRUE,
      '#title'       => $this->t('Colors'),
      '#parents'     => array_merge($parents, ['colors']),
      '#description' => $this->t('Might conflict against CSS framework classes like Bootstrap, etc. Just leave them to default values (color #000000/ black, and opacity 1 or 0) to respect CSS framework. Only useful if colors are not provided by frameworks.'),
    ];

    $colors = &$form['styles']['colors'];
    $colors['background_color'] = [
      '#type'  => 'color',
      '#title'  => $this->t('Background color'),
    ];

    $colors['background_opacity'] = [
      '#type'  => 'range',
      '#title' => $this->t('Background opacity'),
    ];

    $colors['overlay_color'] = [
      '#type'  => 'color',
      '#title' => $this->t('Overlay color'),
    ];

    $colors['overlay_opacity'] = [
      '#type'  => 'range',
      '#title' => $this->t('Overlay opacity'),
    ];

    $colors['text_color'] = [
      '#type'  => 'color',
      '#title' => $this->t('Text color'),
    ];

    $colors['text_opacity'] = [
      '#type'  => 'range',
      '#title' => $this->t('Text opacity'),
    ];

    $colors['heading_color'] = [
      '#type'  => 'color',
      '#title' => $this->t('Heading color'),
    ];

    $colors['heading_opacity'] = [
      '#type'  => 'range',
      '#title' => $this->t('Heading opacity'),
    ];

    foreach ($this->manager->getKeys($colors) as $key) {
      if ($excludes && in_array($key, $excludes)) {
        unset($colors[$key]);
        continue;
      }

      $value = $settings['colors'][$key] ?? '';

      if (strpos($key, '_opacity') !== FALSE) {
        $colors[$key]['#min'] = 0;
        $colors[$key]['#max'] = 1;
        $colors[$key]['#step'] = 0.1;
        $colors[$key]['#field_suffix'] = '1';
      }

      if (strpos($key, '_color') !== FALSE) {
        $colors[$key]['#field_suffix'] = $value;
      }

      $colors[$key]['#default_value'] = $value;
      $colors[$key]['#attributes'] = $tooltip;
    }

    // Layouts.
    $form['styles']['layouts'] = [
      '#type'    => 'details',
      '#tree'    => TRUE,
      '#open'    => TRUE,
      '#title'   => $this->t('Layouts'),
      '#parents' => array_merge($parents, ['layouts']),
    ];

    $layouts = &$form['styles']['layouts'];
    $layouts['padding'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Padding'),
      '#description' => $this->t('Valid CSS padding value, e.g.: <code>3rem or 15px 30px</code>. Leave empty if using CSS framework like Bootstrap, etc. Input padding as classes in the relevant <b>Classes</b> option instead, e.g.: <code>p-sm-2 p-md-5</code>'),
    ];

    foreach ($this->manager->getKeys($layouts) as $key) {
      if ($excludes && in_array($key, $excludes)) {
        unset($layouts[$key]);
        continue;
      }

      $layouts[$key]['#default_value'] = $settings['layouts'][$key] ?? '';
      $layouts[$key]['#attributes'] = $tooltip;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @todo refine and merge with self::formWrappers().
   */
  public function formSettings(array &$form, array $settings, array $excludes = []): void {
    $defaults    = Defaults::layoutSettings();
    $admin_css   = $this->blazyManager->config('admin_css', 'blazy.settings');
    $tooltip     = ['class' => ['is-tooltip']];
    $bottoms     = ['align_items', 'grid_auto_rows'];
    $elements    = $options = [];
    $description = '';

    foreach ($defaults as $key => $value) {
      if ($excludes && in_array($key, $excludes)) {
        continue;
      }

      switch ($key) {
        case 'wrapper':
          $options = Defaults::mainWrapperOptions();
          $description = '';
          break;

        case 'classes':
          $options = [];
          $description = $this->t('Use space: <code>bg-dark text-white</code>. May use CSS framework classes like Bootstrap, e.g.: <code>p-sm-2 p-md-5</code>');
          break;

        case 'align_items':
          $options = Defaults::aligItems();
          $description = $this->t('Flexbox and Native Grid only. Try <code>start</code> to have floating elements, but might break Blazy CSS background. The CSS align-items property sets the align-self value on all direct children as a group. In Flexbox, it controls the alignment of items on the Cross Axis. In Grid Layout, it controls the alignment of items on the Block Axis within their grid area. <a href="@url">Read more</a>', [
            '@url' => 'https://developer.mozilla.org/en-US/docs/Web/CSS/align-items',
          ]);
          break;

        case 'grid_auto_rows':
          $options = [];
          $description = $this->t('Native Grid only. Accepted values: auto, min-content, max-content, minmax. Spefiic for minmax, it requires additional arguments, e.g.: minmax(80px, auto). Default to use the CSS rule <code>var(--bn-row-height-native)</code> or 80px. <a href="@url">Read more</a>', [
            '@url' => 'https://developer.mozilla.org/en-US/docs/Web/CSS/grid-auto-rows',
          ]);
          break;

        default:
          $options = [];
          $description = '';
          break;
      }

      $type = is_bool($value) ? 'checkbox' : 'textfield';
      if ($options) {
        $type = 'select';
      }

      $title = str_replace('_', ' ', $key);
      $elements[$key] = [
        '#type'        => $type,
        '#title'       => $this->t('@title', ['@title' => ucfirst($title)]),
        '#description' => $description,
        '#attributes'  => $tooltip,
        '#required'    => $key == 'wrapper',
      ];

      if ($options) {
        $elements[$key]['#options'] = $options;
        if (empty($elements[$key]['#required'])) {
          $elements[$key]['#empty_option'] = $this->t('- None -');
        }
      }
    }

    // Defines the default values if available.
    foreach ($elements as $name => $element) {
      $type     = $element['#type'];
      $fallback = $type == 'checkbox' ? FALSE : '';
      $value    = $defaults[$name] ?? $fallback;

      if (is_array($value)) {
        continue;
      }

      // Stupid, but in case more stupidity gets in the way.
      if ($type == 'textfield') {
        $value = strip_tags($value);
      }

      $elements[$name]['#default_value'] = $settings[$name] ?? $value;
      $elements[$name]['#attributes']['class'][] = 'is-tooltip';

      if ($type == 'textfield') {
        $elements[$name]['#size'] = 20;
        $elements[$name]['#maxlength'] = 255;
      }

      if ($admin_css) {
        if ($type == 'checkbox') {
          $elements[$name]['#title_display'] = 'before';
        }

        foreach ($bottoms as $key) {
          if (!isset($elements[$key]['#wrapper_attributes'])) {
            $elements[$key]['#wrapper_attributes'] = [];
          }

          $attrs = &$elements[$key]['#wrapper_attributes'];
          $attrs['class'][] = 'b-tooltip__bottom';
        }
      }
    }

    $form += $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formWrappers(
    array &$form,
    array $settings,
    array $excludes = [],
    $root = TRUE,
  ): void {
    $tooltip  = ['class' => ['is-tooltip']];
    $elements = [];

    $elements['wrapper'] = [
      '#type'     => 'select',
      '#options'  => $root ? Defaults::mainWrapperOptions() : Defaults::regionWrapperOptions(),
      '#required' => TRUE,
      '#title'    => $this->t('Wrapper'),
    ];

    $elements['attributes'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Attributes'),
      '#description' => $this->t('Use comma: role|main,data-key|value'),
      '#access'      => FALSE,
    ];

    $elements['classes'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Classes'),
      '#description' => $this->t('Use space: bg-dark text-white. May use CSS framework classes like Bootstrap, e.g.: <code>p-sm-2 p-md-5</code>'),
    ];

    $elements['row_classes'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Row classes'),
      '#description' => $this->t('Use space: align-items-stretch no-gutters'),
      '#access'      => FALSE,
    ];

    foreach (array_keys($elements) as $key) {
      if ($excludes && in_array($key, $excludes)) {
        unset($elements[$key]);
        continue;
      }

      $value = $settings[$key] ?? '';
      $elements[$key]['#default_value'] = $value ? Xss::filter($value) : '';
      $elements[$key]['#attributes'] = $tooltip;
    }

    $form += $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formBackground(
    array &$form,
    array $settings,
    array $excludes = [],
    $root = TRUE,
  ): void {
    $tooltip  = ['class' => ['is-tooltip']];
    $elements = [];

    $elements['wrapper'] = [
      '#type'     => 'select',
      '#options'  => $root ? Defaults::mainWrapperOptions() : Defaults::regionWrapperOptions(),
      '#required' => TRUE,
      '#title'    => $this->t('Wrapper'),
    ];

    $elements['attributes'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Attributes'),
      '#description' => $this->t('Use comma: role|main,data-key|value'),
      '#access'      => FALSE,
    ];

    $elements['classes'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Classes'),
      '#description' => $this->t('Use space: bg-dark text-white'),
    ];

    $elements['row_classes'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Row classes'),
      '#description' => $this->t('Use space: align-items-stretch no-gutters'),
      '#access'      => FALSE,
    ];

    foreach (array_keys($elements) as $key) {
      if ($excludes && in_array($key, $excludes)) {
        unset($elements[$key]);
        continue;
      }

      $value = $settings[$key] ?? '';
      $elements[$key]['#default_value'] = $value ? Xss::filter($value) : '';
      $elements[$key]['#attributes'] = $tooltip;
    }

    $form += $elements;
  }

  /**
   * Checks for valid color excluding black (#000000) by design.
   */
  protected function getColor($key, array $settings) {
    $colors = $settings['styles'];
    return !empty($colors[$key]) && $colors[$key] != '#000000' ? $colors[$key] : FALSE;
  }

}
