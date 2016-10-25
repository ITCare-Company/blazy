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
 */
class BlazyManager extends BlazyManagerBase {

  /**
   * Used at top-level element: Cleans up empty breakpoints.
   */
  public function cleanUpBreakpoints(array &$settings = []) {
    if (!empty($settings['breakpoints'])) {
      foreach ($settings['breakpoints'] as $key => $breakpoint) {
        if (empty($breakpoint['width']) && empty($breakpoint['image_style'])) {
          unset($settings['breakpoints'][$key]);
        }
      }
    }

    // If breakpoints provided, enforce Blazy lazyloading without further ado.
    $settings['blazy'] = !empty($settings['breakpoints']);
  }

  /**
   * Checks for Blazy formatter such as from within a Views style plugin.
   *
   * Ensures the settings traverse up to the container where Blazy is clueless.
   * The supported plugins can add [data-blazy] attribute into its container
   * containing $settings['blazy_data'] converted into [data-blazy] JSON.
   *
   * @see \Drupal\gridstack\Plugin\views\style\GridStackViews::render().
   * @see \Drupal\slick_views\Plugin\views\style\SlickViews::render().
   * @see template_preprocess_slick().
   * @see template_preprocess_gridstack().
   *
   * @todo unified way between Views styles, Views fields and field formatters.
   */
  public function isBlazy(array &$settings = [], $item = []) {
    // Retrieves Blazy formatter related settings from within Views style.
    $item_id = $settings['item_id'];

    // 1. Blazy formatter within Views fields by supported modules.
    if (isset($item['settings'])) {
      $blazy = isset($item[$item_id]['#build']['settings']) ? $item[$item_id]['#build']['settings'] : [];

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
    if (isset($item[$item_id]['#view']) && ($view = $item[$item_id]['#view'])) {
      if ($blazy_field = Blazy::blazyViewsField($view)) {
        $settings = array_merge($blazy_field->mergedViewsSettings(), $settings);
      }
    }

    // Provide data for the [data-blazy] attribute at the containing element.
    // Supported modules can add blazy_data as [data-blazy] to the container.
    if (isset($item['item'])) {
      $settings['blazy_data'] = $this->buildDataBlazy($settings, $item['item']);
    }
  }

  /**
   * Builds breakpoints suitable for top-level [data-blazy] wrapper attributes.
   */
  public function buildDataBlazy(array &$settings = [], $item = NULL) {
    // Addresses the trouble with non-mobile-first approach.
    $settings['_dimensions_reset'] = TRUE;

    // Sets dimensions from the first item once, and let child elements inherit.
    Blazy::buildUrl($settings, $item);

    $json = $sources = [];
    if (!empty($settings['breakpoints'])) {
      $end = end($settings['breakpoints']);
      foreach ($settings['breakpoints'] as $key => $breakpoint) {
        if (empty($breakpoint['image_style'])) {
          continue;
        }

        $point = $breakpoint['width'];

        $image_styles[$point] = ImageStyle::load($breakpoint['image_style']);

        $dimensions[$point] = [
          'width'  => $settings['width'],
          'height' => $settings['height'],
        ];

        if (!empty($settings['uri'])) {
          $image_styles[$point]->transformDimensions($dimensions[$point], $settings['uri']);
        }

        $width = static::widthFromDescriptors($point);
        $padding = round((($dimensions[$point]['height'] / $dimensions[$point]['width']) * 100), 2);
        $json['dimensions'][$width] = $padding;

        // Helper for the BG option.
        if (empty($point)) {
          $point = $dimensions[$point]['width'];
        }

        if (!empty($settings['background'])) {
          $source          = [];
          $source['width'] = (int) $width;
          $source['src']   = 'data-src-' . $key;
          $sources[]       = $source;
        }

        // Only set CSS padding-bottom value for the last breakpoint.
        if (!empty($end['breakpoint']) && ($key == $end['breakpoint'] && $end['width'] == $point)) {
          $settings['padding_bottom'] = $padding;
        }
      }

      // Identify that Blazy can be activated only by breakpoints.
      $settings['blazy'] = TRUE;
    }

    if ($sources) {
      // As of Blazy v1.6.0 applied to BG only.
      $json['breakpoints'] = $sources;
    }

    $json['ratio'] = empty($settings['ratio']) ? FALSE : $settings['ratio'];

    // Clean up URIs since this is meant for the top-level containing element.
    unset($settings['uri'], $settings['image_url']);
    return $json;
  }

  /**
   * Used at top-level element: Gets the numeric "width" part from a descriptor.
   */
  public static function widthFromDescriptors($descriptor = '') {
    // Dynamic multi-serving aspect ratio with backward compatibility.
    if (is_numeric($descriptor)) {
      return $descriptor;
    }

    // Cleanup w descriptor to fetch numerical width for JS aspect ratio.
    $width = strpos($descriptor, "w") !== FALSE ? str_replace('w', '', $descriptor) : $descriptor;

    // If both w and x descriptors are provided.
    if (strpos($descriptor, " ") !== FALSE) {
      // If the position is expected: 640w 2x.
      list($width, $px) = array_pad(array_map('trim', explode(" ", $width, 2)), 2, NULL);

      // If the position is reversed: 2x 640w.
      if (is_numeric($px) && strpos($width, "x") !== FALSE) {
        $width = $px;
      }
    }

    return $width;
  }

  /**
   * Returns the image based on the Responsive image mapping, or blazy.
   */
  public function getImage($build = []) {
    /** @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
    $item      = $build['item'];
    $settings  = &$build['settings'];
    $namespace = $settings['namespace'] = empty($settings['namespace']) ? 'blazy' : $settings['namespace'];
    $theme     = isset($settings['theme_hook_image']) ? $settings['theme_hook_image'] : 'blazy';

    if (empty($item)) {
      return [];
    }

    $settings['image_style'] = isset($settings['image_style']) ? $settings['image_style'] : '';
    if (!isset($settings['uri'])) {
      $settings['uri'] = ($entity = $item->entity) && empty($item->uri) ? $entity->getFileUri() : $item->uri;
    }
    if ($theme == 'blazy') {
      $settings['blazy'] = TRUE;
    }

    $image = [
      '#theme'       => $theme,
      '#item'        => [],
      '#delta'       => isset($settings['delta']) ? $settings['delta'] : 0,
      '#image_style' => $settings['image_style'],
      '#build'       => $build,
      '#pre_render'  => [[$this, 'preRenderImage']],
    ];

    $this->getModuleHandler()->alter($namespace . '_image', $image, $settings);

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

    if (!empty($settings['media_switch']) && ($settings['media_switch'] == 'content' || strpos($settings['media_switch'], 'box') !== FALSE)) {
      $this->getMediaSwitch($element, $settings);
    }

    return $element;
  }

  /**
   * Gets media switch options: colorbox, photobox, content -- not iframe, etc.
   */
  public function getMediaSwitch(array &$element = [], $settings = []) {
    $item   = $element['#item'];
    $type   = isset($settings['type']) ? $settings['type'] : 'image';
    $uri    = $settings['uri'];
    $switch = $settings['media_switch'];

    // Provide relevant URL if it is a lightbox.
    if (strpos($switch, 'box') !== FALSE) {
      $json = ['type' => $type, 'width' => 640, 'height' => 360];
      $url_attributes = [];

      // If it is a video/audio, otherwise image to image.
      if (!empty($settings['embed_url'])) {
        $url = $settings['embed_url'];
        $json['scheme'] = $settings['scheme'];
        // Force autoplay for media URL on lightboxes, saving another click.
        if ($json['scheme'] == 'soundcloud') {
          if (strpos($url, 'auto_play') === FALSE || strpos($url, 'auto_play=false') !== FALSE) {
            $url = strpos($url, '?') === FALSE ? $url . '?auto_play=true' : $url . '&amp;auto_play=true';
          }
        }
        elseif (strpos($url, 'autoplay') === FALSE || strpos($url, 'autoplay=0') !== FALSE) {
          $url = strpos($url, '?') === FALSE ? $url . '?autoplay=1' : $url . '&amp;autoplay=1';
        }
      }
      else {
        $url = empty($settings['box_style']) ? file_create_url($uri) : $this->entityLoad($settings['box_style'], 'image_style')->buildUrl($uri);
      }

      if ($switch == 'colorbox' && $settings['count'] > 1) {
        $json['rel'] = $settings['id'];
      }
      elseif ($switch == 'photobox' && !empty($settings['embed_url'])) {
        $url_attributes['rel'] = 'video';
      }

      // Provides custom lightbox media dimension if so configured.
      if ($type != 'image' && !empty($settings['dimension'])) {
        list($json['width'], $json['height']) = array_pad(array_map('trim', explode("x", $settings['dimension'], 2)), 2, NULL);
      }

      $url_attributes['class'] = ['blazy__' . $switch, 'litebox'];
      $url_attributes['data-media'] = Json::encode($json);
      $url_attributes['data-' . $switch] = TRUE;

      $element['#url'] = $url;
      $element['#url_attributes'] = $url_attributes;
      $element['#settings']['lightbox'] = $switch;

      if (!empty($settings['box_caption'])) {
        $element['#captions']['lightbox'] = self::buildCaptions($item, $settings);
      }
    }
    elseif ($switch == 'content' && !empty($settings['absolute_path'])) {
      $element['#url'] = $settings['absolute_path'];
    }
  }

  /**
   * Build lightbox captions.
   */
  public static function buildCaptions($item, $settings = []) {
    $title   = empty($item->title) ? '' : $item->title;
    $alt     = empty($item->alt)   ? '' : $item->alt;
    $delta   = $settings['delta'];
    $caption = '';

    switch ($settings['box_caption']) {
      case 'auto':
        $caption = $alt ?: $title;
        break;

      case 'alt':
        $caption = $alt;
        break;

      case 'title':
        $caption = $title;
        break;

      case 'alt_title':
      case 'title_alt':
        $alt     = $alt ? '<p>' . $alt . '</p>' : '';
        $title   = $title ? '<h2>' . $title . '</h2>' : '';
        $caption = $settings['box_caption'] == 'alt_title' ? $alt . $title : $title . $alt;
        break;

      case 'entity_title':
        $caption = ($entity = $item->getEntity()) ? $entity->label() : '';
        break;

      case 'custom':
        $token = \Drupal::token();
        $caption = '';
        if ($entity = $item->getEntity()) {
          $entity_type = $entity->getEntityTypeId();

          $options = ['clear' => TRUE];
          $caption = $token->replace($settings['box_caption_custom'], [$entity_type => $entity, 'file' => $item], $options);

          // Checks for multi-value text fields, and maps its delta to image.
          if (strpos($caption, ", <p>") !== FALSE) {
            $caption = str_replace(", <p>", '| <p>', $caption);
            $captions = explode("|", $caption);
            $caption = isset($captions[$delta]) ? $captions[$delta] : '';
          }
        }
        break;

      default:
        $caption = '';
    }

    return empty($caption) ? [] : ['#markup' => $caption];
  }

  /**
   * Returns the entity view, if available.
   */
  public function getEntityView($entity, $settings = [], $fallback = []) {
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

}
