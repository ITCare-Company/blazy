<?php

namespace Drupal\blazy;

use Drupal\blazy\Internals\Entity;
use Drupal\blazy\Internals\Internals;
use Drupal\blazy\Theme\Attributes;
use Drupal\blazy\Utility\Sanitize;
use Drupal\blazy\Media\Image;
use Drupal\blazy\Media\Uri;
use Drupal\blazy\Media\Url;
use enshrined\svgSanitize\Sanitizer;

/**
 * Provides common public Blazy utilities and a limited set of aliases.
 *
 * This class acts as a small public façade to shield callers from internal
 * refactors and relocations. Aliases allow Blazy to reorganize or evolve
 * internal implementations over time without breaking existing integrations
 * (for example, the relocation of BlazyGrid or BlazySettings).
 *
 * Static methods are retained primarily for backward compatibility and
 * convenience. Over time, most static helpers have proven restrictive and
 * were migrated to instance-based services to better support dependency
 * injection, testability, and extensibility. New or complex functionality
 * should prefer the provided non-static manager services.
 *
 * If you are calling global methods marked as @internal, consider:
 *   - switching to the documented aliases provided here, when available.
 *   - using the appropriate injected manager or interface-based services,
 *     as static utilities may continue to be reduced in scope. Some were moved
 *     into BlazyInterface since early 2.16, and the remaining will be finally
 *     removed at Blazy 4.x or 5.x at the latest.
 *
 * @todo remove the rest of static methods for DI at 4.x or 5.x.
 */
class Blazy extends BlazyBase {

  /**
   * Initialize Blazy settings for convenience.
   *
   * @todo leave it unchanged till 4.x changes blazy.api.php.
   */
  public static function init(array $data = []): array {
    return $data + BlazyDefault::htmlSettings();
  }

  /**
   * Alias for Entity::settings().
   */
  public static function entitySettings(array &$settings, $entity): void {
    Entity::settings($settings, $entity);
  }

  /**
   * Alias for Internals::fileExistsReplace().
   */
  public static function fileExistsReplace() {
    return Internals::fileExistsReplace();
  }

  /**
   * Alias for Internals::getBlazies().
   */
  public static function getBlazies(array &$settings, bool $merge = FALSE, string $key = 'blazies'): BlazySettings {
    return Internals::getBlazies($settings, $merge, $key);
  }

  /**
   * Alias for Sanitize::attribute().
   */
  public static function sanitize(array $attributes, $escaped = TRUE, $lowercase = FALSE): array {
    return Sanitize::attribute($attributes, $escaped, $lowercase);
  }

  /**
   * Sanitize media input URL.
   */
  public static function sanitizeInputUrl($input, $privacy = FALSE): ?string {
    return Sanitize::inputUrl($input, $privacy);
  }

  /**
   * In case we have SVG Sanitizer alternatives, provide one door check.
   */
  public static function svgSanitizerExists(): bool {
    return class_exists(Sanitizer::class);
  }

  /**
   * Alias for Uri::normalize().
   */
  public static function normalizeUri($path): string {
    return Uri::normalize($path);
  }

  /**
   * Alias for Uri::fromImage().
   */
  public static function uri($item, array $settings = []): string {
    return Uri::fromImage($item, $settings);
  }

  /**
   * Alias for Image::transformDimensions().
   */
  public static function transformDimensions($style, $data, $uri = NULL): array {
    return Image::transformDimensions($style, $data, $uri);
  }

  /**
   * Alias for Uri::transformRelative().
   */
  public static function transformRelative($uri, $style = NULL, array $options = []): string {
    return Uri::transformRelative($uri, $style, $options);
  }

  /**
   * Alias for Attributes::container().
   *
   * @todo deprecate and remove before or at 4.x after sub-modules.
   * @see https://www.drupal.org/node/3367291
   */
  public static function containerAttributes(array &$attributes, array $settings): void {
    Attributes::container($attributes, $settings);
  }

  /**
   * Alias for Internals::autoplay().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3367291
   */
  public static function autoplay($url, $check = TRUE): string {
    @trigger_error('autoplay is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::autoplay($url, $check);
  }

  /**
   * Alias for Url::create().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3367291
   */
  public static function createUrl($uri, $relative = FALSE): string {
    @trigger_error('autoplay is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Url::create($uri, $relative);
  }

  /**
   * Alias for Internals::formatTitle().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3367291
   */
  public static function formatTitle($value, $url, array $settings): array {
    @trigger_error('autoplay is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::formatTitle($value, $url, $settings);
  }

  /**
   * Alias for Internals::service().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3367291
   */
  public static function getService(string $key) {
    @trigger_error('autoplay is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::service($key);
  }

  /**
   * Alias for Internals::has().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3367291
   */
  public static function has($content, $needle): bool {
    @trigger_error('autoplay is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::has($content, $needle);
  }

  /**
   * Alias for Internals::init().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3367291
   */
  public static function initSettings(array $data = []): BlazySettings {
    @trigger_error('autoplay is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::init($data);
  }

  /**
   * Alias for Url::fromAny().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3367291
   */
  public static function toUrl(array $settings, $style = NULL, $uri = NULL): string {
    @trigger_error('autoplay is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Url::fromAny($settings, $style, $uri);
  }

  /**
   * Alias for Url::fromUri().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3367291
   */
  public static function url($uri, $style = NULL, array $options = []): string {
    @trigger_error('autoplay is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Url::fromUri($uri, $style, $options);
  }

  /**
   * Alias for Uri::isDataUri().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3367291
   */
  public static function isDataUri($url): bool {
    @trigger_error('autoplay is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Uri::isDataUri($url);
  }

  /**
   * Alias for Uri::isValid().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3367291
   */
  public static function isValidUri($uri): bool {
    @trigger_error('autoplay is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Uri::isValid($uri);
  }

  /**
   * Alias for Internals::versionGreaterThan().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3367291
   */
  public static function versionGreaterThan($deprecatedVersion): bool {
    @trigger_error('autoplay is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::versionGreaterThan($deprecatedVersion);
  }

  /**
   * Alias for Internals::versionGreaterThan().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3367291
   */
  public static function backwardsCompatibleCall(
    string $deprecatedVersion,
    callable $currentCallable,
    callable $deprecatedCallable,
  ): mixed {
    @trigger_error('autoplay is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::backwardsCompatibleCall($deprecatedVersion, $currentCallable, $deprecatedCallable);
  }

  /**
   * Alias for Entity::translated().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3367291
   */
  public static function translated($entity, $langcode = NULL): object {
    @trigger_error('autoplay is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Entity::translated($entity, $langcode);
  }

  /**
   * Alias for Internals::version().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3367291
   */
  public static function version($module): int {
    @trigger_error('autoplay is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::version($module);
  }

}
