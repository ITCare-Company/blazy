<?php

namespace Drupal\blazy;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\image\Entity\ImageStyle;

/**
 * Implements a public facing blazy manager.
 *
 * A few modules re-use this: GridStack, Mason, Slick...
 */
class BlazyManager extends BlazyManagerBase {

  /**
   * Cleans up empty breakpoints.
   *
   * @param array $settings
   *   The settings being modified.
   */
  public function cleanUpBreakpoints(array &$settings = []) {
    if (empty($settings['breakpoints'])) {
      return;
    }

    foreach ($settings['breakpoints'] as $key => &$breakpoint) {
      $breakpoint = array_filter($breakpoint);
      if (empty($breakpoint['width']) && empty($breakpoint['image_style'])) {
        unset($settings['breakpoints'][$key]);
      }
    }

    // Identify that Blazy can be activated only by breakpoints.
    if (empty($settings['blazy'])) {
      $settings['blazy'] = !empty($settings['breakpoints']);
    }
  }

  /**
   * Sets dimensions once, if applicable, such as with a crop image style.
   *
   * @param array $settings
   *   The settings being modified.
   * @param \Drupal\image\Plugin\Field\FieldType\ImageItem $item
   *   The image item.
   */
  public function setDimensionsOnce(array &$settings = [], $item = NULL) {
    $dimensions['width']  = $settings['original_width'] = isset($item->width) ? $item->width : NULL;
    $dimensions['height'] = $settings['original_height'] = isset($item->height) ? $item->height : NULL;

    // If image style contains crop, sets dimension once, and let all inherit.
    if (($style = ImageStyle::load($settings['image_style'])) && Blazy::isCrop($style)) {
      $style->transformDimensions($dimensions, $settings['uri']);

      $settings['height'] = $dimensions['height'];
      $settings['width']  = $dimensions['width'];

      // Informs individual images that dimensions are already set once.
      $settings['_dimensions'] = TRUE;
    }

    // Also sets breakpoint dimensions once, if cropped.
    if (!empty($settings['breakpoints'])) {
      $this->buildDataBlazy($settings, $item);
    }
    unset($settings['uri']);
  }

  /**
   * Checks for Blazy formatter such as from within a Views style plugin.
   *
   * Ensures the settings traverse up to the container where Blazy is clueless.
   * The supported plugins can add [data-blazy] attribute into its container
   * containing $settings['blazy_data'] converted into [data-blazy] JSON.
   *
   * @param array $settings
   *   The settings being modified.
   * @param \Drupal\image\Plugin\Field\FieldType\ImageItem $item
   *   The image item.
   */
  public function isBlazy(array &$settings = [], $item = []) {
    // Retrieves Blazy formatter related settings from within Views style.
    $item_id = $settings['item_id'];
    $content = isset($item[$item_id]) ? $item[$item_id] : $item;

    // 1. Blazy formatter within Views fields by supported modules.
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

    // Provides data for the [data-blazy] attribute at the containing element.
    $this->cleanUpBreakpoints($settings);
    if (!empty($settings['breakpoints'])) {
      $image = isset($item['item']) ? $item['item'] : NULL;
      $this->buildDataBlazy($settings, $image);
    }
    unset($settings['uri']);
  }

  /**
   * Builds breakpoints suitable for top-level [data-blazy] wrapper attributes.
   *
   * The hustle is because we need to define dimensions once, if applicable, and
   * let all images inherit. Each breakpoint image may be cropped, or scaled
   * without a crop. To set dimensions once requires all breakpoint images
   * uniformly cropped. But that is not always the case.
   *
   * @param array $settings
   *   The settings being modified.
   * @param \Drupal\image\Plugin\Field\FieldType\ImageItem $item
   *   The image item.
   *
   * @todo: Refine this like everything else.
   */
  public function buildDataBlazy(array &$settings = [], $item = NULL) {
    // Early opt-out if blazy_data has already been defined.
    // Blazy doesn't always deal with image directly.
    if (!empty($settings['blazy_data'])) {
      return;
    }

    if (empty($settings['original_width'])) {
      $settings['original_width'] = isset($item->width) ? $item->width : NULL;
      $settings['original_height'] = isset($item->height) ? $item->height : NULL;
    }

    $json = $sources = [];
    $end = end($settings['breakpoints']);
    foreach ($settings['breakpoints'] as $key => $breakpoint) {
      if ($width = Blazy::widthFromDescriptors($breakpoint['width'])) {
        // If contains crop, sets dimension once, and let all images inherit.
        if (!empty($settings['uri']) && !empty($settings['ratio'])) {
          $dimensions['width'] = $settings['original_width'];
          $dimensions['height'] = $settings['original_height'];

          if (($style = ImageStyle::load($breakpoint['image_style']))) {
            $style->transformDimensions($dimensions, $settings['uri']);
            if (Blazy::isCrop($style)) {
              $padding = round((($dimensions['height'] / $dimensions['width']) * 100), 2);
              $json['dimensions'][$width] = $padding;

              // Only set CSS padding-bottom value for the last breakpoint.
              if ($end['width'] == $breakpoint['width']) {
                $settings['padding_bottom'] = $padding;
              }
            }
          }
        }

        // If BG, provide [data-src-BREAKPOINT].
        if (!empty($settings['background'])) {
          $sources[] = ['width' => (int) $width, 'src' => 'data-src-' . $key];
        }
      }
    }

    // As of Blazy v1.6.0 applied to BG only.
    if ($sources) {
      $json['breakpoints'] = $sources;
    }

    // @todo: A more efficient way not to do this in the first place.
    // ATM, this is okay as this method is run once on the top-level container.
    if (isset($json['dimensions']) && (count($settings['breakpoints']) != count($json['dimensions']))) {
      unset($json['dimensions']);
    }

    // Supported modules can add blazy_data as [data-blazy] to the container.
    if ($json) {
      $settings['blazy_data'] = $json;
    }

    // Identify that Blazy can be activated only by breakpoints.
    $settings['blazy'] = TRUE;
  }

