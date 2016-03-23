<?php

/**
 * @file
 * Contains \Drupal\blazy\BlazyManager.
 */

namespace Drupal\blazy;

use Drupal\Core\Cache\Cache;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;

/**
 * Implements a public facing blazy manager.
 *
 * A few modules re-use this: GridStack, Mason, Slick...
 */
class BlazyManager extends BlazyManagerBase {

  /**
   * Builds URLs for individual breakpoint, 0 is respected.
   */
  public function getUrlBreakpoints(array &$settings = []) {
    if (!empty($settings['breakpoints']) && !empty($settings['uri'])) {
      foreach ($settings['breakpoints'] as $key => $breakpoint) {
        $image_style = empty($breakpoint['image_style']) ? '' : $breakpoint['image_style'];
        if (!empty($image_style)) {
          $image_styles[$key] = $this->entityLoad($image_style, 'image_style');
          $settings['breakpoints'][$key]['url'] = $image_styles[$key]->buildUrl($settings['uri']);
        }
      }
    }
  }

  /**
   * Defines image dimensions once as it costs, unless reset for breakpoints.
   */
  public function getUrlDimensions(array &$settings = [], $item = NULL, $modifier = NULL) {
    if (!is_object($item)) {
      return;
    }

    if (!isset($settings['uri'])) {
      $settings['uri'] = ($entity = $item->entity) && empty($item->uri) ? $entity->getFileUri() : $item->uri;
    }

    $settings['cache_tags'] = [];
    if (empty($modifier) && isset($settings['image_style'])) {
      $modifier = $settings['image_style'];
    }

    if (!empty($modifier)) {
      $style = $this->entityLoad($modifier, 'image_style');

      // Image URLs are for lazyloaded images.
      $settings['image_url']  = $style->buildUrl($settings['uri']);
      $settings['cache_tags'] = $style->getCacheTags();

      // Unless reset for multi-styled images, set dimensions once.
      if (empty($settings['_dimensions']) || isset($settings['_dimensions_reset'])) {
        $dimensions = [
          'width'  => isset($item->width)  ? $item->width  : '',
          'height' => isset($item->height) ? $item->height : '',
        ];
        $style->transformDimensions($dimensions, $settings['uri']);
        $settings['height']      = $dimensions['height'];
        $settings['width']       = $dimensions['width'];
        $settings['_dimensions'] = TRUE;
      }
    }
    else {
      $settings['image_url'] = $item->entity->url();
      $settings['height']    = $item->height;
      $settings['width']     = $item->width;
    }

    if (!empty($settings['retina'])) {
      $retina = $this->entityLoad($settings['retina'], 'image_style');
      $settings['retina_url'] = $retina->buildUrl($settings['uri']);
      $settings['image_url']  = $settings['image_url'] . '|' . $settings['retina_url'];
    }
  }

  /**
   * Builds breakpoints suitable for [data-blazy] wrapper attributes.
   */
  public function buildDataBlazy($item = NULL, $settings = []) {
    if (!is_object($item) && empty($settings['breakpoints'])) {
      return [];
    }

    if (!isset($settings['uri'])) {
      $settings['uri'] = ($entity = $item->entity) && empty($item->uri) ? $entity->getFileUri() : $item->uri;
    }

    $json = $sources = [];
    $breakpoints = array_keys($settings['breakpoints']);
    foreach ($settings['breakpoints'] as $key => $breakpoint) {
      if (!empty($breakpoint['image_style'])) {
        $width = $breakpoint['width'];

        $image_styles[$width] = $this->entityLoad($breakpoint['image_style'], 'image_style');

        $dimensions[$width] = [
          'width'  => isset($item->width)  ? $item->width  : NULL,
          'height' => isset($item->height) ? $item->height : NULL,
        ];

        $image_styles[$width]->transformDimensions($dimensions[$width], $settings['uri']);
        $json['dimensions'][$width]['height'] = (int) $dimensions[$width]['height'];
        $json['dimensions'][$width]['width']  = (int) $dimensions[$width]['width'];

        $source = [];
        $source['width'] = (int) $width;
        $source['src'] = 'data-src-' . $key;
        $sources[] = $source;
      }
    }

    if ($sources) {
      $json['breakpoints'] = $sources;
    }
    return $json;
  }

