<?php

/**
 * @file
 * Contains \Drupal\blazy\Form\BlazyAdminBase.
 */

namespace Drupal\blazy\Form;

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
 * @see \Drupal\mason\MasonAdmin
 * @see \Drupal\slick\SlickAdmin
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
   * Defines re-usable breakpoints form.
   */
  public function breakpointsForm(array &$form, $definition = []) {
    $settings     = $definition['settings'];
    $image_styles = image_style_options(FALSE);

    $title = t('Leave Breakpoints empty to disable multi-serving images. <small>Ignored if core Responsive image is provided.</small>');
    $form['breakpoints'] = [
      '#type'       => 'table',
      '#tree'       => TRUE,
      '#header'     => [t('Breakpoint'), t('Max width'), t('Image style')],
      '#prefix'     => '<h2 class="form__title">' . $title . '</h2>',
      '#attributes' => ['class' => ['form-wrapper--table']],
    ];

    $breakpoints = $this->breakpointElements($definition);
    foreach ($breakpoints as $breakpoint => $elements) {
      foreach ($elements as $key => $element) {
        $form['breakpoints'][$breakpoint][$key] = $element;
        $form['breakpoints'][$breakpoint][$key]['#states'] = [
          'enabled' => [
            'select[name$="[responsive_image_style]"]' => ['value' => ''],
          ],
        ];
        $value = isset($settings['breakpoints'][$breakpoint][$key]) ? $settings['breakpoints'][$breakpoint][$key] : '';
        $form['breakpoints'][$breakpoint][$key]['#default_value'] = $value;
      }
    }
  }

  /**
   * Defines re-usable breakpoints form.
   */
  public function breakpointElements($definition = []) {
    if (!isset($definition['breakpoints'])) {
      return [];
    }

    $settings     = $definition['settings'];
    $image_styles = image_style_options(FALSE);

    foreach ($definition['breakpoints'] as $breakpoint) {
      $form[$breakpoint]['breakpoint'] = [
        '#markup'     => $breakpoint,
        '#weight'     => -10,
        '#wrapper_attributes' => ['class' => ['form-item--right']],
      ];

      $form[$breakpoint]['width'] = [
        '#type'          => 'textfield',
        '#title'         => t('Width'),
        '#title_display' => 'invisible',
        '#field_suffix'  => 'px',
        '#maz_length'    => 4,
        '#size'          => 6,
        '#weight'        => 2,
        '#attributes'    => ['class' => ['form-text--width']],
        '#wrapper_attributes' => ['class' => ['form-item--width']],
      ];

      $form[$breakpoint]['image_style'] = [
        '#type'          => 'select',
        '#title'         => t('Image style'),
        '#title_display' => 'invisible',
        '#options'       => $image_styles,
        '#empty_option'  => t('- None -'),
        '#weight'        => 3,
        '#wrapper_attributes' => ['class' => ['form-item--left']],
      ];
    }

    return $form;
  }

  /**
   * Returns re-usable logic, styling and assets across fields and Views.
   */
  public function finalizeForm(array &$form, $definition = []) {
    $namespace = isset($definition['namespace']) ? $definition['namespace'] : 'slick';
    $vanilla   = isset($definition['vanilla']) ? ' form--vanilla' : '';
    $fallback  = $namespace == 'slick' ? 'form--slick' : 'form--' . $namespace . ' form--slick';
    $classes   = isset($definition['form_opening_classes'])
      ? $definition['form_opening_classes']
      : $fallback . ' form--half has-tooltip' . $vanilla;

    $form['opening'] = [
      '#markup' => '<div class="' . $classes . '">',
      '#weight' => -110,
    ];

    $form['closing'] = [
      '#markup' => '</div>',
      '#weight' => 110,
    ];

    $admin_css = isset($definition['admin_css']) ? $definition['admin_css'] : '';
    $admin_css = $admin_css ?: $this->blazyManager->configLoad('admin_css', 'blazy.settings');
    $settings  = isset($definition['settings']) ? $definition['settings'] : [];
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

}
