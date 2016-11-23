<?php

namespace Drupal\blazy;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Serialization\Json;
use Drupal\image\Entity\ImageStyle;

/**
 * Implements a public facing blazy manager.
 *
 * A few modules re-use this: GridStack, Mason, Slick...
 *
 * @see \Drupal\gridstack\Plugin\views\style\GridStackViews::render().
 * @see \Drupal\slick_views\Plugin\views\style\SlickViews::render().
 * @see template_preprocess_slick().
 * @see template_preprocess_gridstack().
 */
class BlazyManager extends BlazyManagerBase {

  /**
   * Used at top-level element: Cleans up empty breakpoints.
   */
  public function cleanUpBreakpoints(array &$settings = []) {
    if (empty($settings['breakpoints'])) {
      return;
    }

    foreach ($settings['breakpoints'] as $key => &$breakpoint) {
      $breakpoint = array_filter($breakpoint);
      if (empty($breakpoint['width']) || empty($breakpoint['image_style'])) {
        unset($settings['breakpoints'][$key]);
      }
    }

    // Identify that Blazy can be activated only by breakpoints.
    if (empty($settings['blazy'])) {
      $settings['blazy'] = !empty($settings['breakpoints']);
    }
  }

  /**
   * Checks for Blazy formatter such as from within a Views style plugin.
   *
   * Ensures the settings traverse up to the container where Blazy is clueless.
   * The supported plugins can add [data-blazy] attribute into its container
   * containing $settings['blazy_data'] converted into [data-blazy] JSON.
   *
   * @todo unified way between Views styles, Views fields and field formatters.
   */
  public function isBlazy(array &$settings = [], $item = []) {
    // Retrieves Blazy formatter related settings from within Views style.
    $item_id = $settings['item_id'];
    $content = isset($item[$item_id]) ? $item[$item_id] : $item;

    // 1. Blazy formatter within Views fields by supported modules.
    // Image/Media related slick formatters, e.g.:
    // \Drupal\slick\Plugin\Field\FieldFormatter\SlickFileFormatterBase.
    // \Drupal\blazy\Dejavu\BlazyEntityReferenceBase
    if (isset($item['settings'])) {
      $blazy = isset($content['#build']['settings']) ? $content['#build']['settings'] : [];

      // Allows breakpoints overrides such as multi-styled images by GridStack.
      if (empty($settings['breakpoints']) && isset($blazy['breakpoints'])) {
        $settings['breakpoints'] = $blazy['breakpoints'];
      }

      foreach (['blazy', 'box_style', 'image_style', 'lazy', 'media_switch', 'ratio', 'uri'] as $key) {
        $fallback = isset($settings[$key]) ? $settings[$key] : '';
        $settings[$key] = isset($blazy[$key]) && empty($fallback) ? $blazy[$key] : $fallback;
      }
    }

    // 2. Blazy Views fields by supported modules.
    if (isset($content['#view']) && ($view = $content['#view'])) {
      if ($blazy_field = Blazy::blazyViewsField($view)) {
        $settings = array_merge(array_filter($blazy_field->mergedViewsSettings()), array_filter($settings));
      }
    }

    // Provide data for the [data-blazy] attribute at the containing element.
    // Supported modules can add blazy_data as [data-blazy] to the container.
    $this->buildDataBlazy($settings);
  }

  /**
   * Builds breakpoints suitable for top-level [data-blazy] wrapper attributes.
   */
  public function buildDataBlazy(array &$settings = []) {
    $json = $sources = [];
    if (!empty($settings['breakpoints'])) {
      $end = end($settings['breakpoints']);
      foreach ($settings['breakpoints'] as $key => $breakpoint) {
        if (empty($breakpoint['image_style']) || empty($breakpoint['width'])) {
          continue;
        }

        if (!empty($settings['background']) && ($style = ImageStyle::load($breakpoint['image_style']))) {
          $point = trim($breakpoint['width']);
          $width = Blazy::widthFromDescriptors($point);

          $sources[] = ['width' => (int) $width, 'src' => 'data-src-' . $key];
        }
      }

      // Identify that Blazy can be activated only by breakpoints.
      $settings['blazy'] = TRUE;
    }

    // As of Blazy v1.6.0 applied to BG only.
    if ($sources) {
      $json['breakpoints'] = $sources;
    }

    $json['ratio'] = empty($settings['ratio']) ? FALSE : $settings['ratio'];

    // Provide data for the [data-blazy] attribute at the containing element.
    // Supported modules can add blazy_data as [data-blazy] to the container.
    $settings['blazy_data'] = $json;
  }

