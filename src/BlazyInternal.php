<?php

namespace Drupal\blazy;

use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Utility\Check;
use Drupal\blazy\Utility\CheckItem;
use Drupal\blazy\Utility\Sanitize;

/**
 * Provides internal non-reusable blazy utilities.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class BlazyInternal {

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
   * Prepares the essential settings, URI, delta, cache , etc.
   */
  public static function prepare(array &$settings, $item, $called = FALSE): void {
    CheckItem::essentials($settings, $item, $called);
    CheckItem::insanity($settings);
  }

  /**
   * Blazy is prepared with an URI.
   */
  public static function prepared(array &$settings, $item): void {
    BlazyImage::prepare($settings, $item);
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
  public static function preserve(array &$parentsets, array &$childsets): void {
    // @todo add more formatter related settings where Views styles have none.
    $cherries = BlazyDefault::cherrySettings();

    foreach ($cherries as $key => $value) {
      $fallback = $parentsets[$key] ?? $value;
      // Ensures to respect parent formatter, or Views style if provided.
      $parentsets[$key] = isset($childsets[$key]) && empty($fallback)
        ? $childsets[$key]
        : $fallback;
    }

    $parent = $parentsets['blazies'] ?? NULL;
    $child  = $childsets['blazies'] ?? NULL;

    if ($parent && $child) {
      // $parent->set('first.settings', array_filter($child));
      // $parent->set('first.item_id', $child->get('item.id'));
      // Hints containers to build relevant lightbox gallery attributes.
      $childbox  = $child->get('lightbox.name');
      $parentbox = $parent->get('lightbox.name');

      // Ensures to respect parent formatter or Views style if provided.
      // The moral of this method is only if parent lacks of settings like Grid.
      // Other settings are not parents' business. Only concerns about those
      // needed by the container, e.g. LIGHTBOX for [data-LIGHTBOX-gallery].
      if ($childbox && !$parentbox) {
        // @todo use Check::lightboxes($settings);
        $optionset = $child->get('lightbox.optionset', $childbox) ?: $childbox;
        $parent->set('lightbox.name', $childbox)
          ->set($childbox, $optionset)
          ->set('is.lightbox', TRUE)
          ->set('switch', $child->get('switch'));

        // Now that we got a child lightbox, overrides parent for sure.
        $parentsets['media_switch'] = $childbox;
      }

      $parent->set('first', $child->get('first'), TRUE)
        ->set('was.preserve', TRUE);
    }
  }

  /**
   * Preliminary settings, normally at container/ global level.
   *
   * @todo refine to separate container from item level. At least move grid out.
   */
  public static function preSettings(array &$settings, $root = TRUE): void {
    Blazy::verify($settings);

    $blazies = $settings['blazies'];

    // Checks for basic features, here for both formatters and views fields.
    // To detect available media bundles from views field when
    // BlazyEntity::prepare() was called too early before media data set.
    // @todo move it back after initialized after both are synced.
    Check::container($settings);

    if ($blazies->was('initialized')) {
      return;
    }

    // Checks for lightboxes.
    Check::lightboxes($settings);

    // Checks for grids.
    if ($root) {
      Check::grids($settings);
    }

    // Checks for Image styles, excluding Responsive image.
    BlazyImage::styles($settings);

    // Checks for lazy.
    Check::lazyOrNot($settings);

    // Marks it processed.
    $blazies->set('was.initialized', TRUE);
  }

  /**
   * Modifies the common UI settings inherited down to each item.
   */
  public static function postSettings(array &$settings): void {
    // Failsafe, might be called directly at ::attach() outside the workflow.
    Blazy::verify($settings);

    $blazies = $settings['blazies'];
    if (!$blazies->was('initialized')) {
      self::preSettings($settings);
    }
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

}
