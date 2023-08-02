<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Theme\BlazyAttribute;
use Drupal\blazy\Theme\Grid;
use Drupal\blazy\Utility\CheckItem;
use Drupal\blazy\Utility\BlazyMarkdown;
use Drupal\blazy\Utility\Path;
use Drupal\blazy\Utility\Sanitize;
use Drupal\blazy\Deprecated\BlazyDeprecatedTrait;

/**
 * Provides common public blazy utility and a few aliases for frequent methods.
 *
 * Using aliases allow Blazy to self-organize, or improve as needed. A good
 * sample is BlazyGrid relocation, or likely BlazySettings, etc. If you are
 * calling global methods marked as @internal, consider changing them to the
 * replacements below, if any. If not, and potential frequent methods, propose
 * one in an issue to put them in the public facing class like this one. Mostly
 * for conveniences, and a good night sleep.
 */
class Blazy {

  // @todo remove at blazy:3.0.
  use BlazyDeprecatedTrait;

  /**
   * The blazy HTML ID.
   *
   * @var int
   */
  protected static $blazyId;

  /**
   * Retrieves the request stack.
   *
   * @return \Symfony\Component\HttpFoundation\RequestStack
   *   The request stack.
   *
   * @todo remove for Path::requestStack() after sub-modules, if any.
   */
  public static function requestStack() {
    return self::service('request_stack');
  }