  /**
   * Returns the image based on the Responsive image mapping, or blazy.
   */
  public function getImage($build = []) {
    /** @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
    $item     = $build['item'];
    $settings = &$build['settings'];
    $theme    = isset($settings['theme_hook_image']) ? $settings['theme_hook_image'] : 'blazy';

    if (empty($item)) {
      return [];
    }

    $settings['delta']       = isset($settings['delta']) ? $settings['delta'] : 0;
    $settings['image_style'] = isset($settings['image_style']) ? $settings['image_style'] : '';
    $settings['namespace']   = empty($settings['namespace']) ? 'blazy' : $settings['namespace'];

    if (!isset($settings['uri'])) {
      $settings['uri'] = ($entity = $item->entity) && empty($item->uri) ? $entity->getFileUri() : $item->uri;
    }

    if ($theme == 'blazy') {
      $settings['blazy'] = TRUE;
    }

    $image = [
      '#theme'       => $theme,
      '#item'        => [],
      '#delta'       => $settings['delta'],
      '#image_style' => $settings['image_style'],
      '#build'       => $build,
      '#pre_render'  => [[$this, 'preRenderImage']],
    ];

    $this->getModuleHandler()->alter('blazy', $image, $settings);

    return $image;
  }

  /**
   * Builds the Blazy image as a structured array ready for ::renderer().
   */
  public function preRenderImage($element) {
    $build = $element['#build'];
    $item  = $build['item'];
    unset($element['#build']);

    $settings = &$build['settings'];
    if (empty($item)) {
      return [];
    }

    // Extract field item attributes for the theme function, and unset them
    // from the $item so that the field template does not re-render them.
    $item_attributes = [];
    if (isset($item->_attributes)) {
      $item_attributes = $item->_attributes;
      unset($item->_attributes);
    }

    $element['#item'] = $item;

    // Responsive image integration.
    $settings['responsive_image_style_id'] = '';
    if (!empty($settings['resimage']) && !empty($settings['responsive_image_style'])) {
      $responsive_image_style = $this->entityLoad($settings['responsive_image_style'], 'responsive_image_style');
      $settings['responsive_image_style_id'] = $responsive_image_style->id() ?: '';
      $settings['lazy'] = '';
      if (!empty($settings['responsive_image_style_id'])) {
        if ($this->configLoad('responsive_image')) {
          $item_attributes['data-srcset'] = TRUE;
          $settings['lazy'] = 'responsive';
        }
        $element['#cache']['tags'] = $this->getResponsiveImageCacheTags($responsive_image_style);
      }
    }

    $element['#url']             = '';
    $element['#settings']        = $settings;
    $element['#captions']        = isset($build['captions']) ? ['inline' => $build['captions']] : [];
    $element['#item_attributes'] = $item_attributes;

    if (!empty($settings['media_switch'])) {
      if ($settings['media_switch'] == 'content' && !empty($settings['absolute_path'])) {
        $element['#url'] = $settings['absolute_path'];
      }
      elseif ($this->getLightboxes() && in_array($settings['media_switch'], $this->getLightboxes())) {
        BlazyLightbox::switchMedia($element);
      }
    }

    return $element;
  }

  /**
   * Returns the entity view, if available.
   */
  public function getEntityView($entity = NULL, $settings = [], $fallback = '') {
    if ($entity && $entity instanceof EntityInterface) {
      $entity_type_id = $entity->getEntityTypeId();
      $view_hook      = $entity_type_id . '_view';
      $view_mode      = empty($settings['view_mode']) ? 'default' : $settings['view_mode'];
      $langcode       = $entity->language()->getId();

      // If module implements own {entity_type}_view.
      if (function_exists($view_hook)) {
        return $view_hook($entity, $view_mode, $langcode);
      }
      // If entity has view_builder handler.
      elseif ($this->getEntityTypeManager()->hasHandler($entity_type_id, 'view_builder')) {
        return $this->getEntityTypeManager()->getViewBuilder($entity_type_id)->view($entity, $view_mode, $langcode);
      }
      elseif ($fallback) {
        return ['#markup' => $fallback];
      }
    }

    return FALSE;
  }

  /**
   * Returns the Responsive image cache tags.
   */
  public function getResponsiveImageCacheTags($responsive_image_style = NULL) {
    $cache_tags = [];
    $image_styles_to_load = [];
    if ($responsive_image_style) {
      $cache_tags = Cache::mergeTags($cache_tags, $responsive_image_style->getCacheTags());
      $image_styles_to_load = $responsive_image_style->getImageStyleIds();
    }

    $image_styles = $this->entityLoadMultiple('image_style', $image_styles_to_load);
    foreach ($image_styles as $image_style) {
      $cache_tags = Cache::mergeTags($cache_tags, $image_style->getCacheTags());
    }
    return $cache_tags;
  }

  /**
   * Now included within theme_blazy().
   *
   * @deprecated: Removed for Blazy::buildBreakpointAttributes().
   */
  public function getUrlBreakpoints(array &$settings = []) {}

  /**
   * Now included within theme_blazy().
   *
   * @deprecated: Removed prior to release for Blazy::buildUrl().
   */
  public function getUrlDimensions(array &$settings = [], $item = NULL, $modifier = NULL) {}

  /**
   * Now included within BlazyLightbox.
   *
   * @deprecated: Removed prior to release for BlazyLightbox::switchMedia().
   */
  public function getMediaSwitch(array &$element = []) {
    BlazyLightbox::switchMedia($element);
  }

}
