<?php

namespace Drupal\blazy\Utility;

/**
 * Provides common type utilities.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Please use the public method instead.
 */
class Type {

  /**
   * Normalize potential mixed values.
   *
   * @param mixed $value
   *   The value.
   * @param bool $default
   *   The default value.
   *
   * @return bool
   *   Returns TRUE or FALSE.
   */
  public static function normalizeBool(mixed $value, bool $default = FALSE): bool {
    if (is_bool($value)) {
      return $value;
    }

    $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $bool ?? $default;
  }

}
