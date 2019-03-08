<?php

namespace Drupal\blazy;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Cache\Cache;

/**
 * Implements a public facing blazy manager.
 *
 * A few modules re-use this: GridStack, Mason, Slick...
 */
class BlazyManager extends BlazyManagerBase {

  /**
   * Checks if image dimensions are set.
   *
   * @var array
   */
  private $isDimensionSet;

  /**
   * Sets dimensions once to reduce method calls, if image style contains crop.
   *
   * The implementor should only call this if not using Responsive image style.
   *
   * @param array $settings
   *   The settings being modified.
   *
   * @todo replace uri with first_uri to be usable for colorbox-like gallery.
   */
  public function setDimensionsOnce(array &$settings = []) {
    if (!isset($this->isDimensionSet[md5($settings['first_uri'])])) {
      $item                 = $settings['first_item'];
      $dimensions['width']  = $settings['original_width'] = isset($item->width) ? $item->width : NULL;
      $dimensions['height'] = $settings['original_height'] = isset($item->height) ? $item->height : NULL;

      // If image style contains crop, sets dimension once, and let all inherit.
      if (!empty($settings['image_style']) && ($style = $this->entityLoad($settings['image_style']))) {
        if ($this->isCrop($style)) {
          $style->transformDimensions($dimensions, $settings['first_uri']);

          $settings['height'] = $dimensions['height'];
          $settings['width']  = $dimensions['width'];

          // Informs individual images that dimensions are already set once.
          $settings['_dimensions'] = TRUE;
        }
      }

      // Also sets breakpoint dimensions once, if cropped.
      if (!empty($settings['breakpoints'])) {
        $this->buildDataBlazy($settings, $item);
      }

      $this->isDimensionSet[md5($settings['first_uri'])] = TRUE;
    }
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
  public function getBlazy(array $build = []) {
    if (empty($build['item'])) {
      return [];
    }

    /** @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
    $item                    = $build['item'];
    $settings                = &$build['settings'];
    $settings['delta']       = isset($settings['delta']) ? $settings['delta'] : 0;
    $settings['image_style'] = isset($settings['image_style']) ? $settings['image_style'] : '';

    // The image URI may not always be given.
    // @todo remove if no need for sure.
    // @todo if (empty($settings['uri']) && is_object($item)) {
    // @todo $settings['uri'] = ($entity = $item->entity) && empty($item->uri) ? $entity->getFileUri() : $item->uri;
    // @todo }
    // Respects content not handled by theme_blazy(), but passed through.
    if (empty($build['content'])) {
      $image = [
        '#theme'       => 'blazy',
        '#delta'       => $settings['delta'],
        '#item'        => isset($settings['entity_type_id']) && $settings['entity_type_id'] == 'user' ? $item : [],
        '#image_style' => $settings['image_style'],
        '#build'       => $build,
        '#pre_render'  => [[$this, 'preRenderImage']],
      ];
    }
    else {
      $image = $build['content'];
    }

    $this->moduleHandler->alter('blazy', $image, $settings);
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
  public function preRenderImage(array $element) {
    $build = $element['#build'];
    $item = $build['item'];
    unset($element['#build']);

    if (empty($item)) {
      return [];
    }

    $settings = $build['settings'];
    $settings += BlazyDefault::itemSettings();
    $settings['_api'] = TRUE;

    // Extract field item attributes for the theme function, and unset them
    // from the $item so that the field template does not re-render them.
    $attributes = isset($build['attributes']) ? $build['attributes'] : [];
    $item_attributes = isset($build['item_attributes']) ? $build['item_attributes'] : [];
    $url_attributes = isset($build['url_attributes']) ? $build['url_attributes'] : [];
    if (isset($item->_attributes)) {
      $item_attributes += $item->_attributes;
    }
    unset($item->_attributes, $build['attributes'], $build['item_attributes'], $build['url_attributes']);

    // Gets the file extension, and ensures the image has valid extension.
    $pathinfo = pathinfo($settings['uri']);
    $settings['extension'] = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';
    $settings['ratio'] = empty($settings['ratio']) ? '' : str_replace(':', '', $settings['ratio']);

    // Prepare image URL and its dimensions.
    Blazy::buildUrlAndDimensions($settings, $item);

    // Responsive image integration.
    $settings['responsive_image_style_id'] = '';
    if (!empty($settings['resimage']) && !empty($settings['responsive_image_style'])) {
      $responsive_image_style = $this->entityLoad($settings['responsive_image_style'], 'responsive_image_style');
      $settings['lazy'] = '';
      if (!empty($responsive_image_style)) {
        $settings['responsive_image_style_id'] = $responsive_image_style->id();
        if ($this->configLoad('responsive_image')) {
          $item_attributes['data-srcset'] = TRUE;
          $settings['lazy'] = 'responsive';
        }
        $element['#cache']['tags'] = $this->getResponsiveImageCacheTags($responsive_image_style);
      }
    }

    // Regular image with custom responsive breakpoints.
    if (empty($settings['responsive_image_style_id'])) {
      if ($settings['width'] && !empty($settings['ratio']) && in_array($settings['ratio'], ['enforced', 'fluid'])) {
        $padding = empty($settings['padding_bottom']) ? round((($settings['height'] / $settings['width']) * 100), 2) : $settings['padding_bottom'];
        $attributes['style'] = 'padding-bottom: ' . $padding . '%';

        // Provides hint to breakpoints to work with multi-breakpoint ratio.
        $settings['_breakpoint_ratio'] = $settings['ratio'];

        // Views rewrite results or Twig inline_template may strip out `style`
        // attributes, provide hint to JS.
        $attributes['data-ratio'] = $padding;
      }

      if (!empty($settings['lazy'])) {
        // Attach data attributes to either IMG tag, or DIV container.
        if (!empty($settings['background'])) {
          Blazy::buildBreakpointAttributes($attributes, $settings);
          $attributes['class'][] = 'media--background';
        }
        else {
          Blazy::buildBreakpointAttributes($item_attributes, $settings);
        }

        // Multi-breakpoint aspect ratio only applies if lazyloaded.
        if (!empty($settings['blazy_data']['dimensions'])) {
          $attributes['data-dimensions'] = Json::encode($settings['blazy_data']['dimensions']);
        }
      }

      if (empty($settings['_no_cache'])) {
        $file_tags = isset($settings['file_tags']) ? $settings['file_tags'] : [];
        $settings['cache_tags'] = empty($settings['cache_tags']) ? $file_tags : Cache::mergeTags($settings['cache_tags'], $file_tags);

        $element['#cache']['max-age'] = -1;
        foreach (['contexts', 'keys', 'tags'] as $key) {
          if (!empty($settings['cache_' . $key])) {
            $element['#cache'][$key] = $settings['cache_' . $key];
          }
        }
      }
    }

    $captions = empty($build['captions']) ? [] : $this->buildCaption($build['captions'], $settings);
    if ($captions) {
      $element['#caption_attributes']['class'][] = $settings['item_id'] . '__caption';
    }

    $element['#attributes']      = $attributes;
    $element['#captions']        = $captions;
    $element['#item']            = $item;
    $element['#item_attributes'] = $item_attributes;
    $element['#url_attributes']  = $url_attributes;
    $element['#settings']        = $settings;

    foreach (['media', 'wrapper'] as $key) {
      if (!empty($settings[$key . '_attributes'])) {
        $element["#$key" . '_attributes'] = $settings[$key . '_attributes'];
      }
    }

    if (!empty($settings['media_switch'])) {
      if ($settings['media_switch'] == 'content' && !empty($settings['content_url'])) {
        $element['#url'] = $settings['content_url'];
      }
      elseif (!empty($settings['lightbox'])) {
        BlazyLightbox::build($element);
      }
    }

    return $element;
  }

  /**
   * Build captions for both old image, or media entity.
   */
  public function buildCaption(array $captions, array $settings) {
    $content = [];
    foreach ($captions as $key => $caption_content) {
      if ($caption_content) {
        $content[$key]['content'] = $caption_content;
        $content[$key]['tag'] = strpos($key, 'title') !== FALSE ? 'h2' : 'div';
        $class = $key == 'alt' ? 'description' : str_replace('field_', '', $key);
        $content[$key]['attributes'] = new Attribute();
        $content[$key]['attributes']->addClass($settings['item_id'] . '__caption--' . str_replace('_', '-', $class));
      }
    }

    return $content ? ['inline' => $content] : [];
  }

  /**
   * Returns the contents using theme_field(), or theme_item_list().
   *
   * @param array $build
   *   The array containing: settings, children elements, or optional items.
   *
   * @return array
   *   The alterable and renderable array of contents.
   */
  public function build(array $build = []) {
    $settings = $build['settings'];
    $settings['_grid'] = isset($settings['_grid']) ? $settings['_grid'] : (!empty($settings['style']) && !empty($settings['grid']));

    // If not a grid, pass the items as regular index children to theme_field().
    // @todo #pre_render doesn't work if called from Views results.
    if (empty($settings['_grid'])) {
      $settings = $this->prepareBuild($build);
      $build['#blazy'] = $settings;
      $build['#attached'] = $this->attach($settings);
    }
    else {
      $build = [
        '#build'      => $build,
        '#settings'   => $settings,
        '#pre_render' => [[$this, 'preRenderBuild']],
      ];
    }

    $this->moduleHandler->alter('blazy_build', $build, $settings);
    return $build;
  }

  /**
   * Builds the Blazy outputs as a structured array ready for ::renderer().
   */
  public function preRenderBuild(array $element) {
    $build = $element['#build'];
    unset($element['#build']);

    // @todo $settings nullified when having Views field within grid.
    $settings = $this->prepareBuild($build);
    $element = BlazyGrid::build($build, $settings);
    $element['#attached'] = $this->attach($settings);
    return $element;
  }

  /**
   * Prepares Blazy outputs, extract items, and return updated $settings.
   */
  public function prepareBuild(array &$build) {
    // If children are stored within items, reset.
    $settings = isset($build['settings']) ? $build['settings'] : [];
    $build = isset($build['items']) ? $build['items'] : $build;

    // Supports Blazy multi-breakpoint images if provided, updates $settings.
    // Blazy formatters have #build and Views fields none.
    if (isset($build[0])) {
      $item = !empty($build[0]['#build']) ? $build[0]['#build'] : $build[0];
      $this->isBlazy($settings, $item);
    }

    unset($build['items'], $build['settings']);
    return $settings;
  }

  /**
   * Returns the entity view, if available.
   *
   * @deprecated to remove for BlazyEntity::getEntityView().
   */
  public function getEntityView($entity, array $settings = [], $fallback = '') {
    return FALSE;
  }

  /**
   * Returns the enforced content, or image using theme_blazy().
   *
   * @deprecated to remove post 2.x for self::getBlazy() for clarity.
   * FYI, most Blazy codes were originally Slick's, PHP, CSS and JS.
   * It was poorly named self::getImage() while Blazy may also contain Media
   * video with iframe element. Probably getMedia() is cool, but let's stick to
   * self::getBlazy() as Blazy also works without Image nor Media video, such as
   * with just a DIV element for CSS background.
   */
  public function getImage(array $build = []) {
    return $this->getBlazy($build);
  }

}
