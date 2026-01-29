<?php

namespace Drupal\blazy\Internals;

use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazyManagerInterface;
use Drupal\blazy\BlazySettings;

/**
 * Provides internal non-reusable blazy utilities.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class Initializer {

  /**
   * Returns the Blazy manager service if available.
   *
   * May return NULL in unit-test or early-bootstrap contexts.
   *
   * @return \Drupal\blazy\BlazyManagerInterface|null
   *   The blazy.manager instance.
   */
  public static function blazy(): ?BlazyManagerInterface {
    return self::service('blazy.manager');
  }

  /**
   * Returns a wrapper to pass tests, or DI where adding params is troublesome.
   */
  public static function service($service) {
    return \Drupal::hasService($service) ? \Drupal::service($service) : NULL;
  }

  /**
   * Returns the BlazySettings indtance.
   *
   * @param array $data
   *   The data being passed.
   *
   * @return \Drupal\blazy\BlazySettings
   *   The BlazySettings instance.
   */
  public static function init(array $data = []): BlazySettings {
    return new BlazySettings($data ?: BlazyDefault::htmlSettings());
  }

  /**
   * Alias for Settings::init().
   *
   * @param array $data
   *   The data being passed.
   *
   * @return \Drupal\blazy\BlazySettings
   *   The BlazySettings instance.
   */
  public static function settings(array $data = []): BlazySettings {
    return static::init($data);
  }

  /**
   * Checks if the object is the BlazySettings.
   *
   * @param mixed $value
   *   The BlazySettings object being passed.
   *
   * @return bool
   *   True if a BlazySettings.
   *
   * @phpstan-assert-if-true \Drupal\blazy\BlazySettings $value
   */
  public static function isBlazies(mixed $value): bool {
    return $value instanceof BlazySettings;
  }

  /**
   * Reset the BlazySettings per item to have unique URI, delta, style, etc.
   *
   * @param array $settings
   *   The settings being modified.
   * @param string $key
   *   The settings key.
   * @param array $defaults
   *   The defaults if any.
   */
  public static function reset(
    array &$settings,
    string $key = 'blazies',
    array $defaults = [],
  ): BlazySettings {
    // Other implementors should verify the $key prior to calling this.
    self::verify($settings, $key, $defaults);

    // The settings instance must be unique per item.
    /** @var \Drupal\blazy\BlazySettings $config */
    $config = &$settings[$key];
    if (!$config->was('reset')) {
      $config->reset($settings, $key);
      $config->set('was.reset', TRUE);
    }

    return $config;
  }

  /**
   * Verify `blazies` exists, in case accessed outside the workflow.
   *
   * @param array $settings
   *   The settings being modified.
   * @param string $key
   *   The settings key.
   * @param array $defaults
   *   The defaults if any.
   *
   * @return \Drupal\blazy\BlazySettings
   *   The BlazySettings instance.
   */
  public static function verify(
    array &$settings,
    string $key = 'blazies',
    array $defaults = [],
  ): BlazySettings {
    if (!isset($settings[$key])) {
      $settings += $defaults ?: Blazy::init();

      // A failsafe for edge cases:
      if (!isset($settings[$key])) {
        $settings[$key] = self::init();
      }
    }

    // In case overriden above without extending self::init().
    $settings += Blazy::init();
    return $settings[$key];
  }

  /**
   * Returns the BlazySettings from $settings which may contain blazies.
   *
   * The naming is simplified to just blazies, normally plural-like module name
   * to be unique.
   *
   * @param array $settings
   *   The settings being modified.
   * @param bool $merge
   *   Whether to merge with the settings or a new set.
   * @param string $key
   *   The key of settings: blazies, slicks, splides, etc.
   *
   * @return \Drupal\blazy\BlazySettings
   *   The BlazySettings instance.
   */
  public static function getBlazies(
    array &$settings,
    bool $merge = FALSE,
    string $key = 'blazies',
  ): BlazySettings {
    $blazies = $settings[$key] ?? NULL;

    $default = $merge ? $settings : [];
    return $blazies instanceof BlazySettings
      ? $blazies
      : self::verify($settings, $key, $default);
  }

}
