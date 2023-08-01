<?php

namespace Drupal\blazy\Views;

/**
 * A base for blazy views integration to support fieldable entities.
 *
 * @see \Drupal\mason\Plugin\views\style\MasonViews
 * @see \Drupal\gridstack\Plugin\views\style\GridStackViews
 * @see \Drupal\slick_views\Plugin\views\style\SlickViews
 * @see \Drupal\splide\Plugin\views\style\SplideViews
 */
abstract class BlazyStylePluginBase extends BlazyStyleBase implements BlazyStylePluginInterface {

  use BlazyStyleOptionsTrait;
  use BlazyStylePluginTrait;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function buildElement(array &$element, $row, $delta) {
    $this->manager->hashtag($element);

    $settings = &$element['#settings'];
    $blazies  = $this->reset($settings);
    $_image   = $settings['image'] ?? NULL;

    $blazies->set('delta', $delta);
    $captions = $this->getCaption($delta, $settings);

    if ($extras = $element[static::$captionId] ?? []) {
      $captions = array_merge($captions, $extras);
      unset($element[static::$captionId]);
    }

    // Add layout field, may be a list field, or builtin layout options.
    if (!empty($settings['layout'])) {
      $this->getLayout($settings, $delta);
    }

    // Add main image fields if so configured.
    // Supports individual grid/box image style either inline IMG, or CSS.
    $element['#delta'] = $delta;
    if ($_image || $captions) {
      $image = $this->getImageRenderable($settings, $row, $delta);
      $element['#item'] = $image['raw'] ?? NULL;

      // @todo merge all these into theme_blazy() at 3.x after sub-modules.
      // @todo use $blazies = $this->manager->preBlazy($data, $item);
      if ($blazies->use('theme_blazy')) {
        $this->themeBlazy($element, $captions, $delta);
      }
      else {
        // @todo remove at 3.x.
        $this->themeItem($element, $captions, $delta);
      }
    }
  }

  /**
   * Provides relevant attributes to feed into theme_blazy().
   */
  private function toBlazy(array &$data, array &$captions, $delta): bool {
    // Call manager not blazyManager due to sub-module deviations.
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
  private function themeBlazy(array &$element, array $captions, $delta): void {
    $internal = $element;

    // Allows sub-modules to use theme_blazy() as their theme_ITEM() contents.
    if ($this->toBlazy($internal, $captions, $delta)) {
      $internal['captions'] = $captions;
    }

    if ($blazy = $this->blazyManager->getBlazy($internal)) {
      $element[static::$itemId] = $blazy;
      $this->updateSettings($element, $blazy);
    }
    else {
      $element[static::$captionId] = $captions;
    }
  }

  /**
   * This is the current implementation before mergers at 3.x.
   *
   * Looks simpler, yet it has lots of dup efforts downstream.
   */
  private function themeItem(array &$element, array $captions, $delta): void {
    $internal = $element;

    if ($blazy = $this->blazyManager->getBlazy($internal)) {
      $element[static::$itemId] = $blazy;
      $this->updateSettings($element, $blazy);
    }

    $element[static::$captionId] = $captions;
  }

  /**
   * Thumbnails are poorly-informed, provide relevant information.
   */
  private function updateSettings(array &$element, array $blazy): void {
    $item_build = $blazy['#build'] ?? [];

    // Update with blazy processed settings: unstyled extensions, SVG, etc.
    if ($blazysets = $this->manager->toHashtag($item_build)) {
      $element['#settings']['blazies']->merge($blazysets['blazies']->storage());
    }
  }

}
