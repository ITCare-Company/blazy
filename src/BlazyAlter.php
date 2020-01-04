<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\StringTranslation\TranslatableMarkup;

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
    if (function_exists('libraries_get_path')) {
      $libraries['blazy']['js'] = ['/' . libraries_get_path('blazy') . '/blazy.js' => ['weight' => -4]];
    }

    $blazy = \Drupal::service('blazy.manager');
    if ($blazy->configLoad('io.enabled') && $blazy->configLoad('io.unblazy')) {
      $dependencies = ['core/drupal', 'blazy/bio.media','blazy/loading'];
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

  /**
   * Implements hook_field_formatter_info_alter().
   *
   * @deprecated in blazy:8.x-2.0 and is removed from blazy:8.x-3.0. Use
   *   \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatter instead.
   * @see https://www.drupal.org/node/3103018
   */
  public static function fieldFormatterInfoAlter(array &$info) {
    // Supports optional Media Entity via VEM/VEF if available.
    $common = [
      'description' => new TranslatableMarkup('Displays lazyloaded images, or iframes, for VEF/ ME.'),
      'quickedit'   => ['editor' => 'disabled'],
      'provider'    => 'blazy',
    ];

    if (\Drupal::service('module_handler')->moduleExists('video_embed_media')) {
      $info['blazy_file'] = $common + [
        'id'          => 'blazy_file',
        'label'       => new TranslatableMarkup('Blazy Image with VEF (deprecated)'),
        'class'       => 'Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFileFormatter',
        'field_types' => ['entity_reference', 'image'],
      ];

      $info['blazy_video'] = $common + [
        'id'          => 'blazy_video',
        'label'       => new TranslatableMarkup('Blazy Video (deprecated)'),
        'class'       => 'Drupal\blazy\Plugin\Field\FieldFormatter\BlazyVideoFormatter',
        'field_types' => ['video_embed_field'],
      ];
    }
  }

}
