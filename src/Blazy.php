<?php

namespace Drupal\blazy;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Template\Attribute;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Serialization\Json;
use Drupal\image\Entity\ImageStyle;
use Drupal\blazy\Dejavu\BlazyDefault;

/**
 * Implements BlazyInterface.
 */
class Blazy implements BlazyInterface {

  /**
   * Defines constant placeholder Data URI image.
   */
  const PLACEHOLDER = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

  /**
   * Prepares variables for blazy.html.twig templates.
   */
  public static function buildAttributes(&$variables) {
    $element = $variables['element'];
    foreach (['captions', 'item', 'item_attributes', 'settings', 'url', 'url_attributes'] as $key) {
      $variables[$key] = isset($element["#$key"]) ? $element["#$key"] : [];
    }

    // Load the supported formatter variables for the possesive blazy wrapper.
    $item             = $variables['item'];
    $settings         = &$variables['settings'];
    $attributes       = &$variables['attributes'];
    $image_attributes = &$variables['item_attributes'];

    // Provides sensible defaults to shut up notices when lacking of settings.
    foreach (['embed_url', 'icon', 'image_style', 'media_switch', 'player', 'scheme'] as $key) {
      $settings[$key] = isset($settings[$key]) ? $settings[$key] : '';
    }

    // Supports GridStack which can have multiple image styles per image.
    $settings['grid_style'] = empty($settings['grid_style']) ? $settings['image_style'] : $settings['grid_style'];
    $settings['type']       = empty($settings['type']) ? 'image' : $settings['type'];
    $settings['ratio']      = empty($settings['ratio']) ? '' : str_replace(':', '', $settings['ratio']);
    $settings['item_id']    = empty($settings['item_id']) ? 'blazy' : $settings['item_id'];

    self::buildUrl($settings, $item, $settings['grid_style']);

    // Do not proceed if no URI is provided.
    // URI is stored within settings, not theme_blazy() property, as it is
    // always called for different purposes prior to arriving at theme_blazy().
    if (empty($settings['uri'])) {
      return;
    }

    // Supports non-blazy formatter, that is, responsive image theme.
    $image  = &$variables['image'];
    $iframe = [];

    // Media embed URL is stored in the settings.
    $media = $settings['embed_url'] && in_array($settings['type'], ['video', 'audio']);

    // The regular non-responsive, non-lazyloaded image URI where image_url may
    // contain image_style which is not expected by responsive_image.
    $image['#uri'] = empty($settings['image_url']) ? $settings['uri'] : $settings['image_url'];

    // Thumbnails.
    // With CSS background, IMG may be empty, add thumbnail to the container.
    if (!empty($settings['thumbnail_style'])) {
      $attributes['data-thumb'] = ImageStyle::load($settings['thumbnail_style'])->buildUrl($settings['uri']);
    }

    // Check whether we have responsive image, or lazyloaded one.
    if (!empty($settings['responsive_image_style_id'])) {
      $image['#type'] = 'responsive_image';
      $image['#responsive_image_style_id'] = $settings['responsive_image_style_id'];
      $image['#uri'] = $settings['uri'];

      // Disable aspect ratio which is not yet supported due to complexity.
      $settings['ratio'] = FALSE;
    }
    else {
      // Supports non-lazyloaded image.
      $image['#theme'] = 'image';

      if (!isset($settings['_no_cache'])) {
        $file_tags = isset($settings['file_tags']) ? $settings['file_tags'] : [];
        $settings['cache_tags'] = empty($settings['cache_tags']) ? $file_tags : Cache::mergeTags($settings['cache_tags'], $file_tags);
        $image['#cache'] = ['tags' => $settings['cache_tags']];

        if (!empty($settings['cache_keys'])) {
          $image['#cache']['keys'] = $settings['cache_keys'];
        }
      }

      // Aspect ratio to fix layout reflow with lazyloaded images responsively.
      // This is outside 'lazy' to allow non-lazyloaded iframes use this too.
      if (!empty($settings['width'])) {
        if (!empty($settings['ratio']) && in_array($settings['ratio'], ['enforced', 'fluid'])) {
          $padding_bottom = isset($settings['padding_bottom']) ? $settings['padding_bottom'] : round((($settings['height'] / $settings['width']) * 100), 2);
          $attributes['style'] = 'padding-bottom: ' . $padding_bottom . '%';
          $settings['_breakpoint_ratio'] = $settings['ratio'];
        }

        // Only output dimensions for non-responsive images.
        $image_attributes['height'] = $settings['height'];
        $image_attributes['width']  = $settings['width'];
      }

      if (!empty($settings['lazy'])) {
        $image['#uri'] = static::PLACEHOLDER;

        // Attach data attributes to either IMG tag or DIV container.
        if (empty($settings['background']) || empty($settings['blazy'])) {
          self::buildBreakpointAttributes($image_attributes, $settings);
        }

        // Supports both Slick and Blazy CSS background lazyloading.
        if (!empty($settings['background'])) {
          self::buildBreakpointAttributes($attributes, $settings);
          $attributes['class'][] = 'media--background';

          // Blazy doesn't need IMG to lazyload CSS background. Slick does.
          if (!empty($settings['blazy'])) {
            $image = [];
          }
        }

        // Multi-breakpoint aspect ratio.
        if (!empty($settings['_breakpoint_dimensions'])) {
          $attributes['data-dimensions'] = Json::encode($settings['_breakpoint_dimensions']);
        }
      }
    }

    // Image is optional for Video, and Blazy CSS background images.
    if ($image) {
      $image_attributes['alt'] = isset($item->alt) ? $item->alt : NULL;

      // Do not output an empty 'title' attribute.
      if (isset($item->title) && (Unicode::strlen($item->title) != 0)) {
        $image_attributes['title'] = $item->title;
      }

      $image_attributes['class'][] = 'media__image media__element';
      $image['#attributes'] = $image_attributes;
    }

    // Prepares a media player, and allows a tiny video preview without iframe.
    if ($media && empty($settings['_noiframe'])) {
      // image : If iframe switch disabled, fallback to iframe, remove image.
      // player: If no colorbox/photobox, it is an image to iframe switcher.
      // data- : Gets consistent with colorbox to share JS manipulation.
      $image                = empty($settings['media_switch']) ? [] : $image;
      $settings['player']   = empty($settings['lightbox']) && $settings['media_switch'] != 'content';
      $iframe['data-media'] = Json::encode(['type' => $settings['type'], 'scheme' => $settings['scheme']]);
      $iframe['data-src']   = $settings['embed_url'];
      $iframe['src']        = empty($settings['iframe_lazy']) ? $settings['embed_url'] : 'about:blank';

      // Only lazyload if media switcher is empty, but iframe lazy enabled.
      if (!empty($settings['iframe_lazy']) && empty($settings['media_switch'])) {
        $iframe['class'][] = 'b-lazy';
      }
    }

    if (!empty($settings['caption'])) {
      $variables['caption_attributes'] = new Attribute();
      $variables['caption_attributes']->addClass($settings['item_id'] . '__caption');
    }

    // URL can be entity, or lightbox URL different from image URL.
    $variables['iframe_attributes'] = new Attribute($iframe);
    $variables['url_attributes']    = new Attribute($variables['url_attributes']);

    // Provides wrapper attributes if requested for complex need such as grid.
    $variables['wrapper_attributes'] = isset($element['#wrapper_attributes']) ? new Attribute($element['#wrapper_attributes']) : [];
  }

