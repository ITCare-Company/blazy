<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Media\Placeholder;
use Drupal\blazy\Theme\BlazyAttribute;

/**
 * Provides common blazy utility static methods.
 */
class Blazy implements BlazyInterface {

  // @todo remove at blazy:3.0.
  use BlazyDeprecatedTrait;

  /**
   * The blazy HTML ID.
   *
   * @var int
   */
  private static $blazyId;

  /**
   * The AMP page.
   *
   * @var bool
   */
  private static $isAmp;

  /**
   * The preview mode to disable Blazy where JS is not available, or useless.
   *
   * @var bool
   */
  private static $isPreview;

  /**
   * The preview mode to disable interactive elements.
   *
   * @var bool
   */
  private static $isSandboxed;

  /**
   * Provides attachments when not using the provided API.
   */
  public static function attach(array &$variables, array $settings = []): void {
    if ($blazy = self::service('blazy.manager')) {
      $attachments = $blazy->attach($settings);
      $variables['#attached'] = empty($variables['#attached']) ? $attachments : NestedArray::mergeDeep($variables['#attached'], $attachments);
    }
  }

  /**
   * Returns the trusted HTML ID of a single instance.
   */
  public static function getHtmlId($string = 'blazy', $id = ''): string {
    if (!isset(static::$blazyId)) {
      static::$blazyId = 0;
    }

    // Do not use dynamic Html::getUniqueId, otherwise broken AJAX.
    $id = empty($id) ? ($string . '-' . ++static::$blazyId) : $id;
    return Html::getId($id);
  }

  /**
   * Returns the commonly used path, or just the base path.
   *
   * @todo remove drupal_get_path check when min D9.3.
   */
  public static function getPath($type, $name, $absolute = FALSE): string {
    if ($resolver = self::pathResolver()) {
      $path = $resolver->getPath($type, $name);
    }
    else {
      $function = 'drupal_get_path';
      $path = is_callable($function) ? $function($type, $name) : '';
    }
    return $absolute ? \base_path() . $path : $path;
  }

  /**
   * Checks lazy insanity given various features/ media types + loading option.
   *
   * @todo re-check if any misses, or regressions here.
   */
  public static function lazyOrNot(array &$settings): void {
    $blazies = $settings['blazies'];

    // Loading `slider` or `unlazy` is more a quasi-loading to vary logic.
    $unlazy = $blazies->is('slider') && $blazies->is('initial');
    $settings['unlazy'] = $unlazy ? TRUE : $settings['unlazy'];

    // @todo remove settings.placeholder|use_media checks after sub-modules.
    // The SVG placeholder should accept either original, or styled image.
    $default = Placeholder::generate($settings['width'], $settings['height']);
    $settings['placeholder'] = $placeholder = $blazies->get('ui.placeholder') ?: $default;

    // @todo remove use_loading after sub-module updates.
    // @todo better logic to support loader as required, must decouple loader.
    // @todo $lazy = $settings['loading'] == 'lazy';
    // @todo $lazy = !empty($settings['blazy']) && ($blazies->get('libs.compat') || $lazy);
    $use_loader = $settings['use_loading'] ?? '';
    $use_loader = $settings['unlazy'] ? FALSE : $use_loader;

    $settings['use_loading'] = $use_loader;

    $blazies->set('use.loader', $use_loader);
    $blazies->set('ui.placeholder', $placeholder);
  }

