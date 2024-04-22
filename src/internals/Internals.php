<?php

namespace Drupal\blazy\internals;

use Drupal\blazy\BlazySettings;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Theme\Grid;
use Drupal\blazy\Utility\Markdown;
use Drupal\blazy\Utility\Path;
use Drupal\Component\Utility\Html;

/**
 * Provides internal kitchen-skink non-reusable blazy utilities.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 *
 * @todo remove some aliases for actual class calls after migrations.
 */
class Internals {

  /**
   * The data URI text.
   */
  const DATA_TEXT = 'data:text/plain;base64,';

  /**
   * The blazy HTML ID.
   *
   * @var int|null
   */
  protected static $blazyId;

  /**
   * Alias for base_path() for easy removal.
   *
   * @todo replace base_path() if any replacement by D11.
   */
  public static function basePath(): ?string {
    return \base_path() ?: '';
  }

  /**
   * Returns TRUE if the link has empty title, or just plain URL or text.
   */
  public static function emptyOrPlainTextLink(array $link): bool {
    $empty = FALSE;
    if ($title = $link['#title'] ?? NULL) {
      // @todo php 8: str_starts_with($title, '/');
      $length = strlen('/');
      $empty = substr($title, 0, $length) === '/' || strpos($title, 'http') !== FALSE;
    }

    if ($empty ||
      isset($link['#plain_text']) ||
      isset($link['#context']['value'])) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Alias for Content::denied().
   */
  public static function denied($entity): array {
    return Content::denied($entity);
  }

  /**
   * Alias for Content::toHtml().
   */
  public static function toHtml($content, $tag = 'div', $class = NULL): array {
    return Content::toHtml($content, $tag, $class);
  }

  /**
   * Alias for Content::toContent().
   */
  public static function toContent(
    array &$data,
    $unset = FALSE,
    array $keys = ['content', 'box', 'slide'],
  ): array {
    return Content::toContent($data, $unset, $keys);
  }

  /**
   * Alias for Multimedia::correct().
   */
  public static function correct($input): ?string {
    return Multimedia::correct($input);
  }

  /**
   * Alias for Multimedia::irrational().
   */
  public static function irrational($provider): bool {
    return Multimedia::irrational($provider);
  }

  /**
   * Alias for Multimedia::irrational().
   */
  public static function linkable($blazies): bool {
    return Multimedia::linkable($blazies);
  }

  /**
   * Alias for Multimedia::provider().
   */
  public static function provider($blazies, $provider = NULL): ?string {
    return Multimedia::provider($blazies, $provider);
  }

  /**
   * Alias for Multimedia::isVideo().
   */
  public static function isVideo($blazies): bool {
    return Multimedia::isVideo($blazies);
  }

  /**
   * Alias for Multimedia::toPlayable().
   */
  public static function toPlayable($blazies, $src = NULL, $sanitized = FALSE): BlazySettings {
    return Multimedia::toPlayable($blazies, $src, $sanitized);
  }

  /**
   * Alias for Multimedia::youtube().
   */
  public static function youtube($input): ?string {
    return Multimedia::youtube($input);
  }

  /**
   * Alias for Settings::contently().
   */
  public static function contently(array &$settings): void {
    Settings::contently($settings);
  }

  /**
   * Alias for Settings::count().
   */
  public static function count($blazies, $default = 0): int {
    return Settings::count($blazies, $default);
  }

  /**
   * Alias for Settings::updateCountByDelta().
   */
  public static function updateCountByDelta(array &$settings): void {
    Settings::updateCountByDelta($settings);
  }

  /**
   * Alias for Settings::getViewFieldData().
   */
  public static function getViewFieldData($view): array {
    return Settings::getViewFieldData($view);
  }

  /**
   * Alias for Settings::getViewLimit().
   */
  public static function getViewLimit($blazies): int {
    return Settings::getViewLimit($blazies);
  }

  /**
   * Alias for Settings::isUnlazy().
   */
  public static function isUnlazy($blazies): bool {
    return Settings::isUnlazy($blazies);
  }

  /**
   * Alias for Settings::prepare().
   */
  public static function prepare(array &$settings, $item, $called = FALSE): void {
    Settings::prepare($settings, $item, $called);
  }

  /**
   * Alias for Settings::prepared().
   */
  public static function prepared(array &$settings, $item): void {
    Settings::prepared($settings, $item);
  }

  /**
   * Alias for Settings::preserve().
   */
  public static function preserve(array &$parentsets, array &$childsets): void {
    Settings::preserve($parentsets, $childsets);
  }

  /**
   * Alias for Settings::preSettings().
   */
  public static function preSettings(array &$settings, $root = TRUE): void {
    Settings::preSettings($settings, $root);
  }

  /**
   * Alias for Settings::postSettings().
   */
  public static function postSettings(array &$settings): void {
    Settings::postSettings($settings);
  }

  /**
   * Alias for Settings::reset().
   */
  public static function reset(
    array &$settings,
    $key = 'blazies',
    array $defaults = [],
  ): BlazySettings {
    return Settings::reset($settings, $key, $defaults);
  }

  /**
   * Alias for Settings::init().
   */
  public static function settings(array $data = []): BlazySettings {
    return Settings::init($data);
  }

  /**
   * Alias for Settings::hashtag().
   */
  public static function hashtag(array &$data, $key = 'settings', $unset = FALSE): void {
    Settings::hashtag($data, $key, $unset);
  }

  /**
   * Alias for Settings::toHashtag().
   */
  public static function toHashtag(array $data, $key = 'settings', $default = []) {
    return Settings::toHashtag($data, $key, $default);
  }

  /**
   * Alias for Settings::tokenize().
   */
  public static function tokenize($blazies): void {
    Settings::tokenize($blazies);
  }

  /**
   * Alias for Settings::verify().
   */
  public static function verify(
    array &$settings,
    $key = 'blazies',
    array $defaults = [],
  ): BlazySettings {
    return Settings::verify($settings, $key, $defaults);
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
   * Alias for Path::getPath().
   */
  public static function getPath($type, $name, $absolute = FALSE): ?string {
    return Path::getPath($type, $name, $absolute);
  }

  /**
   * Checks if it is an SVG.
   */
  public static function isSvg($uri): bool {
    return BlazyFile::isSvg($uri);
  }

  /**
   * Returns markdown.
   */
  public static function markdown($string, $help = TRUE, $sanitize = TRUE): string {
    return Markdown::parse($string, $help, $sanitize);
  }

  /**
   * Returns a wrapper to pass tests, or DI where adding params is troublesome.
   */
  public static function service($service) {
    return \Drupal::hasService($service) ? \Drupal::service($service) : NULL;
  }

  /**
   * Alias for Grid::toNativeGrid().
   */
  public static function toNativeGrid(array &$settings): void {
    Grid::toNativeGrid($settings);
  }

  /**
   * Returns a entity object by a property.
   *
   * @todo remove for BlazyInterface::loadByProperty().
   */
  public static function loadByProperty($property, $value, $type, $manager = NULL): ?object {
    $manager = $manager ?: self::service('blazy.manager');
    return $manager ? $manager->loadByProperty($property, $value, $type) : NULL;
  }

  /**
   * Returns a entity object by a UUID.
   *
   * @todo remove for BlazyInterface::loadByUuid().
   */
  public static function loadByUuid($uuid, $type, $manager = NULL): ?object {
    $manager = $manager ?: self::service('blazy.manager');
    return $manager ? $manager->loadByUuid($uuid, $type) : NULL;
  }

  /**
   * Returns the app root.
   *
   * @todo remove after usage checks.
   */
  public static function root($container) {
    return $container->getParameter('app.root');
  }

}