  /**
   * Provides re-usable breakpoint data-attributes.
   *
   * $settings['breakpoints'] must contain: xs, sm, md, lg breakpoints with
   * the expected keys: width, image_style.
   *
   * @see self::buildAttributes()
   * @see BlazyManager::buildDataBlazy()
   */
  public static function buildBreakpointAttributes(array &$attributes = [], array &$settings = []) {
    $lazy_attribute = empty($settings['lazy_attribute']) ? 'src' : $settings['lazy_attribute'];

    // Defines attributes, builtin, or supported lazyload such as Slick.
    // Required for multi-serving images as of Blazy v1.6.0.
    $attributes['class'][] = empty($settings['lazy_class']) ? 'b-lazy' : $settings['lazy_class'];
    $attributes['data-' . $lazy_attribute] = $settings['image_url'];

    // Only provide multi-serving image URLs if breakpoints are provided.
    if (empty($settings['breakpoints'])) {
      return;
    }

    $srcset = $json = [];
    foreach ($settings['breakpoints'] as $key => $breakpoint) {
      if (empty($breakpoint['image_style']) || empty($breakpoint['width'])) {
        continue;
      }

      if ($style = ImageStyle::load($breakpoint['image_style'])) {
        $url = $style->buildUrl($settings['uri']);

        // Supports multi-breakpoint aspect ratio with irregular sizes.
        // @todo: Compare to ::buildDataBlazy(), only proceed if different.
        if (!empty($settings['_breakpoint_ratio'])) {
          $dimensions = [
            'width'  => $settings['width'],
            'height' => $settings['height'],
          ];

          $style->transformDimensions($dimensions, $settings['uri']);
          if ($width = self::widthFromDescriptors($breakpoint['width'])) {
            $json[$width] = round((($dimensions['height'] / $dimensions['width']) * 100), 2);
          }
        }

        $settings['breakpoints'][$key]['url'] = $url;

        // @todo: Recheck library if multi-styled BG is still supported anyway.
        if (!empty($settings['background'])) {
          $attributes['data-src-' . $key] = $url;
        }
        elseif (!empty($breakpoint['width'])) {
          $width = trim($breakpoint['width']);
          $width = is_numeric($width) ? $width . 'w' : $width;
          $srcset[] = $url . ' ' . $width;
        }
      }
    }

    if ($srcset) {
      $settings['srcset'] = implode(', ', $srcset);

      $attributes['srcset'] = '';
      $attributes['data-srcset'] = $settings['srcset'];
      $attributes['sizes'] = '100w';

      if (!empty($settings['sizes'])) {
        $attributes['sizes'] = trim($settings['sizes']);
        unset($attributes['height'], $attributes['width']);
      }
    }

    if ($json) {
      $settings['_breakpoint_dimensions'] = $json;
    }
  }