  /**
   * Returns the image based on the Responsive image mapping, or blazy.
   */
  public function getImage($build = []) {
    $item     = $build['item'];
    $settings = &$build['settings'];

    $settings['namespace'] = empty($settings['namespace']) ? 'blazy' : $settings['namespace'];
    $theme_image = isset($settings['theme_hook_image']) ? $settings['theme_hook_image'] : 'blazy';

    $image = [
      '#theme'       => $theme_image,
      '#item'        => [],
      '#delta'       => $settings['delta'],
      '#image_style' => $settings['image_style'],
      '#pre_render'  => [[$this, 'preRenderImage']],
    ];

    // Gets individual image URLs, and dimensions set once.
    $this->getUrlDimensions($settings, $item, $image['#image_style']);

    // Gets multi-serving image URLs if breakpoints are provided.
    if (!empty($settings['breakpoints'])) {
      $this->getUrlBreakpoints($settings);
    }

    $file_tags = isset($settings['file_tags']) ? $settings['file_tags'] : [];
    $settings['cache_tags'] = Cache::mergeTags($settings['cache_tags'], $file_tags);

    $image['#build'] = $build;
    $image['#cache'] = ['tags' => $settings['cache_tags']];

    if (isset($settings['theme_hook_image_wrapper'])) {
      $image['#theme_wrappers'][] = $settings['theme_hook_image_wrapper'];
    }

    $this->getModuleHandler()->alter('blazy_image', $image, $settings);
    return $image;
  }

  /**
   * Builds the Slick image as a structured array ready for ::renderer().
   */
  public function preRenderImage($element) {
    $build = $element['#build'];
    $item  = $build['item'];
    unset($element['#build']);

    if (empty($item)) {
      return [];
    }

    $settings = &$build['settings'];

    // Extract field item attributes for the theme function, and unset them
    // from the $item so that the field template does not re-render them.
    $item_attributes = $item->_attributes;
    unset($item->_attributes);

    $element['#item'] = $item;

    // Responsive image integration.
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
    elseif (!empty($settings['width'])) {
      $item_attributes['height'] = $settings['height'];
      $item_attributes['width']  = $settings['width'];

      // Allows custom lazyload solution such as Slick builtin lazyloads.
      $settings['lazy_attribute'] = empty($settings['lazy_attribute']) ? 'src' : $settings['lazy_attribute'];
      if (!empty($settings['blazy']) || $settings['namespace'] == 'blazy') {
        $item_attributes['class'][] = 'b-lazy';
      }
    }

    if (!empty($settings['thumbnail_style'])) {
      $item_attributes['data-thumb'] = $this->entityLoad($settings['thumbnail_style'], 'image_style')->buildUrl($settings['uri']);
    }

    $element['#url'] = '';
    $element['#settings'] = $settings;
    $element['#captions'] = isset($build['captions']) ? $build['captions'] : [];
    $element['#item_attributes'] = $item_attributes;

    if (!empty($settings['media_switch']) && ($settings['media_switch'] == 'content' || strpos($settings['media_switch'], 'box') !== FALSE)) {
      $this->getMediaSwitch($element, $settings);
    }

    $this->getModuleHandler()->alter('blazy_image_pre_render', $element, $settings);
    return $element;
  }

