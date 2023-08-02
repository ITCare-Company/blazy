<?php

namespace Drupal\blazy\Field;

use Drupal\Core\Render\Markup;
use Drupal\blazy\BlazyDefault;

/**
 * A Trait for blazy element and its captions.
 *
 * This is a preliminary exercise for 3.x mergers.
 * We all have similar IMAGE + CAPTION constructs. The only difference is
 * sub-modules separate blazy image from captions while Blazy merges them.
 * Plus thumbnails, already managed by themselves, not blazy's business.
 *
 * Normally required as separate element.caption by sub-modules. This allows
 * improvements at one go, seen like below issues with poorly informed
 * thumbnails. With the integrated captions inside Blazy, this opens up some fun
 * or cool kids like hoverable effects between image and captions, etc. in
 * one place for the entire ecosystem rather than working with each sub-modules.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 */
trait BlazyElementTrait {

  /**
   * The svg manager service.
   *
   * @var \Drupal\blazy\Media\Svg\SvgInterface
   */
  protected $svgManager;

  /**
   * Provides relevant attributes to feed into theme_blazy().
   */
  protected function toBlazy(array &$data, array &$captions, $delta): bool {
    // Call manager not formatter due to sub-module deviations.
    if ($captions = array_filter($captions)) {
      $this->manager->toBlazy($data, $captions, $delta);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Builds the item using theme_blazy(), if so-configured.
   *
   * This is the future implementation after mergers at/by 3.x.
   */
  protected function themeBlazy(array $data, array $captions, $delta): array {
    $internal = $data;

    // Allows sub-modules to use theme_blazy() as their theme_ITEM() contents.
    if ($this->toBlazy($internal, $captions, $delta)) {
      $internal['captions'] = $captions;
    }

    // Provides inline SVG if applicable.
    $this->viewSvg($internal);

    $blazy = $this->formatter->getBlazy($internal);

    if (static::$namespace == 'blazy') {
      $element = $blazy;
    }
    else {
      // Only blazy has content, unset here.
      unset($data['content']);

      // This also might be just removed at 3.x, so to leave it all to blazy.
      // Currently still needed as fallback due to being optional.
      $element = $data;
      $element[static::$itemId] = $blazy;
      $this->updateSettings($element, $blazy);
    }
    return $element;
  }

  /**
   * This is the current implementation before mergers at 3.x.
   *
   * Looks simpler, yet it has lots of dup efforts downstream.
   */
  protected function themeItem(array $data, array $captions, $delta): array {
    $internal = $data;

    // Provides inline SVG if applicable.
    $this->viewSvg($internal);

    // Split for different formatters with very minimal difference.
    if (static::$namespace == 'blazy') {
      $internal[static::$captionId] = $captions;
      $element = $this->formatter->getBlazy($internal);
    }
    else {
      $blazy = $this->formatter->getBlazy($internal);
      // Only blazy has content, unset here.
      unset($data['content']);

      $element = $data;

      $element[static::$itemId] = $blazy;
      $element[static::$captionId] = $captions;

      $this->updateSettings($element, $blazy);
    }
    return $element;
  }

  /**
   * Thumbnails are poorly-informed, provide relevant information.
   */
  protected function updateSettings(array &$element, array $blazy): void {
    $item_build = $blazy['#build'] ?? [];

    // Update with blazy processed settings: unstyled extensions, SVG, etc.
    if ($blazysets = $this->formatter->toHashtag($item_build)) {
      $element['#settings']['blazies']->merge($blazysets['blazies']->storage());
    }
  }

  /**
   * Provides inline SVG if so-configured.
   */
  protected function viewSvg(array &$element): void {
    $settings = $this->formatter->toHashtag($element);
    $blazies  = $settings['blazies'];
    $inline   = $settings['svg_inline'] ?? FALSE;
    $bg       = $settings['background'] ?? FALSE;
    $exist    = $blazies->is('svg_sanitizer');
    $valid    = $inline && $exist && !$bg;

    if ($valid && $uri = $blazies->get('image.uri')) {
      $options = BlazyDefault::toSvgOptions($settings);
      if ($output = $this->svgManager->view($uri, $options)) {
        $element['content'][] = ['#markup' => Markup::create($output)];
      }
    }
  }

}
