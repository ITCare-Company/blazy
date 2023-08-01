<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\FormatterInterface;
use Drupal\editor\Entity\Editor;

/**
 * Provides hook_alter() methods for Blazy.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class BlazyAlter {

  /**
   * The blazy library info.
   *
   * @var array
   */
  private static $libraryInfoBuild;

  /**
   * Implements hook_config_schema_info_alter().
   */
  public static function configSchemaInfoAlter(
    array &$definitions,
    $formatter = 'blazy_base',
    array $settings = []
  ): void {
    if (isset($definitions[$formatter])) {
      $mappings = &$definitions[$formatter]['mapping'];
      $settings = $settings ?: BlazyDefault::extendedSettings() + BlazyDefault::gridSettings();
      $settings += BlazyDefault::svgSettings();
      $settings += BlazyDefault::deprecatedSettings();
      $settings += BlazyDefault::nonBlazySettings();

      foreach ($settings as $key => $value) {
        // Seems double is ignored, and causes a missing schema, unlike float.
        $type = gettype($value);
        $type = $type == 'double' ? 'float' : $type;
        $mappings[$key]['type'] = $key == 'breakpoints' ? 'mapping' : (is_array($value) ? 'sequence' : $type);

        if (!is_array($value)) {
          $mappings[$key]['label'] = Unicode::ucfirst(str_replace('_', ' ', $key));
        }
      }

      // @todo remove custom breakpoints anytime before 3.x as per #3105243.
      if (isset($mappings['breakpoints'])) {
        foreach (['xs', 'sm', 'md', 'lg', 'xl'] as $breakpoint) {
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
  public static function libraryInfoAlter(&$libraries, $extension): void {
    // @todo remove if core changed, right below core/drupal for being generic,
    // and dependency-free and a dependency for many other generic ones.
    // @todo watch out for core @todo to remove drupal namespace for debounce.
    $debounce = 'drupal.debounce';
    if ($extension === 'core' && isset($libraries[$debounce])) {
      $libraries[$debounce]['js']['misc/debounce.js'] = ['weight' => -16];
    }

    if ($extension === 'media' && isset($libraries['oembed.frame'])) {
      $libraries['oembed.frame']['dependencies'][] = 'blazy/oembed';
    }

    if ($extension === 'blazy') {
      if ($manager = Blazy::service('blazy.manager')) {
        $names = ['DOMPurify', 'dompurify'];
        if ($path = $manager->getLibrariesPath($names)) {
          $js = [
            '/' . $path . '/dist/purify.min.js' => [
              'minified' => TRUE,
              'weight' => -16,
            ],
          ];
          $libraries['dompurify']['js'] = $js;
          $libraries['dblazy']['dependencies'][] = 'blazy/dompurify';
        }
      }
    }
  }

  /**
   * Implements hook_library_info_build().
   */
  public static function libraryInfoBuild() {
    if (!isset(self::$libraryInfoBuild)) {
      $libraries = [];
      // Optional polyfills for IEs, and oldies.
      $polyfills = array_merge(BlazyDefault::polyfills(), BlazyDefault::ondemandPolyfills());
      foreach ($polyfills as $id) {
        // Matches common core polyfills' weight.
        $weight = $id == 'polyfill' ? -21 : -20;
        $weight = $id == 'webp' ? -5.5 : $weight;
        $common = ['minified' => TRUE, 'weight' => $weight];
        $libraries[$id] = [
          'js' => [
            'js/polyfill/blazy.' . $id . '.min.js' => $common,
          ],
        ];

        if ($id == 'webp') {
          $libraries[$id]['dependencies'][] = 'blazy/dblazy';
        }
      }

      // Plugins extending dBlazy.
      foreach (BlazyDefault::plugins() as $id) {
        // @todo remove css + dom post sub-module updates at 2.18+.
        $base = ['eventify', 'viewport', 'dataset', 'css', 'dom'];
        $base = in_array($id, $base);
        $deps = $base ? ['blazy/dblazy', 'blazy/base'] : ['blazy/xlazy'];
        if ($id == 'xlazy') {
          $deps = ['blazy/viewport', 'blazy/dataset'];
        }

        // @todo problematic weight, basically compat must be present.
        if (in_array($id, ['animate', 'background'])) {
          $deps[] = 'blazy/compat';
        }
        $weight = $base ? -5.6 : -5.5;

        // @todo remove, integrated into dblazy since 2.17.
        if ($id == 'dom') {
          $weight = -5.9;
        }
        $common = ['minified' => TRUE, 'weight' => $weight];
        $libraries[$id] = [
          'js' => [
            'js/plugin/blazy.' . $id . '.min.js' => $common,
          ],
          'dependencies' => $deps,
        ];
      }

      self::$libraryInfoBuild = $libraries;
    }
    return self::$libraryInfoBuild;
  }

  /**
   * Checks if Entity/Media Embed is enabled.
   */
  public static function isCkeditorApplicable(Editor $editor): bool {
    foreach (['entity_embed', 'media_embed'] as $filter) {
      if (!$editor->isNew()
        && $editor->getFilterFormat()->filters()->has($filter)
        && $editor->getFilterFormat()
          ->filters($filter)
          ->getConfiguration()['status']) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Implements hook_ckeditor_css_alter().
   */
  public static function ckeditorCssAlter(array &$css, Editor $editor): void {
    if (self::isCkeditorApplicable($editor)) {
      $path = Blazy::getPath('module', 'blazy', TRUE);
      $css[] = $path . '/css/components/blazy.media.css';
      $css[] = $path . '/css/components/blazy.preview.css';
      $css[] = $path . '/css/components/blazy.ratio.css';
    }
  }

  /**
   * Provides the third party formatters where full blown Blazy is not worthy.
   *
   * The module doesn't automatically convert the relevant theme to use Blazy,
   * however two attributes are provided: `data-b-lazy` and `data-b-preview`
   * which can be used to override a particular theme to use Blazy.
   *
   * The `data-b-lazy`is a flag indicating Blazy is enabled.
   * The `data-b-preview` is a flag indicating Blazy in CKEditor preview mode
   * via Entity/Media Embed which normally means Blazy should be disabled
   * due to CKEditor not supporting JS assets.
   *
   * @see \Drupal\blazy\Theme\BlazyTheme::blazy()
   * @see \Drupal\blazy\Theme\BlazyTheme::field()
   * @see \Drupal\blazy\Theme\BlazyTheme::fileVideo()
   * @see blazy_preprocess_file_video()
   */
  public static function thirdPartyFormatters(): array {
    $formatters = ['file_video'];
    if ($manager = Blazy::service('blazy.manager')) {
      $manager->moduleHandler()->alter('blazy_third_party_formatters', $formatters);
    }
    return array_unique($formatters);
  }

  /**
   * Implements hook_field_formatter_third_party_settings_form().
   */
  public static function fieldFormatterThirdPartySettingsForm(FormatterInterface $plugin): array {
    if (in_array($plugin->getPluginId(), self::thirdPartyFormatters())) {
      return [
        'blazy' => [
          '#type' => 'checkbox',
          '#title' => 'Blazy',
          '#default_value' => $plugin->getThirdPartySetting('blazy', 'blazy', FALSE),
        ],
      ];
    }
    return [];
  }

  /**
   * Implements hook_field_formatter_settings_summary_alter().
   */
  public static function fieldFormatterSettingsSummaryAlter(array &$summary, $context): void {
    if ($formatter = $context['formatter']) {
      $on = $formatter->getThirdPartySetting('blazy', 'blazy', FALSE);
      if ($on && in_array($formatter->getPluginId(), self::thirdPartyFormatters())) {
        $summary[] = 'Blazy';
      }

      // Provide removal message, applicable to all Blazy ecosystem.
      $plugin_id = $formatter->getPluginId();
      if (strpos($plugin_id, '_file') !== FALSE) {
        $config = $formatter->getSettings();
        // All blazy file ecosystem has this unique option.
        if (isset($config['svg_hide_caption'])) {
          $definition = $context['field_definition'];
          $settings   = $definition->getSettings();
          $extensions = $settings['file_extensions'] ?? '';
          $plugin     = $formatter->getPluginDefinition();

          if (!Blazy::has($extensions, 'svg') && $definition->getType() == 'image') {
            $summary[] = t('<h5>No SVG file extensions, use @provider Image instead.</h5>', [
              '@provider' => Unicode::ucfirst($plugin['provider']),
            ]);
          }
        }
      }
    }
  }

  /**
   * Implements hook_blazy_settings_alter().
   *
   * Provides minimal flags for Blazy field formatters embedded inside a view.
   * With this limited info, sub-modules like Splidebox can correctly inject
   * its options via [data-splidebox] to the correct container, etc., and avoid
   * duplicating injections at both embedded Blazy formatter and Blazy Grid view
   * style. And the same principle applies to all sub-modules.
   */
  public static function blazySettingsAlter(array &$build, $items): void {
    $settings = &$build['#settings'];
    $blazies  = $settings['blazies'];

    // Sniffs for Views to allow block__no_wrapper, views_no_wrapper, etc.
    $function = 'views_get_current_view';
    if (is_callable($function) && $view = $function()) {
      $name      = $view->storage->id();
      $view_mode = $view->current_display;
      $style     = $view->style_plugin;
      $display   = is_null($style) ? '' : $style->displayHandler->getPluginId();
      $plugin_id = is_null($style) ? '' : $style->getPluginId();

      // Not needed, can be just accessed via $blazies directly:
      // $field = $blazies->get('field', []);
      // $field['count'] = $blazies->get('count');
      // Only eat what we can chew:
      $current = [
        'count'       => count($view->result),
        'display'     => $display,
        'embedded'    => TRUE,
        'instance_id' => str_replace('_', '-', "{$name}-{$display}-{$view_mode}"),
        'name'        => $name,
        'plugin_id'   => $plugin_id,
        'view_mode'   => $view_mode,
        // 'formatter' => $field,
      ];

      // Collects view info for the embedded Blazy, and this is not a view.
      $blazies->set('view', $current, TRUE)
        ->set('is.view', FALSE);

      // @todo remove when Blazy has use_theme_field option. This is so to avoid
      // emptiness when enabling Views `Display all values in the same row`, and
      // Blazy is embedded inside sub-modules.
      // @fixme this breaks theme_field() item wrappers when embedded as Views.
      // Fixing one problem breaks others thingies, doh.
      /*
      if ($name = $blazies->get('field.name')) {
      if ($field = ($view->field[$name] ?? NULL)) {
      $options = $field->options ?? [];
      if (!empty($options['group_rows'])) {
      $settings['use_theme_field'] = TRUE;
      }
      }
      }
       */
    }
  }

}
