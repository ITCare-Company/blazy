<?php

namespace Drupal\blazy_layout\Plugin\Layout;

use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Theme\Attributes;
use Drupal\blazy\Utility\Arrays;
use Drupal\blazy_layout\BlazyLayoutDefault as Defaults;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a BlazyLayoutsBase class for Layout plugins.
 */
abstract class BlazyLayoutsBase extends LayoutDefault implements BlazyLayoutsInterface {

  /**
   * The blazy layout admin service.
   *
   * @var \Drupal\blazy_layout\Form\BlazyLayoutAdminInterface
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
  protected static $count = 0;

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

    $instance->admin = $container->get('blazy_layout.admin');
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
  public function getRegionConfig($name, $key): string {
    $config = $this->configuration['regions'][$name] ?? [];
    if ($key == 'label') {
      return $config[$key] ?? '';
    }
    return $config['settings'][$key] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setRegionConfig($name, array $values): self {
    $config = $this->configuration['regions'][$name] ?? [];

    $this->configuration['regions'][$name] = $this->manager->merge($values, $config);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);

    $settings = $form_state->getValue('settings');
    $form_state->setValue(['settings', 'count'], (int) $settings['count']);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $regions = [];
    if ($values = $form_state->getValue('regions')) {
      foreach ($values as $name => $region) {
        foreach ($region as $key => $value) {
          if ($key == 'label') {
            $regions[$name][$key] = $value;
          }
          else {
            foreach ($value as $sk => $sv) {
              $regions[$name][$key][$sk] = $sv;
            }
          }
        }
      }
    }

    $this->configuration['regions'] = $regions;

    if ($settings = $form_state->getValue('settings')) {
      foreach ($settings as $key => $value) {
        $this->configuration[$key] = $value;
      }
      unset($this->configuration['settings']);
    }
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

    $form       = parent::buildConfigurationForm($form, $form_state2);
    $config     = $this->getConfiguration();
    $definition = $this->pluginDefinition;
    $settings   = [];

    $form['settings'] = [
      '#type'        => 'details',
      '#tree'        => TRUE,
      '#open'        => TRUE,
      // '#weight'      => 30,
      '#title'       => $this->t('Global settings'),
      '#description' => $this->t('Use Blazy Image/ Media formatters to have background or even nested grids when creating blocks.'),
      '#parents'     => ['layout_settings', 'settings'],
    ];

    // The main grid setttings.
    foreach (Defaults::layoutSettings() as $key => $value) {
      $default = $config[$key] ?? $value;
      $settings[$key] = $this->configuration['settings'][$key] ?? $default;
    }

    // @todo enable:row_classes.
    $excludes = ['regions', 'attributes', 'row_classes'];
    $excludes = array_combine($excludes, $excludes);
    $this->admin->formBase($form['settings'], $settings, $excludes);
    $this->admin->formSettings($form['settings'], $settings, $excludes);

    $arguments = [
      'grid_simple' => TRUE,
      'no_grid_header' => TRUE,
      'blazy_layout' => TRUE,
    ];

    $grid_form = [];
    $this->admin->gridForm($grid_form, $arguments);

    foreach ($grid_form as $key => $element) {
      $form['settings'][$key] = $element;
      $form['settings'][$key]['#default_value'] = $settings[$key];
      // $form['settings'][$key]['#weight'] = 10;
      if ($key == 'grid') {
        if (isset($form['settings'][$key]['#description'])) {
          $form['settings'][$key]['#description'] .= $this->admin->nativeGridDescription();
        }
      }
    }

    foreach (Element::children($form['settings']) as $key) {
      $parents = ['layout_settings', 'settings', $key];
      $this->admin->themeDescription($form['settings'][$key], $parents);

      if (isset($form['settings'][$key]['#weight'])) {
        unset($form['settings'][$key]['#weight']);
      }
    }

    // Region settings.
    $defined = $definition->getRegions();
    $regions = $this->manager->getRegions((int) $settings['count']);

    $subsets = [];
    $form['regions'] = [
      '#type'    => 'container',
      '#tree'    => TRUE,
      '#parents' => ['layout_settings', 'regions'],
      '#weight'  => 31,
    ];

    foreach ($regions as $region => $info) {
      $delta = $info['delta'];
      $subsets = [];

      foreach (Defaults::regionSettings() as $key => $value) {
        if ($key == 'label') {
          $fallback = $defined[$region]['label'] ?? Defaults::regionLabel($delta);
          $default = $config['regions'][$region][$key] ?? $fallback;
          $subsets['regions'][$region][$key] = $default ?: $value;
        }
        else {
          foreach ($value as $sk => $sv) {
            $default = $config['regions'][$region][$key][$sk] ?? $sv;
            $subsets['regions'][$region][$key][$sk] = $default;
          }
        }
      }

      $subsets2 = $subsets['regions'][$region];
      // $subsets2['label'] = $subsets2['label'] ??
      $label = $this->t('@label: <em>@name</em>', [
        '@label' => $info['label'],
        '@name'  => $subsets2['label'] ?? $this->t('No name'),
      ]);

      $form['regions'][$region] = [
        '#type'    => 'details',
        '#title'   => $label,
        '#open'    => FALSE,
        '#tree'    => TRUE,
        '#parents' => ['layout_settings', 'regions', $region],
      ];

      $form['regions'][$region]['label'] = [
        '#type'          => 'textfield',
        '#title'         => $this->t('Region name'),
        '#default_value' => $subsets2['label'],
        '#description'   => $this->t('The human-readable region name for theming.'),
      ];

      $form['regions'][$region]['settings'] = [
        '#type'    => 'details',
        '#title'   => $this->t('Settings'),
        '#open'    => TRUE,
        '#tree'    => TRUE,
        '#parents' => ['layout_settings', 'regions', $region, 'settings'],
      ];

      $subsets3 = $subsets2['settings'];
      $this->admin->formWrappers($form['regions'][$region]['settings'], $subsets3, [], FALSE);
    }

    $form['settings']['#attached']['library'][] = 'blazy_layout/admin';
    return $form;
  }

  /**
   * Provides attachments and cache common for all blazy-related modules.
   */
  protected function attachments(
    array &$element,
    array $settings,
    array $attachments = []
  ): void {
    $this->manager->setAttachments(
      $element,
      $settings,
      $attachments
    );

    $element['#attached']['library'][] = 'blazy_layout/layout';
    if ($this->inPreview) {
      $element['#attached']['library'][] = 'blazy_layout/admin';
    }
  }

