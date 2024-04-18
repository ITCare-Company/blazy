<?php

namespace Drupal\blazy_layout\Form;

use Drupal\blazy\Form\BlazyAdminBase;
use Drupal\blazy_layout\BlazyLayoutDefault as Defaults;
use Drupal\Component\Utility\Xss;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Extends base form for Blazy layout instance configuration form.
 */
class BlazyLayoutAdmin extends BlazyAdminBase implements BlazyLayoutAdminInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function formBase(array &$form, array $settings, array $excludes = []): void {
    $elements = [];
    $tooltip = ['class' => ['is-tooltip']];

    $elements['count'] = [
      '#type'        => 'number',
      '#title'       => $this->t('Count'),
      '#maxlength'   => 255,
      '#description' => $this->t('The amount of regions. Specific for Native Grid, be sure to match the amount of designated grid boxes.'),
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
          break;

        case 'align_items':
          $options = Defaults::aligItems();
          $description = $this->t('Flexbox and Native Grid only. Try <code>start</code> to have floating elements, but might break Blazy CSS background. The CSS align-items property sets the align-self value on all direct children as a group. In Flexbox, it controls the alignment of items on the Cross Axis. In Grid Layout, it controls the alignment of items on the Block Axis within their grid area. <a href="@url">Read more</a>', [
            '@url' => 'https://developer.mozilla.org/en-US/docs/Web/CSS/align-items',
          ]);
          break;

        case 'grid_auto_rows':
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
      if ($type == 'number') {
        $elements[$name]['#maxlength'] = 60;
        $elements[$name]['#attributes']['class'][] = 'form-text--int';
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
    $root = TRUE
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

}
