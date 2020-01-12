<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Unicode;

/**
 * Provides hook_alter() methods for Blazy.
 */
class BlazyAlter {

  /**
   * Implements hook_config_schema_info_alter().
   */
  public static function configSchemaInfoAlter(array &$definitions, $formatter = 'blazy_base', array $settings = []) {
    if (isset($definitions[$formatter])) {
      $mappings = &$definitions[$formatter]['mapping'];
      $settings = $settings ?: BlazyDefault::extendedSettings() + BlazyDefault::gridSettings();
      foreach ($settings as $key => $value) {
        // Seems double is ignored, and causes a missing schema, unlike float.
        $type = gettype($value);
        $type = $type == 'double' ? 'float' : $type;
        $mappings[$key]['type'] = $key == 'breakpoints' ? 'mapping' : (is_array($value) ? 'sequence' : $type);

        if (!is_array($value)) {
          $mappings[$key]['label'] = Unicode::ucfirst(str_replace('_', ' ', $key));
        }
      }

      if (isset($mappings['breakpoints'])) {
        foreach (BlazyDefault::getConstantBreakpoints() as $breakpoint) {
          $mappings['breakpoints']['mapping'][$breakpoint]['type'] = 'mapping';
          foreach (['breakpoint', 'width', 'image_style'] as $item) {
            $mappings['breakpoints']['mapping'][$breakpoint]['mapping'][$item]['type']  = 'string';
            $mappings['breakpoints']['mapping'][$breakpoint]['mapping'][$item]['label'] = Unicode::ucfirst(str_replace('_', ' ', $item));
          }
        }
      }
    }
  }

  /**
   * Implements hook_library_info_alter().
   */
  public static function libraryInfoAlter(&$libraries, $extension) {
    if (blazy_libraries_get_path('blazy')) {
      $libraries['blazy']['js'] = ['/' . blazy_libraries_get_path('blazy') . '/blazy.js' => ['weight' => -4]];
    }

    $blazy = \Drupal::service('blazy.manager');
    if ($blazy->configLoad('io.enabled') && $blazy->configLoad('io.unblazy')) {
      $dependencies = ['core/drupal', 'blazy/bio.media', 'blazy/loading'];
      $libraries['load']['dependencies'] = $dependencies;
    }
  }

  /**
   * Implements hook_blazy_attach_alter().
   */
  public static function blazyAttachAlter(array &$load, $attach = []) {
    // Intentionally on the second line to not hit it till required.
    if (function_exists('colorbox_theme')) {
      $dummy = [];
      \Drupal::service('colorbox.attachment')->attach($dummy);
      $load = isset($dummy['#attached']) ? NestedArray::mergeDeep($load, $dummy['#attached']) : $load;
      $load['library'][] = 'blazy/colorbox';
      unset($dummy);
    }
  }

  /**
   * Implements hook_blazy_settings_alter().
   */
  public static function blazySettingsAlter(array &$build, $items) {
    $settings = &$build['settings'];

    // Sniffs for Views to allow block__no_wrapper, views_no_wrapper, etc.
    if (function_exists('views_get_current_view') && $view = views_get_current_view()) {
      $settings['view_name'] = $view->storage->id();
      $settings['current_view_mode'] = $view->current_display;
    }
  }

}
