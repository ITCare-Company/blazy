<?php

namespace Drupal\blazy;

use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Media\BlazyResponsiveImage;
use Drupal\blazy\Utility\Check;

/**
 * Provides common field formatter-related methods: Blazy, Slick.
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
    if (!empty($items[0])) {
      $this->uris($settings, $items, $entities);
    }

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

    // Allows altering the settings.
    $this->getModuleHandler()->alter('blazy_settings', $build, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function postBuildElements(array &$build, $items, array $entities = []) {
    // Do nothing.
  }

  /**
   * Extract the first image item to build colorbox/zoom-like gallery.
   *
   * @todo move it into BlazyManagerBase if usable outside formatters.
   */
  protected function uris(array &$settings, $items, array $entities = []) {
    $blazies = $settings['blazies'];

    BlazyFile::urisFromField($settings, $items, $entities);

    // The first image dimensions to differ from individual item dimensions.
    // @todo merge it into BlazyFile::urisFromField to swap them all once.
    if ($item = $blazies->get('first.item')) {
      BlazyImage::dimensions($settings, $item, TRUE);
    }
  }

}