  /**
   * Gets the media switch options: colorbox, photobox, content.
   */
  public function getMediaSwitch(array &$element = [], $settings = []) {
    $type   = isset($settings['type']) ? $settings['type'] : 'image';
    $uri    = $settings['uri'];
    $switch = $settings['media_switch'];

    // Provide relevant URL if it is a lightbox.
    if (strpos($switch, 'box') !== FALSE) {
      $json = ['type' => $type];
      $url_attributes = [];
      if (!empty($settings['url'])) {
        $url = $settings['url'];
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

      $classes = ['blazy-' . $switch, 'litebox'];
      if ($switch == 'colorbox' && $settings['count'] > 1) {
        $json['rel'] = $settings['id'];
      }
      elseif ($switch == 'photobox' && !empty($settings['url'])) {
        $url_attributes['rel'] = 'video';
      }

      // Provides lightbox media dimension if so configured.
      if ($type != 'image' && !empty($settings['dimension'])) {
        list($settings['width'], $settings['height']) = array_pad(array_map('trim', explode("x", $settings['dimension'], 2)), 2, NULL);
        $json['width']  = $settings['width'];
        $json['height'] = $settings['height'];
      }

      $url_attributes['class'] = $classes;
      $url_attributes['data-media'] = Json::encode($json);
      $url_attributes['data-' . $switch] = TRUE;

      $element['#url'] = $url;
      $element['#url_attributes'] = $url_attributes;
      $element['#settings']['lightbox'] = $switch;
    }
    elseif ($switch == 'content' && !empty($settings['absolute_path'])) {
      $element['#url'] = $settings['absolute_path'];
    }

    $this->getModuleHandler()->alter('blazy_media_switch', $element, $settings);
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
   * Collects defined skins as registered via hook_MODULE_NAME_skins_info().
   */
  public function buildSkins($namespace, $skin_class, $methods = []) {
    $skins = [];
    $cid = $namespace . ':skins';
    if ($cache = $this->getCache()->get($cid)) {
      $skins = $cache->data;
    }
    else {
      $classes = $this->getModuleHandler()->invokeAll($namespace . '_skins_info');
      $classes = array_merge([$skin_class], $classes);
      $items   = $skins = [];
      foreach ($classes as $class) {
        if (class_exists($class)) {
          $reflection = new \ReflectionClass($class);
          if ($reflection->implementsInterface($skin_class . 'Interface')) {
            $skin = new $class;
            if (empty($methods) && method_exists($skin, 'skins')) {
              $items = $skin->skins();
            }
            else {
              foreach ($methods as $method) {
                $items[$method] = method_exists($skin, $method) ? $skin->$method() : [];
              }
            }
          }
        }
        $skins = NestedArray::mergeDeep($skins, $items);
      }

      $count = isset($items['skins']) ? count($items['skins']) : count($items);
      $tags  = Cache::buildTags($cid, ['count:' . $count]);

      $this->getCache()->set($cid, $skins, Cache::PERMANENT, $tags);
    }
    return $skins;
  }

  /**
   * Returns array of needed assets suitable for #attached property.
   */
  public function attach($attach = []) {
    $load   = [];
    $attach += ['blazy_colorbox' => TRUE, 'blazy_photobox' => TRUE];
    $switch = empty($attach['media_switch']) ? '' : $attach['media_switch'];

    if ($switch && $switch != 'content') {
      $attach[$switch] = $switch;
    }

    // @todo redo this when colorbox has JS loader again, or just array.
    if (!empty($attach['colorbox'])) {
      $dummy = [];
      \Drupal::service('colorbox.attachment')->attach($dummy);
      $load = NestedArray::mergeDeep($load, $dummy['#attached']);
      $load['library'][] = 'colorbox/colorbox';
      if (!empty($attach['blazy_colorbox'])) {
        $load['library'][] = 'blazy/colorbox';
      }
    }

    if (!empty($attach['photobox']) && !empty($attach['blazy_photobox'])) {
      $load['library'][] = 'blazy/photobox';
    }

    // Core Blazy libraries.
    if (!empty($attach['lazy']) && ($attach['lazy'] == 'blazy' || $attach['lazy'] == 'responsive')) {
      $load['library'][] = 'blazy/load';
      $globals = $this->configLoad()['blazy'];
      $blazy_data = empty($attach['blazy_data']) ? [] : $attach['blazy_data'];

      // Allows other modules to provide custom settings.
      if (!empty($blazy_data['_reset'])) {
        $blazy_data = [];
      }
      $load['drupalSettings']['blazy'] = empty($blazy_data) ? $globals : array_merge($globals, $blazy_data);
    }

    $this->getModuleHandler()->alter('blazy_attach', $load, $attach);
    return $load;
  }

  /**
   * Returns the HTML ID common for Blazy, GridStack, Mason, Slick.
   */
  public static function getHtmlId($string = 'blazy', $id = '') {
    $blazy_id = &drupal_static('blazy_id', 0);

    // Do not use dynamic Html::getUniqueId, otherwise broken AJAX.
    return $id ?: Html::getId($string . '-' . ++$blazy_id);
  }

}
