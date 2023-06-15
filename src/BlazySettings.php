<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\NestedArray;

/**
 * Provides settings object.
 *
 * @todo convert settings into BlazySettings instance at blazy:3.+ if you can.
 */
class BlazySettings implements \Countable {

  /**
   * Stores the settings.
   *
   * @var \stdClass[]
   */
  protected $storage = [];

  /**
   * Creates a new BlazySettings instance.
   *
   * @param \stdClass[] $storage
   *   The storage.
   */
  public function __construct(array $storage) {
    $this->storage = $storage ? Blazy::arrayFilter($storage) : [];
  }

  /**
   * Counts total items.
   */
  public function count(): int {
    return count($this->storage);
  }

  /**
   * Returns values from a key.
   *
   * @param string $key
   *   The storage key.
   * @param string $default_value
   *   The storage default_value.
   *
   * @return mixed
   *   A mixed value (array, string, bool, null, etc.).
   */
  public function get($key = NULL, $default_value = NULL) {
    if (empty($key)) {
      return $this->storage;
    }

    $parts = array_map('trim', explode('.', $key));
    if (count($parts) == 1) {
      return $this->storage[$key] ?? $default_value;
    }

    $value = NestedArray::getValue($this->storage, $parts, $key_exists);
    return $key_exists ? $value : $default_value;
  }

  /**
   * Returns a convenient shortcut to get a feature with a `data` key.
   *
   * @param string $key
   *   The storage key.
   * @param array $default_value
   *   The storage default_value.
   *
   * @return array
   *   The array of items inside the data key, or empty array.
   */
  public function data($key, array $default_value = []): array {
    return $this->get('data.' . $key, $default_value) ?: [];
  }

  /**
   * Returns a convenient shortcut to get a feature with a `filter` key.
   *
   * @param string $key
   *   The storage key.
   * @param string $default_value
   *   The storage default_value.
   * @param string $namespace
   *   The plugin namespace.
   *
   * @return mixed
   *   A mixed value (array, string, bool, null, etc.).
   */
  public function filter($key, $default_value = NULL, $namespace = 'blazy') {
    return $this->get('filter.' . $namespace . '.' . $key, $default_value);
  }

  /**
   * Returns a convenient shortcut to get a feature with an `form` key.
   *
   * @param string $key
   *   The storage key.
   * @param bool $default_value
   *   The storage default_value.
   *
   * @return bool
   *   Returns TRUE or FALSE.
   */
  public function form($key, $default_value = FALSE): bool {
    return $this->get('form.' . $key, $default_value);
  }

  /**
   * Returns a convenient shortcut to get a feature with an `is` key.
   *
   * @param string $key
   *   The storage key.
   * @param bool $default_value
   *   The storage default_value.
   *
   * @return bool
   *   Returns TRUE or FALSE.
   */
  public function is($key, $default_value = FALSE): bool {
    return $this->get('is.' . $key, $default_value);
  }

  /**
   * Returns a convenient shortcut to get a feature with a `was` key.
   *
   * To verify if the expected workflow is by-passed when the key was missing.
   *
   * @param string $key
   *   The storage key.
   * @param bool $default_value
   *   The storage default_value.
   *
   * @return bool
   *   Returns TRUE or FALSE.
   */
  public function was($key, $default_value = FALSE): bool {
    return $this->get('was.' . $key, $default_value);
  }

  /**
   * Returns a convenient shortcut to get a feature with a `use` key.
   *
   * @param string $key
   *   The storage key.
   * @param bool $default_value
   *   The storage default_value.
   *
   * @return bool
   *   Returns TRUE or FALSE.
   */
  public function use($key, $default_value = FALSE): bool {
    return $this->get('use.' . $key, $default_value);
  }

  /**
   * Returns a convenient shortcut to get a feature with a `ui` key.
   *
   * @param string $key
   *   The storage key.
   * @param string $default_value
   *   The storage default_value.
   *
   * @return mixed
   *   A mixed value (array, string, bool, null, etc.).
   */
  public function ui($key, $default_value = NULL) {
    return $this->get('ui.' . $key, $default_value);
  }

  /**
   * Sets values for a key.
   */
  public function set($key, $value = NULL, $merge = FALSE): self {
    if (is_array($key) && !isset($value)) {
      foreach ($key as $k => $v) {
        $this->storage[$k] = $v;
      }
      return $this;
    }

    $parts = array_map('trim', explode('.', $key));

    if (is_array($value) && $merge) {
      $value = array_merge((array) $this->get($key, []), $value);
    }

    if (count($parts) == 1) {
      $this->storage[$key] = $value;
    }
    else {
      NestedArray::setValue($this->storage, $parts, $value);
    }
    return $this;
  }

  /**
   * Merges data into a configuration object.
   *
   * @param array $data_to_merge
   *   An array containing data to merge.
   *
   * @return $this
   *   The configuration object.
   */
  public function merge(array $data_to_merge): self {
    // Preserve integer keys so that configuration keys are not changed.
    $this->setData(NestedArray::mergeDeepArray([$this->storage, $data_to_merge], TRUE));
    return $this;
  }

  /**
   * Replaces the data of this configuration object.
   *
   * @param array $data
   *   The new configuration data.
   *
   * @return $this
   *   The configuration object.
   */
  public function setData(array $data): self {
    $this->storage = $data;
    return $this;
  }

  /**
   * Removes item from this.
   *
   * @param string $key
   *   The key to unset.
   *
   * @return $this
   *   The configuration object.
   */
  public function unset($key): self {
    $parts = array_map('trim', explode('.', $key));
    if (count($parts) == 1) {
      unset($this->storage[$key]);
    }
    else {
      NestedArray::unsetValue($this->storage, $parts);
    }
    return $this;
  }

  /**
   * Check if a config by its key exists.
   *
   * @param string $key
   *   The key to check.
   * @param string|object $group
   *   The BlazySettings as sub-key to check for.
   *
   * @return bool
   *   True if found.
   */
  public function isset($key, $group = NULL): bool {
    $found = FALSE;
    $parts = array_map('trim', explode('.', $key));
    if (count($parts) == 1) {
      if ($group) {
        if (is_string($group)) {
          $found = isset($this->storage[$group][$key]);
        }
        elseif ($group instanceof BlazySettings) {
          $found = isset($group->storage()[$key]);
        }
      }
      else {
        $found = isset($this->storage[$key]);
      }
    }
    else {
      // @fixme not working, yet.
      $found = NestedArray::keyExists($parts, $this->storage);
    }
    return $found;
  }

  /**
   * Reset or renew the BlazySettings object.
   *
   * Normally called at item level so to get correct delta or settings per item.
   *
   * @param array $settings
   *   The settings to reset/ renew the instance.
   * @param string $key
   *   The key inditifying this reset object.
   *
   * @return \Drupal\blazy\BlazySettings
   *   The new BlazySettings instance.
   */
  public function reset(array &$settings, $key = 'blazies'): BlazySettings {
    $data = $this->storage;

    if ($data && $this->is('debug')) {
      $this->rksort($data);
    }

    $instance = new BlazySettings($data);
    $settings[$key] = $instance;
    return $instance;
  }

  /**
   * Returns the whole array.
   */
  public function storage(): array {
    return $this->storage;
  }

  /**
   * Sorts recursively.
   */
  private function rksort(&$a): bool {
    if (!is_array($a)) {
      return FALSE;
    }

    ksort($a);
    foreach ($a as $k => $v) {
      $this->rksort($a[$k]);
    }
    return TRUE;
  }

}