  /**
   * Prepares the minimal settings: URI, delta, initial, and media stuffs.
   *
   * Bundles should not be coupled with embed_url to allow various bundles
   * and use media.source to be more precise instead.
   *
   * @todo remove most $settings once migrated and after sub-modules and tests.
   * @todo remove $type, a legacy VEF period, which knew no bundles, or sources.
   */
  public static function prepare(array &$settings, $item = NULL) {
    $blazies = $settings['blazies'];
    $delta = $blazies->get('delta', $settings['delta'] ?? 0);
    $namespace = $blazies->get('namespace', $settings['namespace'] ?? 'blazy');
    $item_id = $blazies->get('item.id', $settings['item_id'] ?? 'blazy');
    $source = $blazies->get('media.source', 'image');
    $type = $blazies->get('media.type', $settings['type'] ?? 'image');
    $bundle = $blazies->get('media.bundle', $settings['bundle'] ?? 'image');
    $embed_url = $blazies->get('media.embed_url', $settings['embed_url'] ?? '');
    $videos = ['oembed:video', 'video_embed_field'];
    $medias = array_merge(['audio_file', 'video_file'], $videos);
    $is_video = $source && in_array($source, $videos);
    $is_media = $bundle && in_array($source, $medias);
    // $is_media = $type && in_array($type, ['audio', 'video']) || $is_media;
    $is_remote = $embed_url
      && ($is_video || ($bundle == 'remote_video' || $type == 'video'));
    $is_iframe = $is_remote && $settings['media_switch'] == '';
    $is_player = $is_remote && $settings['media_switch'] == 'media';
    $_uri = $blazies->get('uri', $settings['uri'] ?? '');
    $settings['uri'] = $uri = $_uri ?: BlazyFile::uri($item);

    // Redefines some since this can be fed by anyone, including custom works.
    // Also addresses mixed media unique per item.
    $blazies->set('delta', $delta)
      ->set('item.id', $item_id)
      ->set('is.iframe', $is_iframe)
      ->set('is.initial', $delta == $blazies->get('initial'))
      ->set('is.multimedia', $is_media)
      ->set('is.player', $is_player)
      ->set('is.remote', $is_remote)
      ->set('is.video', $is_remote)
      ->set('namespace', $namespace)
      ->set('media.type', $type)
      ->set('uri', $uri);
  }

  /**
   * Preserves crucial blazy specific settings to avoid accidental overrides.
   *
   * To pass the first found Blazy formatter cherry/ limited settings into
   * the container, like Blazy Grid which lacks of options like `Media switch`
   * or lightboxes, so that when this is called at the container
   * level, it can populate lightbox gallery attributes if so configured.
   * And when called per item within Views gallery, Blazy is not overriden
   * by its parent settings. This way at Views style, the container can have
   * lightbox galleries without extra settings, as long as `Use field template`
   * is disabled under `Style settings`, otherwise flattened out as a string.
   *
   * @see \Drupa\blazy\BlazyManagerBase::isBlazy()
   */
  public static function preserve(array &$settings, array $blazy_settings) {
    $cherries = BlazyDefault::cherrySettings();
    foreach ($cherries as $key => $value) {
      $fallback = $settings[$key] ?? $value;
      $settings[$key] = isset($blazy_settings[$key]) && empty($fallback)
        ? $blazy_settings[$key]
        : $fallback;
    }

    $blazies = $settings['blazies'] ?? NULL;
    if ($blazies && $blazy = ($blazy_settings['blazies'] ?? NULL)) {
      // $blazies->set('first.settings', array_filter($blazy));
      // $blazies->set('first.item_id', $blazy->get('item.id'));
      // Hints containers to build relevant lightbox gallery attributes.
      if ($lightbox = $blazy->get('lightbox')) {
        $blazies->set('lightbox', $lightbox)
          ->set($lightbox, $lightbox);
      }

      $blazies->set('first', $blazy->get('first'), TRUE);
    }
  }

  /**
   * Checks if Blazy is in CKEditor preview mode where no JS assets are loaded.
   */
  public static function isPreview(): bool {
    if (!isset(static::$isPreview)) {
      static::$isPreview = self::isAmp() || self::isSandboxed();
    }
    return static::$isPreview;
  }

  /**
   * Checks if Blazy is in AMP pages.
   */
  public static function isAmp(): bool {
    if (!isset(static::$isAmp)) {
      $stack = self::requestStack();
      static::$isAmp = $stack && $stack->getCurrentRequest()->query->get('amp');
    }
    return static::$isAmp;
  }

