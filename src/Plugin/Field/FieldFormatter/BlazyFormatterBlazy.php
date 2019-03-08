<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Component\Utility\Xss;

/**
 * Plugin implementation of the `Blazy File` or `Blazy Image` for Blazy only.
 *
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFileFormatter
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyImageFormatter
 */
class BlazyFormatterBlazy extends BlazyFileFormatterBase {

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

    // Pass first item to optimize sizes and build colorbox/zoom-like gallery.
    if (method_exists($this, 'getImageItem') && $image = $this->getImageItem($files[0])) {
      $settings['first_item'] = $image['item'];
      $settings['first_uri'] = $image['item']->uri;
    }

    // Build the settings.
    $build = ['settings' => $settings];

    // Modifies settings.
    $this->formatter->buildSettings($build, $items);

    // Build the elements.
    $this->buildElements($build, $files);

    // Pass to manager for easy updates to all Blazy formatters.
    return $this->formatter->build($build);
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
