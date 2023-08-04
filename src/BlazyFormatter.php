<?php

namespace Drupal\blazy;

use Drupal\blazy\Media\Preloader;
use Drupal\blazy\Utility\Check;

/**
 * Provides common image, file, media formatter-related methods.
 */
class BlazyFormatter extends BlazyManager implements BlazyFormatterInterface {

  /**
   * {@inheritdoc}
   */
  protected static $namespace = 'blazy';

  /**
   * {@inheritdoc}
   */
  protected static $itemId = 'content';

  /**
   * {@inheritdoc}
   */
  protected static $itemPrefix = 'blazy';

  /**
   * {@inheritdoc}
   */
  public function fieldSettings(array &$settings, $items) {
    Check::fields($settings, $items);
  }

  /**
   * {@inheritdoc}
   *
   * @todo make it protected after sub-modules, mostly are just tests + BVEF.
   */
  public function buildSettings(array &$build, $items) {
    $this->hashtag($build);
    $settings = &$build['#settings'];

    // BC for mismatched minor versions.
    $this->verify($settings);

    $blazies = $settings['blazies'];
    $entity  = $items->getEntity();

    // @todo remove after sub-modules.
    if (!empty($settings['item_id'])) {
      foreach (['item_id', 'namespace'] as $key) {
        if (!empty($settings[$key])) {
          $k = str_replace('_', '.', $key);
          $blazies->set($k, $settings[$key]);
        }
      }
    }

    // BVEF compat due to its ::viewElements being left behind.
    // @todo remove once BVEF is updated to Blazy:2.10.
    if (!$blazies->was('initialized')) {
      $this->preSettings($settings);
      Preloader::prepare($settings, $items);
      $this->postSettings($settings);
    }

    $build['#entity'] = $entity;
    $this->prepareData($build);
    $this->fieldSettings($settings, $items);

    // Minor byte saving.
    if (!empty($settings['caption'])) {
      $settings['caption'] = array_filter($settings['caption']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preBuildElements(array &$build, $items, array $entities = []) {
    $this->hashtag($build);
    $settings = &$build['#settings'];

    // BC for mismatched minor versions.
    $this->verify($settings);

    $blazies   = $settings['blazies'];
    $plugin_id = $blazies->get('field.plugin_id');

    // BC for non-nego vanilla formatters identified by its plugin ID.
    if ($plugin_id && strpos($plugin_id, 'vanilla') !== FALSE) {
      $settings['vanilla'] = TRUE;
      $blazies->set('is.vanilla', TRUE);
    }

    // Extracts initial settings:
    // - Container or root level settings: lightboxes, grids, etc.
    // - Map (Responsive) image style option to its entity, etc.
    // - Lazy load decoupled via `No JavaScript: lazy`, etc.
    $this->preSettings($settings);

    // Extracts the first image item to build colorbox/zoom-like gallery.
    // Also prepare URIs for the new Preload option.
    // Requires image style entity from above.
    Preloader::prepare($settings, $items, $entities);

    // Extracts (Responsive) image dimensions, requires first.uri above.
    $this->postSettings($settings);

    // @todo remove after sub-modules hook_alters at 3.x.
    $build['settings'] = &$settings;

    // Allows altering the presettings once for the entire ecosystem.
    // Has the needed settings above to modify sub-modules ::buildSettings().
    $this->moduleHandler->alter('blazy_presettings', $settings, $items, $entities);

    // Extended by sub-modules with data massaged above.
    $this->buildSettings($build, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function preElements(array &$build, $items, array $entities = []): void {
    $this->preBuildElements($build, $items, $entities);

    $settings = &$build['#settings'];

    // Since 2.17, allows altering the settings once for the entire ecosystem,
    // rather than each hook_alter for every modules.
    // The $build contains #settings, or potential #optionset for sub-modules.
    $this->moduleHandler->alter('blazy_settings', $build, $items, $entities);

    // Combines settings with the provided hook_alter().
    $this->postSettingsAlter($settings, $items->getEntity());
  }

  /**
   * {@inheritdoc}
   */
  public function postBuildElements(array &$build, $items, array $entities = []) {
    // Do nothing.
  }

}