  /**
   * Builds URLs, cache tags, and dimensions for individual image.
   */
  public static function buildUrl(array &$settings = [], $item = NULL, $modifier = NULL) {
    $settings['image_style'] = isset($settings['image_style']) ? $settings['image_style'] : '';
    $modifier = empty($modifier) ? $settings['image_style'] : $modifier;

    // Blazy already sets URI, yet set fallback for direct theme_blazy() call.
    if (empty($settings['uri']) && $item) {
      $settings['uri'] = ($entity = $item->entity) && empty($item->uri) ? $entity->getFileUri() : $item->uri;
    }

    if (empty($settings['uri'])) {
      return;
    }

    // Lazyloaded elements expect image URL, not URI.
    if (empty($settings['image_url'])) {
      $settings['image_url'] = file_create_url($settings['uri']);
    }

    // Dimensions are already set once at top-level containing element, see
    // BlazyManager::buildDataBlazy(). Yet provides fallback for those which
    // call theme_blazy() directly without BlazyManager service.
    if (empty($settings['width'])) {
      $settings['width']  = isset($item->width)  ? $item->width  : NULL;
      $settings['height'] = isset($item->height) ? $item->height : NULL;

      // No file API, no $item, with unmanaged VEF image without image_style.
      // @todo: Decide to abandon, as videos without image style is edge case.
      if (empty($modifier) || empty($settings['width'])) {
        list($settings['width'], $settings['height']) = getimagesize($settings['image_url']);
      }
    }

    // Image style modifier can be multi-style images such as GridStack.
    if (!empty($modifier) && ($style = ImageStyle::load($modifier))) {
      // Image URLs, as opposed to URIs, are expected by lazyloaded images.
      $settings['image_url']  = $style->buildUrl($settings['uri']);
      $settings['cache_tags'] = $style->getCacheTags();

      // Unless reset for multi-styles, or top-level element, set em once.
      // @todo: Recheck, this assumes a cropped image style, not scaled.
      if (empty($settings['_dimensions']) || isset($settings['_dimensions_reset'])) {
        $dimensions = [
          'width'  => $settings['width'],
          'height' => $settings['height'],
        ];
        $style->transformDimensions($dimensions, $settings['uri']);
        $settings['height']      = $dimensions['height'];
        $settings['width']       = $dimensions['width'];
        $settings['_dimensions'] = TRUE;
        unset($settings['_dimensions_reset']);
      }
    }
  }

