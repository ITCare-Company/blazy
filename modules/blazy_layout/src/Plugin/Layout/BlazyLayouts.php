<?php

namespace Drupal\blazy_layout\Plugin\Layout;

use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Theme\Attributes;
use Drupal\blazy\Utility\Arrays;
use Drupal\blazy_layout\BlazyLayoutDefault as Defaults;
use Drupal\Core\Render\Element;

/**
 * Provides a BlazyLayouts class for Layout plugins.
 */
class BlazyLayouts extends BlazyLayoutsBase {

  /**
   * {@inheritdoc}
   */
  public function build(array $regions): array {
    $this->init();

    $build    = parent::build($regions);
    $settings = $this->settings();

    $build['#settings'] = $settings;
    $build['#count'] = (int) $settings['count'];

    // Modifies output.
    $items  = $this->interpolate($settings, $regions);
    $grids  = $this->manager->toGrid($items, $settings);
    $output = $this->manager->merge($grids, $build);

    // Modifies attachments.
    $this->attachments($output, $settings);

    // Modifies regions.
    $this->regions($output, $settings);

    // Modifies attributes.
    $this->attributes($output, $settings);

    // Updates settings.
    $output['#settings'] = $settings;

    return $output;
  }

  /**
   * Provides attachments and cache common for all blazy-related modules.
   */
  protected function attachments(
    array &$element,
    array $settings,
    array $attachments = []
  ): void {
    $cache                 = $this->manager->getCacheMetadata($settings);
    $attached              = $this->manager->attach($settings);
    $attachments           = $this->manager->merge($attached, $attachments);
    $element['#attached']  = $this->manager->merge($attachments, $element, '#attached');
    $element['#cache']     = $this->manager->merge($cache, $element, '#cache');
    $element['#namespace'] = static::$namespace;

    if ($this->inPreview) {
      $element['#attached']['library'][] = 'blazy_layout/admin';
    }
  }

  /**
   * Initialize dynamic layout regions.
   */
  protected function init(): void {
    $settings = $this->getConfiguration();
    $layout = $this->pluginDefinition;
    $factory_regions = $layout->getRegions();
    $keys = array_values($factory_regions);
    $count = (int) $settings['count'];

    // Add new regions, if any different from factory.
    foreach (range(1, $count) as $delta => $value) {
      $key = Defaults::regionId($delta);
      $region = $keys[$delta] ?? $delta;

      if ($region == $delta) {
        $label = Defaults::regionLabel($delta);
        $factory_regions[$key] = [
          'label' => Defaults::regionTranslatableLabel($label),
        ];
      }
      // @fixme useless here.
      // $regions[$key]['dummy'] = ['#markup' => ' '];
    }

    $layout->setRegions($factory_regions);
  }

  /**
   * Returns settings.
   */
  protected function settings(): array {
    $settings = $this->getConfiguration();
    $settings['blazy_layout'] = TRUE;
    $count = (int) $settings['count'];

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
      ->set('count', $count);

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
    $used_regions = [];
    $count = (int) $settings['count'];

    // Add dummy regions to keep layout intact.
    foreach (range(1, $count) as $delta => $value) {
      $name = Defaults::regionId($delta);
      if (!isset($output[$name])) {
        $output[$name]['dummy']['#markup'] = '';
      }
    }

    foreach (Element::children($output) as $delta => $name) {
      if (isset($output[$name])) {

        // Provides dummy regions.
        if (array_key_exists($delta, $active_regions)) {
          $label = Defaults::regionLabel($delta);
          $used_regions[$name] = [
            'label' => Defaults::regionTranslatableLabel($label),
          ];

          // Move Blazy background to the beginning.
          foreach (Element::children($output[$name]) as $uuid) {
            $block = $output[$name][$uuid];

            if ($formatter = $block['content'][0]['#formatter'] ?? '') {
              if (strpos($formatter, 'blazy') !== FALSE) {
                if ($fielsets = $block['content'][0]['#blazy'] ?? []) {
                  $output[$name][$uuid]['#blazy'] = $settings;
                  $bg = $fielsets['background'] ?? FALSE;

                  if ($bg) {
                    $settings['regions'][$name]['background'] = TRUE;
                    $output[$name][$uuid]['#weight'] = -101;
                  }
                }
              }
            }
          }
        }
        else {
          unset($output[$name]);
        }
      }
    }

    if ($used_regions) {
      ksort($used_regions);
      $this->pluginDefinition->setRegions($used_regions);
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
  }

  /**
   * Interpolate data from Layout Builder to match original construct.
   */
  private function interpolate(
    array &$settings,
    array $regions
  ): array {
    $id     = 'box';
    $items  = [];
    $count  = $settings['count'];
    $checks = $this->manager->getRegions($count);

    foreach (range(0, $settings['count'] - 1) as $delta) {
      $rid = Defaults::regionId($delta);
      $box = [];

      // Remove top level settings to avoid leaking due to similarity.
      $sets = array_diff_key($settings, Defaults::regionSettings());
      $blazies = $sets['blazies']->reset($sets);
      $blazies->set('lb.rid', $rid);

      $box['#settings'] = $sets;

      if ($dummy = $checks[$rid]) {
        $regions[$rid]['#region'] = $dummy;
      }

      // Preserves indices even if empty so to layout for Layout Builder.
      // $region = $regions[$rid] ?? NULL;
      // $region && !Element::isEmpty($region) ? $region : ['#markup' => ' '];.
      $box[$id] = ['#markup' => ' '];
      $items[] = $box;
    }
    return $items;
  }

}
