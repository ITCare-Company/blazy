<?php

namespace Drupal\blazy;

use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Media\BlazyResponsiveImage;
use Drupal\blazy\Media\Preloader;
use Drupal\blazy\Utility\Check;

/**
 * Provides common image, file, media formatter-related methods.
 */
class BlazyFormatter extends BlazyManager implements BlazyFormatterInterface {

  /**
   * {@inheritdoc}
   */
  public function fieldSettings(array &$build, $items) {
    Check::fields($build, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettings(array &$build, $items) {
    $settings = &$build['settings'];
    $entity   = $items->getEntity();

    $this->preSettings($settings);
    $this->prepareData($build, $entity);
    $this->postSettings($settings);
    $this->fieldSettings($build, $items);

    // Minor byte saving.
    if (!empty($settings['caption'])) {
      $settings['caption'] = array_filter($settings['caption']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preBuildElements(array &$build, $items, array $entities = []) {
    $this->buildSettings($build, $items);

    $settings = &$build['settings'];
    $blazies = $settings['blazies'];

    // Pass first item to optimize sizes this time.
    // Extract the first image item to build colorbox/zoom-like gallery.
    // Also prepare URIs for the new Preload option.
    // @tdo remove condition after another check.
    if (!empty($items[0])) {
      Preloader::prepare($settings, $items, $entities);

      // Sets dimensions once, if cropped, to reduce costs with ton of images.
      // This is less expensive than re-defining dimensions per image.
      if ($blazies->get('first.uri')) {
        if ($blazies->get('resimage.style')) {
          BlazyResponsiveImage::dimensionsAndSources($settings, TRUE);
        }
        elseif ($style = $blazies->get('image.style')) {
          BlazyImage::cropDimensions($settings, $style);
        }
      }
    }

    // Allows altering the settings.
    $this->getModuleHandler()->alter('blazy_settings', $build, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function postBuildElements(array &$build, $items, array $entities = []) {
    // Do nothing.
  }

}
