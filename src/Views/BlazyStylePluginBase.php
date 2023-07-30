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
  protected function buildElement(array &$element, $row, $index) {
    $this->manager->hashtag($element);

    $settings = &$element['#settings'];
    $blazies  = $this->reset($settings);

    $blazies->set('delta', $index);

    // Add main image fields if so configured.
    if (!empty($settings['image'])) {
      // Supports individual grid/box image style either inline IMG, or CSS.
      $image                    = $this->getImageRenderable($settings, $row, $index);
      $element['#item']         = $this->getImageItem($image);
      $element[static::$itemId] = $image['rendered'] ?? [];
    }

    // Add layout field, may be a list field, or builtin layout options.
    if (!empty($settings['layout'])) {
      $this->getLayout($settings, $index);
    }

    // Add caption fields if so configured.
    $element[static::$captionId] = $this->getCaption($index, $settings);
  }

}