  /**
   * Initialize dynamic layout regions.
   */
  protected function init() {
    $layout = clone $this->pluginDefinition;
    $settings = $this->getConfiguration();
    $factory_regions = $layout->getRegions();
    $keys = array_values($factory_regions);
    $count = (int) $settings['count'];
    static::$count = $count;

    // Add new regions, if any different from factory.
    foreach (range(1, static::$count) as $delta => $value) {
      $key = Defaults::regionId($delta);
      $region = $keys[$delta] ?? $delta;

      if (is_int($region) && $region == $delta) {
        $label = Defaults::regionLabel($delta);
        $factory_regions[$key] = [
          'label' => Defaults::regionTranslatableLabel($label),
        ];
      }
    }

    $factory_regions['bg'] = [
      'label' => Defaults::regionTranslatableLabel('Background'),
    ];

    $this->setConfiguration($settings);
    $layout->setRegions($factory_regions);
    $this->pluginDefinition = $layout;
    return $layout;
  }

  /**
   * Returns settings.
   */
  protected function settings(): array {
    $settings = $this->getConfiguration();
    $settings['blazy_layout'] = TRUE;

    $this->manager->verifySafely($settings);
    $this->manager->preSettings($settings);

    $settings = $this->manager->toSettings($settings);
    $blazies  = $settings['blazies'];

    $blazies->set('namespace', static::$namespace)
      ->set('is.grid', TRUE)
      ->set('is.lb', TRUE)
      ->set('lb.regions', $settings['regions'])
      ->set('item.id', static::$itemId)
      ->set('item.prefix', static::$itemPrefix)
      ->set('item.caption', static::$captionId)
      ->set('count', static::$count);

    $this->manager->postSettings($settings);

    $settings = array_diff_key($settings, BlazyDefault::imageSettings());
    $settings = Arrays::filter($settings);

    return $settings;
  }

