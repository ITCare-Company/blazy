<?php

namespace Drupal\blazy;

/**
 * Defines shared plugin default settings for field formatter and Views style.
 */
class BlazyDefault {

  /**
   * Defines constant for the supported text tags.
   */
  const TAGS = ['a', 'em', 'strong', 'h2', 'p', 'span', 'ul', 'ol', 'li'];

  /**
   * Defines constant for the supported media tags.
   *
   * @todo recheck if OEmbed has <iframe>, <embed>, <object>, <track>, etc.
   */
  const MEDIA_TAGS = [
    'audio',
    'div',
    'figcaption',
    'figure',
    'img',
    'picture',
    'source',
    'span',
    'video',
  ];

  /**
   * Returns alterable plugin settings to pass the tests.
   *
   * @param array $settings
   *   The settings being modified.
   */
  public static function alterableSettings(array &$settings) {
    if ($manager = Blazy::service('blazy.manager')) {
      $context = ['class' => get_called_class()];
      $manager->moduleHandler()->alter('blazy_base_settings', $settings, $context);
    }
  }

  /**
   * Returns basic plugin settings.
   */
  public static function baseSettings() {
    $settings = ['cache' => 0];

    self::alterableSettings($settings);
    return $settings;
  }

  /**
   * Returns cherry-picked settings for field formatters and Views fields.
   */
  public static function cherrySettings() {
    return [
      'box_style'       => '',
      'image_style'     => '',
      'media_switch'    => '',
      'ratio'           => '',
      'thumbnail_style' => '',
    ];
  }

  /**
   * Returns image-related field formatter and Views settings.
   */
  public static function baseImageSettings() {
    return [
      'background'             => FALSE,
      'box_caption'            => '',
      'box_caption_custom'     => '',
      'box_media_style'        => '',
      'caption'                => [],
      'loading'                => 'lazy',
      'preload'                => FALSE,
      'responsive_image_style' => '',
      'use_theme_field'        => FALSE,
    ] + self::cherrySettings();
  }

  /**
   * Returns image-related field formatter and Views settings.
   */
  public static function imageSettings() {
    return [
      'layout'    => '',
      'view_mode' => '',
    ] + self::baseSettings() + self::baseImageSettings();
  }

  /**
   * Returns Views specific settings.
   */
  public static function viewsSettings() {
    return [
      'class'   => '',
      'image'   => '',
      'link'    => '',
      'overlay' => '',
      'title'   => '',
      'vanilla' => FALSE,
    ];
  }

  /**
   * Returns fieldable entity formatter and Views settings.
   */
  public static function extendedSettings() {
    return self::viewsSettings() + self::imageSettings();
  }

  /**
   * Returns optional grid field formatter and Views settings.
   */
  public static function gridSettings() {
    return [
      'grid'        => '',
      'grid_medium' => '',
      'grid_small'  => '',
      'style'       => '',
    ];
  }

  /**
   * Returns sensible default options common for Views lacking of UI.
   */
  public static function lazySettings() {
    return [
      'blazy' => TRUE,
      'lazy'  => 'blazy',
      'ratio' => 'fluid',
    ];
  }

  /**
   * Returns sensible default options common for entities lacking of UI.
   */
  public static function entitySettings() {
    return [
      'media_switch' => 'media',
      'rendered'     => FALSE,
      'view_mode'    => 'default',
      '_detached'    => TRUE,
    ] + self::lazySettings();
  }

  /**
   * Returns default options common for rich Media entities: Facebook, etc.
   *
   * This basically disables few Blazy features for rendered-entity-like.
   */
  public static function richSettings() {
    return [
      'background'   => FALSE,
      'media_switch' => '',
    ];
  }

  /**
   * Returns minimum grid and style settings.
   */
  public static function gridEntitySettings() {
    return self::gridSettings() + ['view_mode' => ''];
  }

  /**
   * Returns shared global form settings which should be consumed at formatters.
   */
  public static function uiSettings() {
    return [
      'blur_client'         => FALSE,
      'blur_storage'        => FALSE,
      'blur_minwidth'       => 0,
      'fx'                  => '',
      'nojs'                => [],
      'one_pixel'           => TRUE,
      'visible_class'       => FALSE,
      'noscript'            => FALSE,
      'placeholder'         => '',
      'responsive_image'    => FALSE,
      'unstyled_extensions' => '',
    ];
  }

  /**
   * Grouping for sanity till all settings converted into BlazySettings.
   *
   * It was a pre-release RC7 @todo, partially implemented since 2.7.
   * The hustle is sub-modules are not aware, yet. Yet better started before 3.
   * While some configurable settings are intact, blazies are more for grouping
   * dynamic, non-configurable settings. But it can also store blazy-specific.
   * Very few are adjusted into blazies for easy calls/overrides/alters.
   */
  public static function blazies() {
    return [
      'initial' => 0,
      'is' => [],
      'lazy' => ['attribute' => 'src', 'class' => 'b-lazy'],
      'libs' => [],
      'ui' => self::uiSettings(),
      'use' => [],
    ];
  }