  /**
   * Gets the numeric "width" part from a descriptor.
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
   * Implements hook_config_schema_info_alter().
   */
  public static function configSchemaInfoAlter(array &$definitions, $formatter = 'blazy_base', $settings = []) {
    if (isset($definitions[$formatter])) {
      $mappings = &$definitions[$formatter]['mapping'];
      $settings = $settings ?: BlazyDefault::extendedSettings() + BlazyDefault::gridSettings();
      foreach ($settings as $key => $value) {
        $mappings[$key]['type'] = $key == 'breakpoints' ? 'mapping' : (is_array($value) ? 'sequence' : gettype($value));

        if (!is_array($value)) {
          $mappings[$key]['label'] = Unicode::ucfirst(str_replace('_' , ' ' , $key));
        }
      }

      if (isset($mappings['breakpoints'])) {
        foreach (BlazyDefault::getConstantBreakpoints() as $breakpoint) {
          $mappings['breakpoints']['mapping'][$breakpoint]['type'] = 'mapping';
          foreach (['breakpoint', 'width', 'image_style'] as $item) {
            $mappings['breakpoints']['mapping'][$breakpoint]['mapping'][$item]['type']  = 'string';
            $mappings['breakpoints']['mapping'][$breakpoint]['mapping'][$item]['label'] = Unicode::ucfirst(str_replace('_' , ' ' , $item));
          }
        }
      }

      if (isset($mappings['overridables'])) {
        $mappings['overridables']['label'] = 'Overridable options';
        $mappings['overridables']['sequence'][0]['type'] = 'string';
        $mappings['overridables']['sequence'][0]['label'] = 'Overridable';
      }

      // @todo: Drop non-UI stuffs.
      foreach (['dimension', 'display', 'item_id'] as $key) {
        $mappings[$key]['type'] = 'string';
      }
    }
  }

  /**
   * Implements hook_views_pre_render().
   */
  public static function viewsPreRender($view) {
    // Load Blazy library once, not per field, if any Blazy Views field found.
    if ($blazy = self::blazyViewsField($view)) {
      $settings = $blazy->mergedViewsSettings();
      $load = $blazy->blazyManager()->attach($settings);

      // Enforce Blazy to work with hidden element such as with EB selection.
      $load['drupalSettings']['blazy']['loadInvisible'] = TRUE;
      $view->element['#attached'] = isset($view->element['#attached']) ? NestedArray::mergeDeep($view->element['#attached'], $load) : $load;
      $view->element['#attributes']['data-blazy'] = TRUE;
      if (!empty($settings['media_switch'])) {
        $switch = str_replace('_', '-', $settings['media_switch']);
        $view->element['#attributes']['data-' . $switch . '-gallery'] = TRUE;
      }
    }
  }

  /**
   * Returns one of the Blazy Views fields, if available.
   */
  public static function blazyViewsField($view) {
    if (!isset($view->field)) {
      return;
    }

    foreach (['file', 'media'] as $entity) {
      if (isset($view->field['blazy_' . $entity])) {
        return $view->field['blazy_' . $entity];
      }
    }
    return FALSE;
  }

  /**
   * Implements hook_field_formatter_info_alter().
   */
  public static function fieldFormatterInfoAlter(array &$info) {
    // Supports optional Media Entity via VEM within VEF if available.
    if (function_exists('video_embed_media_media_bundle_insert')) {
      $info['blazy_file'] = [
        'id'          => 'blazy_file',
        'label'       => new TranslatableMarkup('Blazy Image with Media'),
        'description' => new TranslatableMarkup('Display the images associated to VEM/ME as videos.'),
        'class'       => 'Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFileFormatter',
        'field_types' => ['entity_reference', 'image'],
        'quickedit'   => ['editor' => 'disabled'],
        'provider'    => 'blazy',
      ];
    }
  }

  /**
   * Return blazy global config.
   */
  public static function getConfig($setting_name = '', $settings = 'blazy.settings') {
    $config = \Drupal::service('config.factory')->get($settings);
    return empty($setting_name) ? $config->get() : $config->get($setting_name);
  }

  /**
   * Returns the HTML ID of a single instance.
   */
  public static function getHtmlId($string = 'blazy', $id = '') {
    $blazy_id = &drupal_static('blazy_id', 0);

    // Do not use dynamic Html::getUniqueId, otherwise broken AJAX.
    return empty($id) ? Html::getId($string . '-' . ++$blazy_id) : $id;
  }

}
