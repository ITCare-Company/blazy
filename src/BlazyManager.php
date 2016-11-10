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
    $image = isset($item['item']) ? $item['item'] : NULL;
    $this->buildDataBlazy($settings, $image);
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
        if (empty($breakpoint['image_style']) || empty($breakpoint['width'])) {
          continue;
        }

        $point = trim($breakpoint['width']);

        if ($style = ImageStyle::load($breakpoint['image_style'])) {
          $dimensions = [
            'width'  => $settings['width'],
            'height' => $settings['height'],
          ];

          if (!empty($settings['uri'])) {
            $style->transformDimensions($dimensions, $settings['uri']);
          }

          $width = Blazy::widthFromDescriptors($point);
          $padding = round((($dimensions['height'] / $dimensions['width']) * 100), 2);
          $json['dimensions'][$width] = $padding;

          // Helper for the BG option.
          if (!empty($settings['background'])) {
            $source['width'] = (int) $width;
            $source['src']   = 'data-src-' . $key;
            $sources[]       = $source;
          }
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
    // unset($settings['uri'], $settings['image_url']);
    $settings['blazy_data'] = $json;
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

    $settings['delta']       = isset($settings['delta']) ? $settings['delta'] : 0;
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
      '#delta'       => $settings['delta'],
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

    if (!empty($settings['media_switch'])) {
      if ($settings['media_switch'] == 'content' && !empty($settings['absolute_path'])) {
        $element['#url'] = $settings['absolute_path'];
      }
      elseif (strpos($settings['media_switch'], 'box') !== FALSE) {
        $this->getMediaSwitch($element);
      }
    }

    if (!empty($settings['_grid'])) {
      $element['#wrapper_attributes']['class'][] = 'grid__content';
    }

    return $element;
  }

  /**
   * Gets media switch options: colorbox, photobox, not content nor iframe, etc.
   */
  public function getMediaSwitch(array &$element = []) {
    $item     = $element['#item'];
    $settings = $element['#settings'];
    $type     = isset($settings['type']) ? $settings['type'] : 'image';
    $uri      = $settings['uri'];
    $switch   = $settings['media_switch'];
    $multiple = !empty($settings['count']) && $settings['count'] > 1;

    // Provide relevant URL if it is a lightbox.
    $json = ['type' => $type];
    $url_attributes = [];

    // If it is a video/audio, otherwise image to image.
    if (!empty($settings['embed_url'])) {
      $url = $settings['embed_url'];

      $json['scheme'] = $settings['scheme'];
      $json['width']  = 640;
      $json['height'] = 360;

      // Force autoplay for media URL on lightboxes, saving another click.
      if ($json['scheme'] == 'soundcloud') {
        if (strpos($url, 'auto_play') === FALSE || strpos($url, 'auto_play=false') !== FALSE) {
          $url = strpos($url, '?') === FALSE ? $url . '?auto_play=true' : $url . '&auto_play=true';
        }
      }
      elseif (strpos($url, 'autoplay') === FALSE || strpos($url, 'autoplay=0') !== FALSE) {
        $url = strpos($url, '?') === FALSE ? $url . '?autoplay=1' : $url . '&autoplay=1';
      }

      // Provides custom lightbox media dimension if so configured.
      if (!empty($settings['dimension'])) {
        list($json['width'], $json['height']) = array_pad(array_map('trim', explode("x", $settings['dimension'], 2)), 2, NULL);
      }

      if ($switch == 'photobox') {
        $url_attributes['rel'] = 'video';
      }
    }
    else {
      $url = empty($settings['box_style']) ? file_create_url($uri) : $this->entityLoad($settings['box_style'], 'image_style')->buildUrl($uri);
    }

    if ($switch == 'colorbox' && $multiple) {
      $json['rel'] = empty($settings['id']) ? 'blazy_colorbox' : $settings['id'];
    }

    $url_attributes['class'] = ['blazy__' . $switch, 'litebox'];
    $url_attributes['data-media'] = Json::encode($json);
    $url_attributes['data-' . $switch . '-trigger'] = TRUE;

    $element['#url'] = $url;
    $element['#url_attributes'] = $url_attributes;
    $element['#settings']['lightbox'] = $switch;

    if (!empty($settings['box_caption'])) {
      $element['#captions']['lightbox'] = self::buildCaptions($item, $settings);
    }
  }

  /**
   * Build lightbox captions.
   */
  public static function buildCaptions($item, $settings = []) {
    $title   = empty($item->title) ? '' : $item->title;
    $alt     = empty($item->alt)   ? '' : $item->alt;
    $delta   = empty($settings['delta']) ? 0 : $settings['delta'];
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
        $caption = '';
        if (!empty($settings['box_caption_custom']) && ($entity = $item->getEntity())) {
          $options = ['clear' => TRUE];
          $caption = \Drupal::token()->replace($settings['box_caption_custom'], [$entity->getEntityTypeId() => $entity, 'file' => $item], $options);

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
   * Returns items as a grid display.
   */
  public function buildGrid($items = [], $settings = []) {
    $grids = [];
    foreach ($items as $delta => $item) {
      // @todo support non-Blazy which normally uses item_id.
      $item_settings = isset($item['#build']['settings']) ? $item['#build']['settings'] : $settings;
      $item_settings['delta'] = $delta;

      // Supports complex fields such as Views, when theme_blazy() is absent.
      // @todo: Decide whether to use this consistently for theme_blazy() too.
      if (!isset($item['#build'])) {
        $item['#theme_wrappers'][] = 'container';
        $item['#attributes']['class'][] = 'grid__content';
      }

      $grid = [];
      $grid['content'] = $item;
      $this->buildGridItemAttributes($grid, $item_settings);

      $grids[] = $grid;
    }

    $count = empty($settings['count']) ? count($grids) : $settings['count'];
    $blazy = empty($settings['blazy_data']) ? [] : $settings['blazy_data'];
    $element = [
      '#theme' => 'item_list',
      '#items' => $grids,
      '#attributes' => [
       'class' => [
         'blazy',
         'blazy--grid',
         'block-' . $settings['style'],
         'block-count-' . $count,
        ],
        'data-blazy' => Json::encode($blazy),
      ],
      '#wrapper_attributes' => [
        'class' => ['item-list--blazy', 'item-list--blazy-grid'],
      ],
    ];

    $settings['grid_large'] = $settings['grid'];
    foreach (['small', 'medium', 'large'] as $grid) {
      if (!empty($settings['grid_' . $grid])) {
        $element['#attributes']['class'][] = $grid . '-block-' . $settings['style'] . '-' . $settings['grid_' . $grid];
      }
    }

    return $element;
  }

  /**
   * Returns a grid item.
   */
  public function buildGridItemAttributes(array &$grid = [], $settings = []) {
    $grid['#wrapper_attributes']['class'][] = 'grid';

    if (!empty($settings['type'])) {
      $grid['#wrapper_attributes']['class'][] = 'grid--' . $settings['type'];
    }

    if (!empty($settings['media_switch'])) {
      $grid['#wrapper_attributes']['class'][] = 'grid--' . $settings['media_switch'];
      if (strpos($settings['media_switch'], 'box') !== FALSE) {
        $grid['#wrapper_attributes']['class'][] = 'grid--litebox';
      }
    }

    $grid['#wrapper_attributes']['class'][] = 'grid--' . $settings['delta'];
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

}