  /**
   * Returns sensible default container settings to shutup notices when lacking.
   */
  public static function htmlSettings() {
    return [
      'blazies' => Blazy::settings(self::blazies()),
      'inited' => TRUE,

      // @todo remove after complete migrations:
      'image_url' => '',
      'height' => NULL,
      'width' => NULL,

      // Configurable settings are dumped as they are as always.
      // Very few are adjusted into blazies for easy calls/overrides/alters.
    ] + self::imageSettings()
      + self::gridSettings();
  }

  /**
   * Returns blazy theme properties, its image and container attributes.
   *
   * The reserved attributes is defined before entering Blazy as bonus variable.
   * Consider other bonuses: title and content attributes at a later stage.
   * layering is crucial for mixed media, cannot be simply dumped as
   * indexed children, must have clear properties indentifying their functions.
   */
  public static function themeProperties() {
    return [
      'attributes' => [],
      'captions' => [],
      'content' => [],
      'iframe' => [],
      'image' => [],
      'icon' => [],
      'item' => NULL,
      'item_attributes' => [],
      'noscript' => [],
      'overlay' => [],
      'preface' => [],
      'postscript' => [],
      'settings' => [],
      'url' => NULL,
    ];
  }

  /**
   * Returns additional/ optional blazy theme attributes.
   *
   * The attributes mentioned here are only instantiated at theme_blazy() and
   * might be an empty array, not instanceof \Drupal\Core\Template\Attribute.
   */
  public static function themeAttributes() {
    return ['caption', 'media', 'url', 'wrapper'];
  }

  /**
   * Returns available components.
   */
  public static function components(): array {
    return array_merge(self::grids(), [
      'animate',
      'background',
      'blur',
      'compat',
      'filter',
      'media',
      'mfp',
      'photobox',
      'ratio',
    ]);
  }

  /**
   * Returns available grid components.
   */
  public static function grids(): array {
    return [
      'column',
      'flex',
      'grid',
      'nativegrid',
      'nativegrid.masonry',
    ];
  }

  /**
   * Returns available plugins.
   */
  public static function plugins(): array {
    return [
      'eventify',
      'viewport',
      'xlazy',
      'css',
      'dom',
      'animate',
      'dataset',
      'background',
      'observer',
    ];
  }

  /**
   * Returns available nojs components related to core Blazy functionality.
   */
  public static function polyfills(): array {
    return [
      'polyfill',
      'classlist',
      'promise',
      'raf',
      'webp',
    ];
  }

  /**
   * Returns available nojs components related to core Blazy functionality.
   */
  public static function nojs(): array {
    return array_merge(['lazy'], self::polyfills());
  }

  /**
   * Returns optional polyfills, not loaded till enabled and a feature meets.
   */
  public static function ondemandPolyfills(): array {
    return [
      'fullscreen',
    ];
  }

  /**
   * Returns deprecated, or previously wrong room settings.
   *
   * Only needed by 1.x/old users who never re-saved the forms at 2.x. This is
   * easily solved by just re-saving them. And these will be just gone for good.
   *
   * @todo deprecated/ removed at 3.x.
   */
  public static function deprecatedSettings() {
    return [
      'breakpoints' => [],
      'current_view_mode' => '',
      'fx' => '',
      'grid_header' => '',
      'icon' => '',
      'id' => '',
      'lazy'  => 'blazy',
      'sizes' => '',
      'skin'  => '',
      'style' => '',
      '_item' => '',
      '_uri' => '',
    ];
  }

  /**
   * Returns Blazy specific breakpoints.
   *
   * @todo remove custom breakpoints anytime at blazy:3.x, called by BVEF.
   */
  public static function getConstantBreakpoints() {
    return ['xs', 'sm', 'md', 'lg', 'xl'];
  }

  /**
   * Returns optional grid field formatter and Views settings.
   *
   * @todo deprecated/ removed for self::gridSettings() since style is coupled.
   */
  public static function gridBaseSettings() {
    return [
      'grid'        => '',
      'grid_medium' => '',
      'grid_small'  => '',
      'style'       => '',
    ];
  }

  /**
   * Returns text settings.
   *
   * @todo deprecated/ removed for self::gridSettings() since style is coupled.
   */
  public static function textSettings() {
    return self::gridSettings();
  }

  /**
   * Returns settings provided by various UI.
   *
   * @todo deprecated/ removed, no longer relevant since 2.17 after blazies.
   */
  public static function anywhereSettings() {
    return [
      'lazy'  => '',
      'style' => '',
    ];
  }

  /**
   * Returns sensible default item settings to shutup notices when lacking.
   *
   * @todo deprecated/ removed, no longer relevant since 2.17 after blazies.
   */
  public static function itemSettings() {
    return [
      'classes' => [],
      'image_url' => '',
      'height' => NULL,
      'width' => NULL,
    ] + self::htmlSettings();
  }

}