  /**
   * Modifies regions.
   */
  protected function regions(array &$output, array &$settings): void {
    $layout = $this->pluginDefinition;
    $factory_regions = $layout->getRegions();
    $dummy_regions = $output['#regions'] ?? [];
    $default_regions = array_keys($factory_regions);
    $active_regions = array_keys(array_diff_key($dummy_regions, $default_regions));
    $new_regions = [];

    // Add dummy regions to keep layout intact.
    foreach (range(1, static::$count) as $delta => $value) {
      $name = Defaults::regionId($delta);

      if ($subsets = $settings['regions'][$name]['settings'] ?? []) {
        if ($classes = $this->getClasses($subsets)) {
          $settings['regions'][$name]['settings']['classes'] = $classes;
        }
        if (empty($output[$name])) {
          $settings['regions'][$name]['settings']['empty'] = TRUE;
        }
      }

      if (!isset($output[$name]) && $this->inPreview) {
        $label = Defaults::regionLabel($delta);
        $output[$name]['dummy']['#markup'] = '';
      }
    }

    if (empty($output['bg'])) {
      $settings['regions']['bg']['settings']['empty'] = TRUE;
      if ($this->inPreview) {
        $output['bg']['dummy']['#markup'] = '';
      }
    }

    // Add or remove regions based on the given settings.count.
    $keys = array_filter($output, fn($k) => strpos($k, '#') === FALSE, ARRAY_FILTER_USE_KEY);
    foreach (array_keys($keys) as $delta => $name) {
      if (isset($output[$name])) {

        // Provides dummy regions.
        if (array_key_exists($delta, $active_regions)) {
          $label = Defaults::regionLabel($delta);
          $new_regions[$name] = [
            'label' => Defaults::regionTranslatableLabel($label),
          ];
        }
        else {
          if ($name != 'bg') {
            unset($output[$name]);
          }
        }
      }
    }

    // Add a special bg region.
    $new_regions['bg'] = [
      'label' => Defaults::regionTranslatableLabel('Background'),
    ];

    if ($new_regions) {
      $this->blocks($output, $settings, $new_regions);

      ksort($new_regions);
      $this->pluginDefinition->setRegions($new_regions);
    }
  }

  /**
   * Modifies blocks.
   */
  protected function blocks(array &$output, array &$settings, array $new_regions): void {
    // Move Blazy background to the beginning.
    foreach (array_keys($new_regions) as $name) {
      if (!isset($output[$name])) {
        continue;
      }

      foreach (Element::children($output[$name]) as $uuid) {
        $block = $output[$name][$uuid];
        $formatter = $block['content'][0]['#formatter'] ?? 'x';

        if (strpos($formatter, 'blazy') !== FALSE) {
          if ($fielsets = $block['content'][0]['#blazy'] ?? []) {
            // Pass the layout settings, not formatter's.
            $subsets = $settings;
            $blazies = $subsets['blazies']->reset($subsets);
            $subblazies = $fielsets['blazies'];
            $output[$name][$uuid]['#blazy'] = $subsets;

            if (!empty($fielsets['background'])) {
              $blazies->set('is.preview', $this->inPreview)
                ->set('use.bg', TRUE)
                ->set('lb.region', $name);

              $keys = ['entity', 'field', 'image', 'lightbox', 'media'];
              foreach ($keys as $key) {
                $blazies->set($key, $subblazies->get($key));
              }

              if ($name == 'bg') {
                $output[$name][$uuid]['content'][0][0]['#build']['overlay']['blazy_layout']['#markup'] = '<div class="media__overlay"></div>';
              }

              $settings['regions'][$name]['settings']['background'] = TRUE;
              $this->setRegionConfig($name, [
                'settings' => [
                  'background' => TRUE,
                ],
              ]);
              $output[$name][$uuid]['#weight'] = -101;
            }
          }
        }
      }
    }
  }

  /**
   * Modifies attributes.
   */
  protected function attributes(array &$output, array $settings): void {
    if (!isset($output['#attributes'])) {
      $output['#attributes'] = [];
    }

    $css = '';
    foreach (['grid_auto_rows', 'align_items'] as $option) {
      if ($value = $settings[$option] ?? NULL) {
        $key = str_replace('_', '-', $option);
        $value = trim($value);
        $css .= $key . ':' . $value . ';';
      }
    }

    if ($css) {
      Attributes::inlineStyle($output['#attributes'], $css);
    }

    $this->parseClasses($output, $settings);
  }

  /**
   * Returns CSS classes.
   */
  protected function getClasses(array $settings): array {
    if ($classes = $settings['classes'] ?? '') {
      $classes = array_map(
        '\Drupal\Component\Utility\Html::cleanCssIdentifier',
        explode(' ', $classes)
      );
      return array_filter($classes);
    }
    return [];
  }

  /**
   * Modifies output classes.
   */
  protected function parseClasses(array &$output, array $settings): void {
    if ($classes = $this->getClasses($settings)) {
      foreach ($classes as $class) {
        $output['#attributes']['class'][] = $class;
      }
    }
  }

}
