<?php

/**
 * @file
 * Contains \Drupal\blazy\Form\BlazyAdminBase.
 */

namespace Drupal\blazy\Form;

use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Render\Element;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\blazy\BlazyManagerInterface;

/**
 * A base for blazy admin integration to have re-usable methods in one place.
 *
 * @see \Drupal\gridstack\Form\GridStackAdmin
 * @see \Drupal\mason\Form\MasonAdmin
 * @see \Drupal\slick\Form\SlickAdmin
 * @see \Drupal\blazy\Form\BlazyAdminFormatterBase
 */
abstract class BlazyAdminBase implements BlazyAdminInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The typed config manager service.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfig;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * Constructs a BlazyAdminBase object.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
   *   The typed config service.
   * @param \Drupal\slick\BlazyManagerInterface $blazy_manager
   *   The blazy manager service.
   */
  public function __construct(EntityDisplayRepositoryInterface $entity_display_repository, TypedConfigManagerInterface $typed_config, BlazyManagerInterface $blazy_manager) {
    $this->entityDisplayRepository = $entity_display_repository;
    $this->typedConfig             = $typed_config;
    $this->blazyManager            = $blazy_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_display.repository'), $container->get('config.typed'), $container->get('blazy.manager'));
  }

  /**
   * Returns the entity display repository.
   */
  public function getEntityDisplayRepository() {
    return $this->entityDisplayRepository;
  }

  /**
   * Returns the typed config.
   */
  public function getTypedConfig() {
    return $this->typedConfig;
  }

  /**
   * Returns the blazy manager.
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * Returns shared form elements across field formatter and Views.
   */
  public function openingForm(array &$form, $definition = []) {
    if (!isset($definition['namespace'])) {
      return;
    }

    $form['skin'] = [
      '#type'        => 'select',
      '#title'       => t('Skin'),
      '#options'     => isset($definition['skins']) ? $definition['skins'] : [],
      '#enforced'    => TRUE,
      '#description' => t('Skins allow various layouts with just CSS. Some options below depend on a skin. Leave empty to DIY. Or use the provided hook_info() and implement the skin interface to register ones.'),
      '#weight'      => -107,
      '#access'      => isset($definition['skins']),
    ];

    $form['background'] = [
      '#type'        => 'checkbox',
      '#title'       => t('Use CSS background'),
      '#description' => t('Check this to turn the image into CSS background instead.'),
      '#access'      => isset($definition['background']),
      '#weight'      => -98,
    ];

    $form['layout'] = [
      '#type'        => 'select',
      '#title'       => t('Layout'),
      '#options'     => isset($definition['layouts']) ? $definition['layouts'] : [],
      '#description' => t('Requires a skin. The builtin layouts affects the entire items uniformly. Leave empty to DIY.'),
      '#access'      => isset($definition['layouts']),
      '#weight'      => 2,
    ];

    $form['caption'] = [
      '#type'        => 'checkboxes',
      '#title'       => t('Caption fields'),
      '#options'     => isset($definition['captions']) ? $definition['captions'] : [],
      '#description' => t('Enable any of the following fields as captions. These fields are treated and wrapped as captions.'),
      '#access'      => isset($definition['captions']),
      '#weight'      => 80,
    ];

    $weight = -99;
    foreach (Element::children($form) as $key) {
      if (!isset($form[$key]['#weight'])) {
        $form[$key]['#weight'] = ++$weight;
      }
    }
  }

  /**
   * Defines re-usable breakpoints form.
   */
  public function breakpointsForm(array &$form, $definition = []) {
    $settings = $definition['settings'];
    $title = t('Leave Breakpoints empty to disable multi-serving images. <small>If provided, Blazy lazyload applies. Ignored if core Responsive image is provided.</small>');

    $form['breakpoints'] = [
      '#type'       => 'table',
      '#tree'       => TRUE,
      '#header'     => [t('Breakpoint'), t('Max width'), t('Image style')],
      '#prefix'     => '<h2 class="form__title">' . $title . '</h2>',
      '#attributes' => ['class' => ['form-wrapper--table']],
      '#weight'     => 110,
      '#enforced'   => TRUE,
    ];

    // Unlike D7, D8 form states seem to not recognize individual field form.
    $vanilla = ':input[name$="[vanilla]"]';
    if (isset($definition['field_name'])) {
      $vanilla = ':input[name="fields[' . $definition['field_name'] . '][settings_edit_form][settings][vanilla]"]';
    }

    if (!empty($definition['_views'])) {
      $vanilla = ':input[name="options[settings][vanilla]"]';
    }

    $breakpoints = $this->breakpointElements($definition);
    foreach ($breakpoints as $breakpoint => $elements) {
      foreach ($elements as $key => $element) {
        $form['breakpoints'][$breakpoint][$key] = $element;

        // Do this because otherwise the entire form disappears for table type.
        $form['breakpoints'][$breakpoint][$key]['#states'] = [
          'enabled' => [
            'select[name$="[responsive_image_style]"]' => ['value' => ''],
          ],
        ];

        if (isset($definition['vanilla'])) {
          $form['breakpoints'][$breakpoint][$key]['#states']['enabled'][$vanilla] = ['checked' => FALSE];
        }
        $value = isset($settings['breakpoints'][$breakpoint][$key]) ? $settings['breakpoints'][$breakpoint][$key] : '';
        $form['breakpoints'][$breakpoint][$key]['#default_value'] = $value;
      }
    }
  }

  /**
   * Defines re-usable breakpoints form.
   *
   * @todo re-check if retina is supported per breakpoint.
   */
  public function breakpointElements($definition = []) {
    if (!isset($definition['breakpoints'])) {
      return [];
    }

    foreach ($definition['breakpoints'] as $breakpoint) {
      $form[$breakpoint]['breakpoint'] = [
        '#type'               => 'item',
        '#markup'             => $breakpoint,
        '#weight'             => 1,
        '#wrapper_attributes' => ['class' => ['form-item--right']],
      ];

      $form[$breakpoint]['width'] = [
        '#type'               => 'textfield',
        '#title'              => t('Width'),
        '#title_display'      => 'invisible',
        '#field_suffix'       => 'px',
        '#maz_length'         => 4,
        '#size'               => 6,
        '#weight'             => 2,
        '#attributes'         => ['class' => ['form-text--width']],
        '#wrapper_attributes' => ['class' => ['form-item--width']],
      ];

      $form[$breakpoint]['image_style'] = [
        '#type'               => 'select',
        '#title'              => t('Image style'),
        '#title_display'      => 'invisible',
        '#options'            => image_style_options(FALSE),
        '#empty_option'       => t('- None -'),
        '#weight'             => 3,
        '#wrapper_attributes' => ['class' => ['form-item--left']],
      ];
    }

    return $form;
  }

  /**
   * Returns shared ending form elements across field formatter and Views.
   */
  public function closingForm(array &$form, $definition = []) {
    $form['current_view_mode'] = [
      '#type'          => 'hidden',
      '#default_value' => isset($definition['current_view_mode']) ? $definition['current_view_mode'] : '_custom',
      '#weight'        => 100,
    ];

    $this->finalizeForm($form, $definition);
  }

  /**
   * Returns re-usable logic, styling and assets across fields and Views.
   */
  public function finalizeForm(array &$form, $definition = []) {
    $namespace = isset($definition['namespace']) ? $definition['namespace'] : 'slick';
    $settings  = isset($definition['settings']) ? $definition['settings'] : [];
    $vanilla   = isset($definition['vanilla']) ? ' form--vanilla' : '';
    $fallback  = $namespace == 'slick' ? 'form--slick' : 'form--' . $namespace . ' form--slick';
    $classes   = isset($definition['form_opening_classes'])
      ? $definition['form_opening_classes']
      : $fallback . ' form--half has-tooltip' . $vanilla;

    $form['opening'] = [
      '#markup' => '<div class="' . $classes . '">',
      '#weight' => -120,
    ];

    $form['closing'] = [
      '#markup' => '</div>',
      '#weight' => 120,
    ];

    $admin_css = isset($definition['admin_css']) ? $definition['admin_css'] : '';
    $admin_css = $admin_css ?: $this->blazyManager->configLoad('admin_css', 'blazy.settings');
    $excludes  = ['container', 'details', 'item', 'hidden', 'submit'];

    foreach (Element::children($form) as $key) {
      if (isset($form[$key]['#type']) && !in_array($form[$key]['#type'], $excludes)) {
        if (!isset($form[$key]['#default_value']) && isset($settings[$key])) {
          $form[$key]['#default_value'] = $settings[$key];
        }
        if (!isset($form[$key]['#attributes']) && isset($form[$key]['#description'])) {
          $form[$key]['#attributes'] = ['class' => ['is-tooltip']];
        }

        if ($admin_css) {
          if ($form[$key]['#type'] == 'checkbox' && $form[$key]['#type'] != 'checkboxes') {
            $form[$key]['#field_suffix'] = '&nbsp;';
            $form[$key]['#title_display'] = 'before';
          }
          elseif ($form[$key]['#type'] == 'checkboxes' && !empty($form[$key]['#options'])) {
            foreach ($form[$key]['#options'] as $i => $option) {
              $form[$key][$i]['#field_suffix'] = '&nbsp;';
              $form[$key][$i]['#title_display'] = 'before';
            }
          }
        }
        if ($form[$key]['#type'] == 'select' && !in_array($key, ['cache', 'optionset', 'view_mode'])) {
          if (!isset($form[$key]['#empty_option']) && !isset($form[$key]['#required'])) {
            $form[$key]['#empty_option'] = t('- None -');
          }
        }

        if (!isset($form[$key]['#enforced']) && isset($definition['vanilla'])) {
          $states['visible'][':input[name*="[vanilla]"]'] = ['checked' => FALSE];
          if (isset($form[$key]['#states'])) {
            $form[$key]['#states']['visible'][':input[name*="[vanilla]"]'] = ['checked' => FALSE];
          }
          else {
            $form[$key]['#states'] = $states;
          }
        }
      }

      if (isset($form[$key]['#access']) && $form[$key]['#access'] == FALSE) {
        unset($form[$key]['#default_value']);
      }
    }

    if ($admin_css) {
      $form['#attached']['library'][] = 'blazy/admin';
    }
  }

  /**
   * Returns time in interval for select options.
   */
  public function getCacheOptions() {
    $period = [0, 60, 180, 300, 600, 900, 1800, 2700, 3600, 10800, 21600, 32400, 43200, 86400];
    $period = array_map([\Drupal::service('date.formatter'), 'formatInterval'], array_combine($period, $period));
    $period[0] = '<' . t('No caching') . '>';
    return $period + [Cache::PERMANENT => t('Permanent')];
  }

  /**
   * Returns available optionsets for select options.
   */
  public function getOptionsetOptions($entity_type = '') {
    $optionsets = [];
    if (empty($entity_type)) {
      return $optionsets;
    }

    $entities = $this->blazyManager->entityLoadMultiple($entity_type);
    foreach ((array) $entities as $entity) {
      $optionsets[$entity->id()] = Html::escape($entity->label());
    }
    asort($optionsets);
    return $optionsets;
  }

  /**
   * Returns available view modes for select options.
   */
  public function getViewModeOptions($target_type) {
    return $this->entityDisplayRepository->getViewModeOptions($target_type);
  }

}