  /**
   * Returns the cross-compat D8 ~ D10 app root.
   */
  public static function root($container) {
    return version_compare(\Drupal::VERSION, '9.0', '<')
      ? $container->get('app.root') : $container->getParameter('app.root');
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
   * Retrieves the stream wrapper manager service.
   *
   * @return \Drupal\Core\StreamWrapper\StreamWrapperManager
   *   The stream wrapper manager.
   *
   * @todo remove for Path::streamWrapperManager() after sub-modules.
   */
  public static function streamWrapperManager() {
    return self::service('stream_wrapper_manager');
  }

  /**
   * Returns a wrapper to pass tests, or DI where adding params is troublesome.
   */
  public static function service($service) {
    return \Drupal::hasService($service) ? \Drupal::service($service) : NULL;
  }

  /**
   * Filters out empty string value to avoid JSON.parse error.
   */
  public static function arrayFilter(array $config): array {
    return array_filter($config, '\Drupal\blazy\Blazy::filterEmpty');
  }

  /**
   * Provides attachments when not using the provided API.
   */
  public static function attach(array &$variables, array $settings = []): void {
    if ($blazy = self::service('blazy.manager')) {
      $attachments = $blazy->attach($settings) ?: [];
      $variables['#attached'] = self::merge($attachments, $variables, '#attached');
    }
  }

  /**
   * Alias for CheckItem::autoplay().
   */
  public static function autoplay($url, $check = TRUE): string {
    return CheckItem::autoplay($url, $check);
  }

  /**
   * Alias for hook_config_schema_info_alter().
   */
  public static function configSchemaInfoAlter(
    array &$definitions,
    $formatter = 'blazy_base',
    array $settings = []
  ): void {
    BlazyAlter::configSchemaInfoAlter($definitions, $formatter, $settings);
  }

  /**
   * Alias for BlazyAttribute::container().
   */
  public static function containerAttributes(array &$attributes, array $settings): void {
    BlazyAttribute::container($attributes, $settings);
  }

  /**
   * Returns the highest views rows, or field items count to determine gallery.
   *
   * Sliders may trick count 100 into just 2 for their magic chunk trick.
   */
  public static function count($blazies, $default = 0): int {
    $field = $blazies->get('total', 0) ?: $blazies->get('count', 1);
    $views = $blazies->get('view.count', 0);
    $count = $views > $field ? $views : $field;
    $total = $count > $default ? $count : $default;

    // Store it in an undisturbed location.
    $blazies->set('item.count', $total);
    return $total;
  }

  /**
   * Alias for BlazyFile::createUrl().
   */
  public static function createUrl($uri, $relative = FALSE): string {
    return BlazyFile::createUrl($uri, $relative);
  }

  /**
   * Alias for CheckItem::denied().
   */
  public static function denied($entity): array {
    return CheckItem::denied($entity);
  }

  /**
   * Alias for BlazyEntity::settings().
   */
  public static function entitySettings(array &$settings, $entity): void {
    BlazyEntity::settings($settings, $entity);
  }

  /**
   * Returns a entity object by a property.
   */
  public static function loadByProperty($property, $value, $type, $manager = NULL): ?object {
    $manager = $manager ?: self::service('blazy.manager');
    return $manager ? $manager->loadByProperty($property, $value, $type) : NULL;
  }

  /**
   * Returns a entity object by a UUID.
   */
  public static function loadByUuid($uuid, $type, $manager = NULL): ?object {
    $manager = $manager ?: self::service('blazy.manager');
    return $manager ? $manager->loadByUuid($uuid, $type) : NULL;
  }

  /**
   * Filters out empty string value to avoid JSON.parse error.
   */
  public static function filterEmpty($config): bool {
    return ($config !== NULL && $config !== '' && $config !== []);
  }

  /**
   * Returns the trusted HTML ID of a single instance.
   */
  public static function getHtmlId($namespace = 'blazy', $id = ''): string {
    if (!isset(static::$blazyId)) {
      static::$blazyId = 0;
    }

    // Do not use dynamic Html::getUniqueId, otherwise broken AJAX.
    $id = empty($id) ? ($namespace . '-' . ++static::$blazyId) : $id;
    return Html::getId($id);
  }

  /**
   * Alias for Path::getLibraries().
   */
  public static function getLibraries(array $names, $base_path = FALSE): array {
    return Path::getLibraries($names, $base_path);
  }

  /**
   * Alias for Path::getLibrariesPath().
   */
  public static function getLibrariesPath($name, $base_path = FALSE): ?string {
    return Path::getLibrariesPath($name, $base_path);
  }

  /**
   * Alias for Path::getPath().
   */
  public static function getPath($type, $name, $absolute = FALSE): ?string {
    return Path::getPath($type, $name, $absolute);
  }

  /**
   * Alias for CheckItem::has().
   */
  public static function has($content, $needle) {
    return CheckItem::has($content, $needle);
  }

  /**
   * Initialize Blazy settings for convenience.
   */
  public static function init(): array {
    return BlazyDefault::htmlSettings();
  }

  /**
   * Return TRUE if an url is a data URI.
   */
  public static function isDataUri($url) {
    $url = trim($url ?: '');
    return $url && mb_substr($url, 0, 10) === 'data:image';
  }

  /**
   * Merge data with a new one with an optional key and reversed parameters.
   */
  public static function merge(array $data, array $element, $key = NULL): array {
    if ($key) {
      return empty($element[$key])
        ? $data : NestedArray::mergeDeep($element[$key], $data);
    }
    return empty($element)
      ? $data : NestedArray::mergeDeep($element, $data);
  }

  /**
   * Merge multiple BlazySettings objects.
   */
  public static function mergeSettings(array $keys, array $defaults, array $configs): array {
    foreach ($keys as $key) {
      $object = $defaults[$key] ?? NULL;
      $oldies = $object ? $object->storage() : [];

      if (!isset($configs[$key]) && $object) {
        $configs[$key] = $object;
      }

      if ($newbies = $configs[$key] ?? NULL) {
        $data = $newbies->storage();
        $data = $oldies ? NestedArray::mergeDeepArray([$oldies, $data], TRUE) : $data;
        $configs[$key]->setData($data);
      }
    }

    return $configs;
  }

  /**
   * Alias for BlazyFile::normalizeUri().
   */
  public static function normalizeUri($path): string {
    return BlazyFile::normalizeUri($path);
  }

  /**
   * Reset the BlazySettings per item to have unique URI, delta, style, etc.
   */
  public static function reset(array &$settings, $key = 'blazies', array $defaults = []): BlazySettings {
    // Other implementors should verify the $key prior to calling this.
    self::verify($settings, $key, $defaults);

    // The settings instance must be unique per item.
    $config = &$settings[$key];
    if (!$config->was('reset')) {
      $config->reset($settings, $key);
      $config->set('was.reset', TRUE);
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function markdown($string, $help = TRUE): string {
    return BlazyMarkdown::parse($string, $help);
  }

  /**
   * Alias for Sanitize::attribute().
   */
  public static function sanitize(array $attributes, $escaped = TRUE, $lowercase = FALSE): array {
    return Sanitize::attribute($attributes, $escaped, $lowercase);
  }

  /**
   * Alias for BlazySettings().
   */
  public static function settings(array $data = []): BlazySettings {
    return new BlazySettings($data);
  }

  /**
   * Sanitize media input URL.
   */
  public static function sanitizeInputUrl($input): ?string {
    return Sanitize::inputUrl($input);
  }

  /**
   * Modifies settings to support iframes.
   */
  public static function toPlayable($blazies, $src = NULL, $sanitized = FALSE): BlazySettings {
    if ($src) {
      if (!$sanitized) {
        $src = Sanitize::url($src);
        $sanitized = TRUE;
      }

      $blazies->set('media.embed_url', $src)
        ->set('media.escaped', $sanitized);
    }

    return $blazies->set('is.iframeable', TRUE)
      ->set('is.playable', TRUE)
      ->set('is.multimedia', TRUE)
      ->set('libs.media', TRUE);
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
   * Alias for BlazyImage::transformDimensions().
   */
  public static function transformDimensions($style, $data, $uri = NULL): array {
    return BlazyImage::transformDimensions($style, $data, $uri);
  }

  /**
   * Alias for BlazyFile::transformRelative().
   */
  public static function transformRelative($uri, $style = NULL, array $options = []): string {
    return BlazyFile::transformRelative($uri, $style, $options);
  }

  /**
   * Alias for BlazyImage::toUrl().
   */
  public static function toUrl(array $settings, $style = NULL, $uri = NULL): string {
    return BlazyImage::toUrl($settings, $style, $uri);
  }

  /**
   * Alias for BlazyImage::url().
   */
  public static function url($uri, $style = NULL, array $options = []): string {
    return BlazyImage::url($uri, $style, $options);
  }

  /**
   * Alias for BlazyFile::isValidUri().
   */
  public static function isValidUri($uri): bool {
    return BlazyFile::isValidUri($uri);
  }

  /**
   * Alias for BlazyFile::uri().
   */
  public static function uri($item, array $settings = []): string {
    return BlazyFile::uri($item, $settings);
  }

  /**
   * Verify `blazies` exists, in case accessed outside the workflow.
   */
  public static function verify(array &$settings, $key = 'blazies', array $defaults = []): void {
    if (!isset($settings[$key]) || !isset($settings['image_style'])) {
      $settings += $defaults ?: self::init();
    }
  }

  /**
   * Returns a module installed version based on `hook_update_VERSION`.
   *
   * @requires drupal:9.3.0, no need a fallback.
   */
  public static function version($module): int {
    if ($service = self::service('update.update_hook_registry')) {
      return (int) $service->getInstalledVersion((string) $module);
    }
    return 0;
  }

  /**
   * Alias for CheckItem::which().
   */
  public static function which(array &$settings, $lazy, $class, $attribute): void {
    CheckItem::which($settings, $lazy, $class, $attribute);
  }

  /**
   * Alias for Grid::build().
   */
  public static function grid($items, array $settings): array {
    return Grid::build($items, $settings);
  }

  /**
   * Alias for Grid::attributes().
   */
  public static function gridAttributes(array &$attrs, array $settings): void {
    Grid::attributes($attrs, $settings);
  }

  /**
   * Alias for Grid::checkAttributes().
   */
  public static function gridCheckAttributes(
    array &$attrs,
    array &$content_attrs,
    $blazies,
    $root = FALSE
  ): void {
    Grid::checkAttributes($attrs, $content_attrs, $blazies, $root);
  }

  /**
   * Alias for Grid::itemAttributes().
   */
  public static function gridItemAttributes(
    array &$attrs,
    array &$content_attrs,
    array $settings
  ): void {
    Grid::itemAttributes($attrs, $content_attrs, $settings);
  }

  /**
   * Alias for Grid::initGrid().
   */
  public static function initGrid(array $options): array {
    return Grid::initGrid($options);
  }

  /**
   * Alias for Grid::toNativeGrid().
   */
  public static function toNativeGrid(array &$settings): void {
    Grid::toNativeGrid($settings);
  }

  /**
   * A helper to gradually convert things to #things to avoid render error.
   */
  public static function hashtag(array &$data, $key = 'settings', $unset = FALSE): void {
    if (!isset($data["#$key"])) {
      $data["#$key"] = $data[$key] ?? [];
    }

    // Temporary failsafe.
    if ($unset) {
      unset($data[$key]);
    }

    $blazy = "#blazy";
    if ($key == 'settings' && isset($data[$blazy])) {
      $data["#$key"] = $data[$blazy];

      // Temporary failsafe.
      if ($unset) {
        unset($data[$blazy]);
      }
    }
  }

  /**
   * A helper to gradually convert things to #things to avoid render error.
   */
  public static function toHashtag(array $data, $key = 'settings', $default = []) {
    $result = $data["#$key"] ?? $data[$key] ?? $default;
    if (!$result && $key == 'settings') {
      $result = $data["#blazy"] ?? $default;
    }
    return $result;
  }

}