  /**
   * In CKEditor without JS assets, interactive elements must be sandboxed.
   */
  public static function isSandboxed(): bool {
    if (!isset(static::$isSandboxed)) {
      $route = self::routeMatch()->getRouteName();
      $check = FALSE;

      // @todo remove after regression fixes, or keep it due to thumbnail sizes.
      $edits = ['entity_browser.', 'edit_form', 'add_form', '.preview'];
      foreach ($edits as $key) {
        if (mb_strpos($route, $key) !== FALSE) {
          $check = TRUE;
          break;
        }
      }
      static::$isSandboxed = $check;
    }
    return static::$isSandboxed;
  }

  /**
   * Returns the cross-compat D8 ~ D10 app root.
   */
  public static function root($container) {
    return version_compare(\Drupal::VERSION, '9.0', '<') ? $container->get('app.root') : $container->getParameter('app.root');
  }

  /**
   * Extracts setting from the $build.
   */
  public static function toSettings(array &$build): array {
    $settings = $build;
    if (isset($settings['settings'])) {
      $settings = &$settings['settings'];
    }

    $settings += BlazyDefault::htmlSettings();
    return $settings;
  }

  /**
   * Modifies the common settings extracted from the given entity.
   */
  public static function translated($entity, $langcode): object {
    if ($langcode && $entity->hasTranslation($langcode)) {
      return $entity->getTranslation($langcode);
    }
    return $entity;
  }

  /**
   * Retrieves the stream wrapper manager service.
   *
   * @return \Drupal\Core\StreamWrapper\StreamWrapperManager
   *   The stream wrapper manager.
   */
  public static function streamWrapperManager() {
    return self::service('stream_wrapper_manager');
  }

  /**
   * Retrieves the currently active route match object.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   The currently active route match object.
   */
  public static function routeMatch() {
    return \Drupal::routeMatch();
  }

  /**
   * Retrieves the request stack.
   *
   * @return \Symfony\Component\HttpFoundation\RequestStack
   *   The request stack.
   */
  public static function requestStack() {
    return self::service('request_stack');
  }

  /**
   * Retrieves the path resolver.
   *
   * @return \Drupal\Core\Extension\ExtensionPathResolver
   *   The path resolver.
   */
  public static function pathResolver() {
    return self::service('extension.path.resolver');
  }

  /**
   * Retrieves the file url generator service.
   *
   * @return \Drupal\Core\Extension\ExtensionPathResolver
   *   The file url generator.
   *
   * @see https://www.drupal.org/node/2940031
   */
  public static function fileUrlGenerator() {
    return self::service('file_url_generator');
  }

  /**
   * Retrieves the breakpoint manager.
   *
   * @return \Drupal\breakpoint\BreakpointManager
   *   The breakpoint manager.
   */
  public static function breakpointManager() {
    return self::service('breakpoint.manager');
  }

  /**
   * Returns a wrapper to pass tests, or DI where adding params is troublesome.
   */
  public static function service($service) {
    return \Drupal::hasService($service) ? \Drupal::service($service) : NULL;
  }

  /**
   * Alias for hook_config_schema_info_alter() for sub-modules.
   */
  public static function configSchemaInfoAlter(array &$definitions, $formatter = 'blazy_base', array $settings = []): void {
    BlazyAlter::configSchemaInfoAlter($definitions, $formatter, $settings);
  }

  /**
   * Alias for BlazyAttribute::container() for sub-modules.
   */
  public static function containerAttributes(array &$attributes, array $settings = []): void {
    BlazyAttribute::container($attributes, $settings);
  }

  /**
   * Returns URI from image item.
   *
   * @todo deprecated and removed for BlazyFile::uri().
   */
  public static function uri($item): string {
    return BlazyFile::uri($item);
  }

  /**
   * Returns fake image item based on the given $attributes.
   *
   * @todo deprecated and removed for BlazyImage::fake().
   */
  public static function image(array $attributes = []) {
    return BlazyImage::fake($attributes);
  }

}
