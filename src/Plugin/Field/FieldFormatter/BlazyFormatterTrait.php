<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Component\Utility\Xss;
use Drupal\blazy\BlazyGrid;

/**
 * A Trait common for blazy image and file ER formatters.
 */
trait BlazyFormatterTrait {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $build = [];
    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return $build;
    }

    // Collects specific settings to this formatter.
    $settings              = $this->buildSettings();
    $settings['blazy']     = TRUE;
    $settings['namespace'] = $settings['item_id'] = $settings['lazy'] = 'blazy';
    $settings['_grid']     = !empty($settings['style']) && !empty($settings['grid']);
    $settings['langcode']  = $langcode;

    // Build the settings.
    $build = ['settings' => $settings];

    // Modifies settings.
    $this->formatter->buildSettings($build, $items);

    // Build the elements.
    $this->buildElements($build, $files);

    // Updates settings.
    $settings = $build['settings'];
    unset($build['settings']);

    // Supports Blazy multi-breakpoint images if provided.
    $this->formatter->isBlazy($settings, $build[0]['#build']);

    // Build grid if provided.
    if (empty($settings['_grid'])) {
      $build['#blazy'] = $settings;
    }
    else {
      $build = BlazyGrid::build($build, $settings);
    }

    $build['#attached'] = $this->formatter->attach($settings);
    return $build;
  }

  /**
   * Build the Blazy elements.
   */
  public function buildElements(array &$build, $files) {
    $settings = $build['settings'];

    foreach ($files as $delta => $file) {
      /* @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      $item = $file->_referringItem;

      $settings['delta']     = $delta;
      $settings['file_tags'] = $file->getCacheTags();
      $settings['type']      = 'image';
      $settings['uri']       = $file->getFileUri();
      $box['item']           = $item;
      $box['settings']       = $settings;

      // If imported Drupal\blazy\Dejavu\BlazyVideoTrait.
      $this->buildElement($box, $file);

      // Build caption if so configured.
      if (!empty($settings['caption'])) {
        foreach ($settings['caption'] as $caption) {
          if ($caption_content = $box['item']->{$caption}) {
            $box['captions'][$caption] = ['#markup' => Xss::filterAdmin($caption_content)];
          }
        }
      }

      // Image with grid, responsive image, lazyLoad, and lightbox supports.
      $build[$delta] = $this->formatter->getBlazy($box);
      unset($box);
    }
  }

}
