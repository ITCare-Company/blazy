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
   * CHecks if the image style contains crop in the effect name.
   *
   * @var array
   */
  private $isCrop;

  /**
   * Cleans up empty breakpoints.
   *
   * @param array $settings
   *   The settings being modified.
   */
  public function cleanUpBreakpoints(array &$settings = []) {
    if (!empty($settings['breakpoints'])) {
      foreach ($settings['breakpoints'] as $key => &$breakpoint) {
        $breakpoint = array_filter($breakpoint);

        if (empty($breakpoint['width']) && empty($breakpoint['image_style'])) {
          unset($settings['breakpoints'][$key]);
        }
      }
    }

    // Identify that Blazy can be activated only by breakpoints.
    if (empty($settings['blazy'])) {
      $settings['blazy'] = !empty($settings['breakpoints']);
    }
  }

  /**
   * Checks if an image style contains crop effect.
   */
  public function isCrop($style) {
    if (!isset($this->isCrop[$style->getName()])) {
      $this->isCrop[$style->getName()] = FALSE;

      foreach ($style->getEffects() as $effect) {
        if (strpos($effect->getPluginId(), 'crop') !== FALSE) {
          $this->isCrop[$style->getName()] = TRUE;
          break;
        }
      }
    }

    return $this->isCrop[$style->getName()];
  }

  /**
   * Sets dimensions once to reduce method calls, if image style contains crop.
   *
   * The implementor should only call this if not using Responsive image style.
   *
   * @param array $settings
   *   The settings being modified.
   */
  public function setDimensionsOnce(array &$settings = []) {
    if (!isset($this->isDimensionSet[md5($settings['uri'])])) {
      $item                 = $settings['item'];
      $dimensions['width']  = $settings['original_width'] = isset($item->width) ? $item->width : NULL;
      $dimensions['height'] = $settings['original_height'] = isset($item->height) ? $item->height : NULL;

      // If image style contains crop, sets dimension once, and let all inherit.
      if (!empty($settings['image_style']) && ($style = $this->entityLoad($settings['image_style']))) {
        if ($this->isCrop($style)) {
          $style->transformDimensions($dimensions, $settings['uri']);

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

      $this->isDimensionSet[md5($settings['uri'])] = TRUE;
    }

    // Remove these since this method is meant for top-level container.
    unset($settings['uri'], $settings['item']);
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
   * @param array $item
   *   The item containing settings or item keys.
   */
  public function isBlazy(array &$settings, array $item = []) {
    // Retrieves Blazy formatter related settings from within Views style.
    $content = !empty($settings['item_id']) && isset($item[$settings['item_id']]) ? $item[$settings['item_id']] : $item;

    // 1. Blazy formatter within Views fields by supported modules.
    if (isset($item['settings'])) {
      // Prevents edge case with unexpected flattened Views results which is
      // normally triggered by checking "Use field template" option.
      $blazy = is_array($content) && isset($content['#build']['settings']) ? $content['#build']['settings'] : [];

      // Allows breakpoints overrides such as multi-styled images by GridStack.
      if (empty($settings['breakpoints']) && isset($blazy['breakpoints'])) {
        $settings['breakpoints'] = $blazy['breakpoints'];
      }

      $cherries = [
        'blazy',
        'box_style',
        'image_style',
        'lazy',
        'media_switch',
        'ratio',
        'uri',
      ];

      foreach ($cherries as $key) {
        $fallback = isset($settings[$key]) ? $settings[$key] : '';
        $settings[$key] = isset($blazy[$key]) && empty($fallback) ? $blazy[$key] : $fallback;
      }
    }

    // 2. Blazy Views fields by supported modules.
    if (is_array($content) && isset($content['#view']) && ($view = $content['#view'])) {
      if ($blazy_field = BlazyViews::viewsField($view)) {
        $settings = array_merge(array_filter($blazy_field->mergedViewsSettings()), array_filter($settings));
      }
    }

    // Provides data for the [data-blazy] attribute at the containing element.
    $this->cleanUpBreakpoints($settings);
    if (!empty($settings['breakpoints'])) {
      $image = isset($item['item']) ? $item['item'] : NULL;
      $this->buildDataBlazy($settings, $image);
    }
    unset($settings['uri'], $settings['item']);
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
   * @param object|mixed $item
   *   The \Drupal\image\Plugin\Field\FieldType\ImageItem item, or array when
   *   dealing with Video Embed Field.
   *
   * @todo: Refine this like everything else.
   */
  public function buildDataBlazy(array &$settings, $item = NULL) {
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
      if (empty($breakpoint['image_style']) || empty($breakpoint['width'])) {
        continue;
      }

      if ($width = Blazy::widthFromDescriptors($breakpoint['width'])) {
        // If contains crop, sets dimension once, and let all images inherit.
        if (!empty($settings['uri']) && !empty($settings['ratio'])) {
          $dimensions['width'] = $settings['original_width'];
          $dimensions['height'] = $settings['original_height'];

          if (!empty($breakpoint['image_style']) && ($style = $this->entityLoad($breakpoint['image_style']))) {
            if ($this->isCrop($style)) {
              $style->transformDimensions($dimensions, $settings['uri']);
              $padding = round((($dimensions['height'] / $dimensions['width']) * 100), 2);
              $json['dimensions'][$width] = $padding;

              // Only set padding-bottom for the last breakpoint to avoid FOUC.
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
      unset($json['dimensions'], $settings['padding_bottom']);
    }

    // Supported modules can add blazy_data as [data-blazy] to the container.
    // This also informs individual images to not work with dimensions any more
    // if the image style contains 'crop'.
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
    if (empty($settings['uri']) && is_object($item)) {
      $settings['uri'] = ($entity = $item->entity) && empty($item->uri) ? $entity->getFileUri() : $item->uri;
    }

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
  public function preRenderImage(array $element) {
    $build = $element['#build'];
    $item = $build['item'];
    unset($element['#build']);

    if (empty($item)) {
      return [];
    }

    $attributes = [];
    $settings = $build['settings'];
    $settings += BlazyDefault::itemSettings();
    $settings['_api'] = TRUE;

    // Extract field item attributes for the theme function, and unset them
    // from the $item so that the field template does not re-render them.
    $item_attributes = [];
    if (isset($item->_attributes)) {
      $item_attributes = $item->_attributes;
      unset($item->_attributes);
    }

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
        $padding_bottom = empty($settings['padding_bottom']) ? round((($settings['height'] / $settings['width']) * 100), 2) : $settings['padding_bottom'];
        $attributes['style'] = 'padding-bottom: ' . $padding_bottom . '%';

        // Provides hint to breakpoints to work with multi-breakpoint ratio.
        $settings['_breakpoint_ratio'] = $settings['ratio'];
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
        $class = $key == 'alt' ? 'description' : $key;
        $content[$key]['attributes'] = new Attribute();
        $content[$key]['attributes']->addClass($settings['item_id'] . '__caption--' . str_replace('_', '-', $class));
      }
    }

    return $content ? ['inline' => $content] : [];
  }

  /**
   * Returns the Responsive image cache tags.
   *
   * @param object $responsive
   *   The responsive image style entity.
   *
   * @return array
   *   The responsive image cache tags, or empty array.
   */
  public function getResponsiveImageCacheTags($responsive) {
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
