<?php

namespace Drupal\blazy_layout\Plugin\Layout;

/**
 * Provides a BlazyLayout class for Layout plugins.
 */
class BlazyLayout extends BlazyLayoutForm {

  /**
   * {@inheritdoc}
   */
  public function build(array $regions): array {
    $this->init();

    $build    = parent::build($regions);
    $settings = $this->settings();

    $build['#settings'] = $settings;
    $build['#count']    = static::$count;

    // Modifies output.
    $items  = $this->interpolate($settings);
    $grids  = $this->manager->toGrid($items, $settings);
    $output = $this->manager->merge($grids, $build);

    // Modifies attachments.
    $this->attachments($output, $settings);

    // Modifies attributes.
    $this->attributes($output, $settings);

    // Modifies regions.
    $this->regions($output, $settings);

    // Provides inline style.
    $this->styles($output, $settings);

    // Updates settings.
    $output['#settings'] = $settings;
    $this->setConfiguration($settings);

    return $output;
  }

  /**
   * Interpolate data from Layout Builder to extract grid attributes.
   */
  private function interpolate(array &$settings): array {
    $items   = [];
    $regions = $settings['regions'] ?? [];
    unset($regions['bg']);

    $i = 0;
    foreach ($regions as $rid => $value) {
      $box = [];
      $sets = $settings;
      $blazies = $sets['blazies']->reset($sets);

      $blazies->set('lb.rid', $rid)
        ->set('delta', $i);

      $box['#settings'] = $sets;

      // Preserves indices even if empty so to layout for Layout Builder.
      // $region && !Element::isEmpty($region) ? $region : ['#markup' => ' '];.
      $box[$rid] = $this->inPreview ? ['#markup' => '?'] : [];
      $items[] = $box;
      ++$i;
    }
    return $items;
  }

}
