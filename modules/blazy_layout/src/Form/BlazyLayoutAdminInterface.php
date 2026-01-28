<?php

namespace Drupal\blazy_layout\Form;

use Drupal\blazy\Form\BlazyAdminInterface;

/**
 * Defines re-usable services and functions for BlazyLayoutManager forms.
 */
interface BlazyLayoutAdminInterface extends BlazyAdminInterface {

  /**
   * Returns base form elements.
   *
   * @param array<string, mixed> $form
   *   The modified form.
   * @param-out array<string, mixed> $form
   * @param array<string, mixed> $settings
   *   The settings being passed.
   * @param array<string, mixed> $options
   *   The extra options containing: excluded form elements and entity data.
   */
  public function formBase(array &$form, array $settings, array $options = []): void;

  /**
   * Returns color form elements.
   *
   * @param array<string, mixed> $form
   *   The modified form.
   * @param-out array<string, mixed> $form
   * @param array<string, mixed> $settings
   *   The settings being passed.
   * @param array<string, mixed> $options
   *   The extra options containing: excluded form elements and entity data.
   */
  public function formStyles(array &$form, array $settings, array $options = []): void;

  /**
   * Returns available form elements.
   *
   * @param array<string, mixed> $form
   *   The modified form.
   * @param-out array<string, mixed> $form
   * @param array<string, mixed> $settings
   *   The settings being passed.
   * @param array<string, mixed> $options
   *   The extra options containing: excluded form elements and entity data.
   */
  public function formSettings(array &$form, array $settings, array $options = []): void;

  /**
   * Returns wrapper form elements.
   *
   * @param array<string, mixed> $form
   *   The modified form.
   * @param-out array<string, mixed> $form
   * @param array<string, mixed> $settings
   *   The stored settings.
   * @param array<string, mixed> $options
   *   The extra options containing: excluded form elements and entity data.
   * @param bool $root
   *   Whether applicable to root, or region elements.
   */
  public function formWrappers(
    array &$form,
    array $settings,
    array $options = [],
    $root = TRUE,
  ): void;

}
