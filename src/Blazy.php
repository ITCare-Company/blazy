<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Media\Placeholder;
use Drupal\blazy\Theme\BlazyAttribute;
use Drupal\blazy\Theme\Grid;
use Drupal\blazy\Utility\Check;

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
   * Provides attachments when not using the provided API.
   */
  public static function attach(array &$variables, array $settings = []): void {
    // Our own service exists for sure, but tests don't see this due to static.
    if ($blazy = self::service('blazy.manager')) {
      $attachments = $blazy->attach($settings);
      $variables['#attached'] = empty($variables['#attached'])
        ? $attachments
        : NestedArray::mergeDeep($variables['#attached'], $attachments);
    }
  }

  /**
   * Provides autoplay URL, relevant for lightboxes to save another click.
   */
  public static function autoplay($url): string {
    if (strpos($url, 'autoplay') === FALSE
      || strpos($url, 'autoplay=0') !== FALSE) {
      return strpos($url, '?') === FALSE
        ? $url . '?autoplay=1'
        : $url . '&autoplay=1';
    }
    return $url;
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
   * Provides a wrapper to replace deprecated libraries_get_path() at ease.
   */
  public static function getLibrariesPath($name, $base_path = FALSE) {
    if ($finder = self::service('library.libraries_directory_file_finder')) {
      return $finder->find($name);
    }

    $function = 'libraries_get_path';
    return is_callable($function) ? $function($name, $base_path) : FALSE;
  }

  /**
   * Checks for essential settings.
   */
  public static function essentials(array &$settings, $item = NULL, $delta = -1): void {
    $blazies = $settings['blazies'];
    $delta   = $settings['delta'] ?? $blazies->get('delta', $delta);
    $initial = $delta == $blazies->get('initial', -2);
    $uri     = BlazyFile::uri($item, $settings);

    // This means re-definition since URI can be fed from any sources uptream.
    $blazies->set('delta', $delta)
      ->set('is.initial', $initial)
      ->set('uri', $uri);

    // File tags.
    if ($item && ($file = ($item->entity ?? NULL))) {
      $tags = $file->getCacheTags();
      $blazies->set('cache.file.tags', $tags);
    }

    // @todo remove after sub-modules.
    $settings['delta'] = $delta;
    $settings['uri'] = $uri;
  }

  /**
   * Preliminary settings, normally at container/ global level.
   */
  public static function preSettings(array &$settings) {
    self::verify($settings);

    $blazies = $settings['blazies'];
    if ($blazies->was('initialized')) {
      return;
    }

    // Checks for basic features.
    Check::container($settings);

    // Checks for grids.
    Check::grids($settings);

    // Checks for lightboxes.
    Check::lightboxes($settings);

    // Checks for [Responsive] image styles.
    BlazyImage::styles($settings);

    // Checks for lazy.
    Check::lazyOrNot($settings);

    // Marks it processed.
    $blazies->set('was.initialized', TRUE);
  }

  /**
   * Modifies the common UI settings inherited down to each item.
   */
  public static function postSettings(array &$settings = []) {
    // Failsafe, might be called directly at ::attach() outside the workflow.
    self::verify($settings);

    $blazies = $settings['blazies'];
    if (!$blazies->was('initialized')) {
      self::preSettings($settings);
    }
  }

  /**
   * Prepares the minimal settings: URI, delta, initial, and media stuffs.
   *
   * Checks lazy insanity given various features/ media types + loading option.
   * Bundles should not be coupled with embed_url to allow various bundles
   * and use media.source to be more precise instead.
   * Some duplicate rules are to address non-blazy formatters like embedded
   * Image formatter within Blazy ecosystem, but not using Blazy formatter, etc.
   *
   * @todo remove most $settings once migrated and after sub-modules and tests.
   * @todo remove $type, a legacy VEF period, which knew no bundles, or sources.
   * @todo needs a recap to move some container-level here if they must live at
   * individual level, such as non-blazy Image formatter within Blazy ecosystem.
   */
  public static function prepare(array &$settings, $item = NULL, $delta = -1) {
    // Checks for essential features.
    self::essentials($settings, $item, $delta);

    $blazies    = $settings['blazies'];
    $source     = $blazies->get('media.source');
    $type       = $blazies->get('media.type', $settings['type'] ?? 'image');
    $bundle     = $blazies->get('media.bundle', $settings['bundle'] ?? '');
    $embed_url  = $blazies->get('media.embed_url', $settings['embed_url'] ?? '');
    $videos     = ['oembed:video', 'video_embed_field'];
    $medias     = array_merge(['audio_file', 'video_file'], $videos);
    $is_video   = $source && in_array($source, $videos);
    $is_media   = $bundle && in_array($source, $medias);
    $is_remote  = $bundle == 'remote_video' || $type == 'video';
    $is_remote  = $embed_url && ($is_video || $is_remote);
    $switch     = $settings['media_switch'] ?? $blazies->get('switch');
    $is_iframe  = $is_remote && $switch == '';
    $is_player  = $is_remote && $switch == 'media';
    $unlazy     = $blazies->is('slider') && $blazies->is('initial');
    $unlazy     = $unlazy ? TRUE : $blazies->is('unlazy');
    $use_loader = $settings['use_loading'] ?? $blazies->get('use.loader');
    $use_loader = $unlazy ? FALSE : $use_loader;
    $is_unblur  = $blazies->is('sandboxed') || $blazies->is('unstyled') || $is_iframe;
    $is_blur    = $blazies->is('blur') && !$is_unblur;

    // Supports core Image formatter embedded within Blazy ecosystem.
    $is_fluid = $blazies->is('fluid') ?: $settings['ratio'] == 'fluid';

    // @todo better logic to support loader as required, must decouple loader.
    // @todo $lazy = $settings['loading'] == 'lazy';
    // @todo $lazy = $blazies->is('blazy') && ($blazies->get('libs.compat') || $lazy);
    // Redefines some since this can be fed by anyone, including custom works.
    // Also addresses mixed media unique per item.
    $blazies->set('is.fluid', $is_fluid)
      ->set('is.iframe', $is_iframe)
      ->set('is.multimedia', $is_media)
      ->set('is.player', $is_player)
      ->set('is.remote', $is_remote)
      ->set('is.blur', $is_blur)
      ->set('is.video', $is_remote)
      ->set('media.type', $type)
      ->set('is.unlazy', $unlazy)
      ->set('use.loader', $use_loader)
      ->set('switch', $switch)
      ->set('was.prepare', TRUE);
  }

  /**
   * Blazy is prepared with an URI, provides few attributes as needed.
   */
  public static function prepared(array &$attributes, array &$settings, $item = NULL) {
    $blazies = $settings['blazies'];

    // Prepares extension, image styles.
    BlazyFile::prepare($settings, $item);

    // Build thumbnail and optional placeholder based on thumbnail.
    Placeholder::prepare($attributes, $settings);

    // Prepare image URL and its dimensions, including for rich-media content,
    // such as for local video poster image if a poster URI is provided.
    BlazyImage::prepare($settings, $item);

    $blazies->set('was.prepared', TRUE);
  }

  /**
   * Preserves crucial blazy specific settings to avoid accidental overrides.
   *
   * To pass the first found Blazy formatter cherry settings into the container,
   * like Blazy Grid which lacks of options like `Media switch` or lightboxes,
   * so that when this is called at the container level, it can populate
   * lightbox gallery attributes if so configured.
   * This way at Views style, the container can have lightbox galleries without
   * extra settings, as long as `Use field template` is disabled under
   * `Style settings`, otherwise flattened out as a string.
   *
   * @see \Drupa\blazy\BlazyManagerBase::isBlazy()
   */
  public static function preserve(array &$parentsets, array &$childsets) {
    $cherries = BlazyDefault::cherrySettings();

    foreach ($cherries as $key => $value) {
      $fallback = $parentsets[$key] ?? $value;
      $parentsets[$key] = isset($childsets[$key]) && empty($fallback)
        ? $childsets[$key]
        : $fallback;
    }

    $parent = $parentsets['blazies'] ?? NULL;
    if ($parent && $child = ($childsets['blazies'] ?? NULL)) {
      // $parent->set('first.settings', array_filter($child));
      // $parent->set('first.item_id', $child->get('item.id'));
      // Hints containers to build relevant lightbox gallery attributes.
      $childbox = $child->get('lightbox.name');
      $parentbox = $parent->get('lightbox.name');

      if ($childbox && !$parentbox) {
        $optionset = $child->get('lightbox.optionset', $childbox);
        $parent->set('lightbox.name', $childbox)
          ->set($childbox, $optionset)
          ->set('is.lightbox', TRUE)
          ->set('switch', $child->get('switch'));
      }

      $parent->set('first', $child->get('first'), TRUE)
        ->set('was.preserve', TRUE);
    }
  }

  /**
   * Returns the cross-compat D8 ~ D10 app root.
   */
  public static function root($container) {
    return version_compare(\Drupal::VERSION, '9.0', '<') ? $container->get('app.root') : $container->getParameter('app.root');
  }

  /**
   * Reset the BlazySettings per item.
   */
  public static function reset(array &$settings): BlazySettings {
    self::verify($settings);

    // The settings instance must be unique per item.
    $blazies = &$settings['blazies'];
    if (!$blazies->was('reset')) {
      $blazies->reset($settings);
      $blazies->set('was.reset', TRUE);
    }

    return $blazies;
  }

  /**
   * Initialize BlazySettings object for convenient, and easy organization.
   */
  public static function settings(array $data = []): BlazySettings {
    return new BlazySettings($data);
  }

  /**
   * Extracts settings from the $build.
   */
  public static function toSettings(array &$build): array {
    $settings = $build;
    if (isset($settings['settings'])) {
      $settings = &$settings['settings'];
    }

    self::verify($settings);
    return $settings;
  }

  /**
   * Returns the translated entity if available.
   */
  public static function translated($entity, $langcode): object {
    if ($langcode && $entity->hasTranslation($langcode)) {
      return $entity->getTranslation($langcode);
    }
    return $entity;
  }

  /**
   * Verify `blazies` exists, in case accessed outside the workflow.
   */
  public static function verify(array &$settings): void {
    if (!isset($settings['blazies']) && !isset($settings['inited'])) {
      $settings += BlazyDefault::htmlSettings();
    }
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
    return self::service('current_route_match');
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
  public static function configSchemaInfoAlter(
    array &$definitions,
    $formatter = 'blazy_base',
    array $settings = []
  ): void {
    BlazyAlter::configSchemaInfoAlter($definitions, $formatter, $settings);
  }

  /**
   * Alias for BlazyAttribute::container() for sub-modules.
   */
  public static function containerAttributes(array &$attributes, array $settings): void {
    BlazyAttribute::container($attributes, $settings);
  }

  /**
   * Alias for Grid::build() for sub-modules.
   */
  public static function grid(array $items, array $settings): array {
    return Grid::build($items, $settings);
  }

  /**
   * Returns URI from image item, safe to remove anytime.
   *
   * @todo deprecated and removed for BlazyFile::uri() anytime.
   */
  public static function uri($item): string {
    return BlazyFile::uri($item);
  }

  /**
   * Returns fake image item, safe to remove anytime.
   *
   * @todo deprecated and removed for BlazyImage::fake() anytime.
   */
  public static function image(array $attributes = []) {
    return BlazyImage::fake($attributes);
  }

}
