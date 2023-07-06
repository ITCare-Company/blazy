<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Theme\BlazyAttribute;
use Drupal\blazy\Theme\Grid;
use Drupal\blazy\Utility\CheckItem;
use Drupal\blazy\Utility\Path;
use Drupal\blazy\Utility\Sanitize;
use Drupal\blazy\Deprecated\BlazyDeprecatedTrait;

/**
 * Provides common public blazy utility and a few aliases for frequent methods.
 */
class Blazy {

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
    if ($blazy = self::service('blazy.manager')) {
      $attachments = $blazy->attach($settings) ?: [];
      $variables['#attached'] = self::merge($attachments, $variables, '#attached');
    }
  }

  /**
   * Provides autoplay URL, relevant for lightboxes to save another click.
   */
  public static function autoplay($url, $check = TRUE): string {
    // It doesn't cover all providers, but few, no biggies till needed.
    if (!self::has($url, 'autoplay')
      || self::has($url, 'autoplay=0')) {
      return self::has($url, '?')
        ? $url . '&autoplay=1'
        : $url . '?autoplay=1';
    }

    // @todo recheck if any side effect/ double escape to cdn/ valid input.
    return $check ? UrlHelper::stripDangerousProtocols($url) : $url;
  }

  /**
   * Alias for BlazyFile::createUrl() for sub-modules.
   */
  public static function createUrl($uri, $relative = FALSE): string {
    return BlazyFile::createUrl($uri, $relative);
  }

  /**
   * Alias for CheckItem::denied() for sub-modules.
   */
  public static function denied($entity): array {
    return CheckItem::denied($entity);
  }

  /**
   * Alias for BlazyEntity::settings() for sub-modules.
   */
  public static function entitySettings(array &$settings, $entity): void {
    BlazyEntity::settings($settings, $entity);
  }

  /**
   * Filters out empty string value to avoid JSON.parse error.
   */
  public static function arrayFilter(array $config): array {
    return array_filter($config, '\Drupal\blazy\Blazy::filterEmpty');
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
  public static function getHtmlId($string = 'blazy', $id = ''): string {
    if (!isset(self::$blazyId)) {
      self::$blazyId = 0;
    }

    // Do not use dynamic Html::getUniqueId, otherwise broken AJAX.
    $id = empty($id) ? ($string . '-' . ++self::$blazyId) : $id;
    return Html::getId($id);
  }

  /**
   * Alias for Path::getPath().
   */
  public static function getPath($type, $name, $absolute = FALSE): ?string {
    return Path::getPath($type, $name, $absolute);
  }

  /**
   * Alias for Path::getLibrariesPath().
   */
  public static function getLibrariesPath($name, $base_path = FALSE): ?string {
    return Path::getLibrariesPath($name, $base_path);
  }

  /**
   * A simple wrapper for stripos().
   */
  public static function has($content, $needle) {
    if ($content && $needle = trim($needle ?: '')) {
      // stripos() won't work with diacritical signs.
      $needle = strtolower($needle);
      return strpos($content, $needle) !== FALSE;
    }
    return FALSE;
  }

  /**
   * Merge data with a new one with an optional key.
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
   * Reset the BlazySettings per item to have unique URI, delta, style, etc.
   */
  public static function reset(array &$settings, $key = 'blazies'): BlazySettings {
    // Other implementors should verify the $key prior to calling this.
    self::verify($settings);

    // The settings instance must be unique per item.
    $blazies = &$settings[$key];
    if (!$blazies->was('reset')) {
      $blazies->reset($settings);
      $blazies->set('was.reset', TRUE);
    }

    return $blazies;
  }

  /**
   * Returns the cross-compat D8 ~ D10 app root.
   */
  public static function root($container) {
    return version_compare(\Drupal::VERSION, '9.0', '<') ? $container->get('app.root') : $container->getParameter('app.root');
  }

  /**
   * Alias for Sanitize::attribute() for sub-modules.
   */
  public static function sanitize(array $attributes, $escaped = TRUE): array {
    return Sanitize::attribute($attributes, $escaped);
  }

  /**
   * Initialize BlazySettings object for convenience, and easy organization.
   */
  public static function settings(array $data = []): BlazySettings {
    return new BlazySettings($data);
  }

  /**
   * A helper to gradually convert settings to #settings to avoid render error.
   *
   * @todo refactor at 3.x, to solve out of sync module like BVEF, etc.
   * No real problems found so far even with BVEF, just minimize issues.
   */
  public static function toSettings(array $data) {
    return $data['#settings'] ?? $data['settings'] ?? [];
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
   * Retrieves the currently active route match object.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   The currently active route match object.
   *
   * @todo remove for Path::routeMatch() after sub-modules, if any.
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
   * Alias for Grid::build() for sub-modules and easy organization.
   */
  public static function grid(array $items, array $settings): array {
    return Grid::build($items, $settings);
  }

  /**
   * Alias for Grid::attributes() for sub-modules and easy organization.
   */
  public static function gridAttributes(array &$attributes, array $settings): void {
    Grid::attributes($attributes, $settings);
  }

  /**
   * Alias for Grid::itemAttributes() for sub-modules and easy organization.
   *
   * This method + self::gridAttributes() allows you to build grids with any
   * themes having just DIV > DIVs without building it with self::grid() such as
   * seen at IO Browser/Slick Browser by simply modifying existing attributes.
   */
  public static function gridItemAttributes(
    array &$attributes,
    array &$content_attributes,
    array $settings
  ): void {
    Grid::itemAttributes($attributes, $content_attributes, $settings);
  }

  /**
   * Return TRUE if an url is a data URI.
   */
  public static function isDataUri($url) {
    $url = trim($url ?: '');
    return $url && mb_substr($url, 0, 10) === 'data:image';
  }

  /**
   * Alias for BlazyFile::normalizeUri() for sub-modules.
   */
  public static function normalizeUri($path): string {
    return BlazyFile::normalizeUri($path);
  }

  /**
   * Alias for BlazyFile::transformRelative() for sub-modules.
   */
  public static function transformRelative($uri, $style = NULL, array $options = []): string {
    return BlazyFile::transformRelative($uri, $style, $options);
  }

  /**
   * Alias for BlazyFile::uri() for sub-modules.
   */
  public static function uri($item, array $settings = []): string {
    return BlazyFile::uri($item, $settings);
  }

  /**
   * Alias for CheckItem::which() for sub-modules.
   */
  public static function which(array &$settings, $lazy, $class, $attribute): void {
    CheckItem::which($settings, $lazy, $class, $attribute);
  }

}