  /**
   * Returns the enforced content, or image using theme_blazy().
   *
   * @param array $build
   *   The array containing: item, content, settings, or optional captions.
   *
   * @return array
   *   The alterable and renderable array of enforced content, or theme_blazy().
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

    if (empty($settings['uri'])) {
      $settings['uri'] = ($entity = $item->entity) && empty($item->uri) ? $entity->getFileUri() : $item->uri;
    }

    if ($theme == 'blazy') {
      $settings['blazy'] = TRUE;
    }

    // Respects content not handled by theme_blazy(), but passed through.
    if (empty($build['content'])) {
      $image = [
        '#theme'       => $theme,
        '#delta'       => $settings['delta'],
        '#item'        => [],
        '#image_style' => $settings['image_style'],
        '#build'       => $build,
        '#pre_render'  => [[$this, 'preRenderImage']],
      ];
    }
    else {
      $image = $build['content'];
    }

    $this->getModuleHandler()->alter('blazy', $image, $settings);

    return $image;
  }

  /**
   * Builds the Blazy image as a structured array ready for ::renderer().
   *
   * @param array $element
   *   The pre-rendered element.
   *
   * @return array
   *   The renderable array of pre-rendered element.
   */
  public function preRenderImage($element) {
    $build = $element['#build'];
    $item  = $build['item'];
    unset($element['#build']);

    $settings = $build['settings'];
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
    else {
      if (!isset($settings['_no_cache'])) {
        $file_tags = isset($settings['file_tags']) ? $settings['file_tags'] : [];
        $settings['cache_tags'] = empty($settings['cache_tags']) ? $file_tags : Cache::mergeTags($settings['cache_tags'], $file_tags);
        $element['#cache'] = ['tags' => $settings['cache_tags']];

        if (!empty($settings['cache_keys'])) {
          $element['#cache']['keys'] = $settings['cache_keys'];
        }
      }
    }

    $element['#item']            = $item;
    $element['#captions']        = isset($build['captions']) ? ['inline' => $build['captions']] : [];
    $element['#item_attributes'] = $item_attributes;
    $element['#url']             = '';
    $element['#settings']        = $settings;

    if (!empty($settings['media_switch'])) {
      if ($settings['media_switch'] == 'content' && !empty($settings['absolute_path'])) {
        $element['#url'] = $settings['absolute_path'];
      }
      elseif ($this->getLightboxes() && in_array($settings['media_switch'], $this->getLightboxes())) {
        BlazyLightbox::build($element);
      }
    }

    return $element;
  }

  /**
   * Returns the entity view, if available.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being rendered.
   *
   * @return array|bool
   *   The renderable array of the view builder, or false if not applicable.
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
   *
   * @param \Drupal\responsive_image\ResponsiveImageStyleInterface $responsive
   *   The responsive image style entity.
   *
   * @return array
   *   The responsive image cache tags, or empty array.
   */
  public function getResponsiveImageCacheTags($responsive = NULL) {
    $cache_tags = [];
    $image_styles_to_load = [];
    if ($responsive) {
      $cache_tags = Cache::mergeTags($cache_tags, $responsive->getCacheTags());
      $image_styles_to_load = $responsive->getImageStyleIds();
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
   * @deprecated: Removed prior to release for BlazyLightbox::build().
   */
  public function getMediaSwitch(array &$element = []) {}

}
