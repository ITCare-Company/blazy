<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * A Trait common for all blazy formatters.
 */
trait BlazyFormatterViewTrait {

  /**
   * Returns similar view elements.
   */
  public function commonViewElements(FieldItemListInterface $items, $langcode, array $entities = [], array $settings = []) {
    // Collects specific settings to this formatter.
    $defaults = $this->buildSettings();
    $settings = $settings ? array_merge($defaults, $settings) : $defaults;
    $settings['langcode'] = $langcode;

    $this->preSettings($settings);

    // Build the settings.
    $build = ['settings' => $settings];

    // Modifies settings before building elements.
    $entities = empty($entities) ? [] : array_values($entities);
    $this->formatter->preBuildElements($build, $items, $entities);

    // Build the elements.
    $elements = $entities ?: $items;
    $this->buildElements($build, $elements, $langcode);

    // Modifies settings post building elements.
    $this->formatter->postBuildElements($build, $items, $entities);

    // Pass to manager for easy updates to all Blazy formatters.
    return $this->formatter->build($build);
  }

  /**
   * Prepare the settings, allows sub-modules to re-use and override.
   */
  protected function preSettings(array &$settings) {
    $blazies = $settings['blazies'];

    // Marks this formatter as blazy specific.
    if ($blazies->get('namespace') == 'blazy') {
      $this->blazySettings($settings);
    }
  }

}
