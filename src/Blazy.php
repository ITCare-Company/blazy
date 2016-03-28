<?php

/**
 * @file
 * Contains \Drupal\blazy\Blazy.
 */

namespace Drupal\blazy;

use Drupal\Core\Template\Attribute;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Serialization\Json;
use Drupal\blazy\Dejavu\BlazyDefault;

/**
 * Defines preprocess and alter methods specific to blazy.
 */
class Blazy extends BlazyManager {

  /**
   * Defines constant placeholder Data URI image.
   */
  const PLACEHOLDER = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

  /**
   * Prepares variables for blazy templates.
   */
  public static function buildAttributes(&$variables) {
    // Merge the supported formatter $variables: image and colorbox.
    $element = $variables['element'];
    foreach (['captions', 'delta', 'item', 'item_attributes', 'settings', 'url', 'url_attributes'] as $key) {
      $variables[$key] = isset($element["#$key"]) ? $element["#$key"] : [];
    }

    // Load the supported formatter variables for the possesive blazy wrapper.
    $variables['attributes'] = $variables['item_attributes'];
    $attributes = &$variables['attributes'];
    $settings   = &$variables['settings'];
    $item       = $variables['item'];

    // Modifies variables.
    $variables['noscript'] = '';
    $ratio_attributes = new Attribute();
    foreach (['icon', 'player', 'type', 'uri'] as $key) {
      $settings[$key] = isset($settings[$key]) ? $settings[$key] : '';
    }

    $settings['ratio']   = empty($settings['ratio']) ? '' : str_replace(':', '', $settings['ratio']);
    $settings['item_id'] = empty($settings['item_id']) ? 'blazy' : $settings['item_id'];

    if (empty($settings['uri'])) {
      $settings['uri'] = ($entity = $item->entity) && empty($item->uri) ? $entity->getFileUri() : $item->uri;
    }

    if (!empty($settings['caption'])) {
      $variables['caption_attributes'] = new Attribute();
      $variables['caption_attributes']->addClass($settings['item_id'] . '__caption');
    }

    // @todo adjust themes (IMG/IFRAME) based on type: image/video/audio.
    // Supports non-blazy formatter, that is, responsive image theme.
    $media = &$variables['image'];

    $media['#uri'] = $settings['uri'];
    $media['#alt'] = isset($item->alt) ? $item->alt : NULL;

    // Do not output an empty 'title' attribute.
    if (Unicode::strlen($item->title) != 0) {
      $media['#title'] = $item->title;
    }

    // Check whether we have responsive image, or plain one.
    if (!empty($settings['responsive_image_style_id'])) {
      $media['#type'] = 'responsive_image';
      $media['#responsive_image_style_id'] = $settings['responsive_image_style_id'];

      // Disable aspect ratio which is not yet supported due to complexity.
      $settings['ratio'] = FALSE;
    }
    elseif (!empty($settings['lazy']) && empty($settings['responsive_image_style_id'])) {
      $media['#theme'] = 'image';
      $media['#uri']   = static::PLACEHOLDER;

      // Defines attributes, builtin, or supported lazyload such as Slick.
      self::buildBreakpointAttributes($attributes, $settings);

      // Aspect ratio to fix layout reflow with lazyloaded images responsively.
      if (!empty($settings['ratio']) && !empty($settings['height']) && in_array($settings['ratio'], ['enforced', 'fluid'])) {
        $ratio_attributes->setAttribute('style', 'padding-bottom: ' . round((($settings['height'] / $settings['width']) * 100), 2) . '%');
      }
    }

    $attributes['class'][] = 'media__element';
    $media['#attributes'] = $attributes;

    if (empty($settings['icon']) && !empty($settings['lightbox'])) {
      $settings['icon'] = ['#markup' => '<span class="media__icon media__icon--litebox"></span>'];
    }

    // URL can be entity or lightbox URL different from the content image URL.
    $variables['url_attributes']   = new Attribute($variables['url_attributes']);
    $variables['ratio_attributes'] = $ratio_attributes;
  }

  /**
   * Provides re-usable breakpoint data-attributes.
   *
   * $settings['breakpoints'] must contain: xs, sm, md, lg breakpoints with
   * the expected keys: width, image_style,	url.
   *
   * @see BlazyManager::buildDataBlazy()
   * @see BlazyManager::getUrlBreakpoints()
   */
  public static function buildBreakpointAttributes(array &$attributes = [], $settings = []) {
    $lazy_attribute = empty($settings['lazy_attribute']) ? 'src' : $settings['lazy_attribute'];

    // Defines attributes, builtin, or supported lazyload such as Slick.
    $attributes['data-' . $lazy_attribute] = empty($settings['image_url']) ? '' : $settings['image_url'];
    if (!empty($settings['breakpoints'])) {
      foreach (array_filter($settings['breakpoints']) as $key => $breakpoint) {
        if (!empty($breakpoint['url'])) {
          $attributes['data-src-' . $key] = $breakpoint['url'];
        }
      }
    }
  }

  /**
   * Overrides any supported template to have blazy wrapper variables.
   */
  public static function buildWrapperAttributes(&$variables) {
    $element = $variables['element'];

    // Do not proceed if not an image.
    if ($variables['field_type'] != 'image') {
      return;
    }

    // Proceed only if using Blazy formatter.
    if (!isset($element['#blazy'])) {
      return;
    }

    $settings = $element['#blazy'];
    $settings['blazy_data']['ratio'] = !empty($settings['ratio']);
    if (!empty($settings['responsive_image_style'])) {
      $settings['ratio'] = FALSE;
    }

    // Defines [data-blazy] attribute as required by the Blazy loader.
    $settings['blazy_data']['container'] = '#' . $settings['id'];
    $variables['attributes']['id'] = $settings['id'];
    $variables['attributes']['class'][] = 'blazy';
    $variables['attributes']['data-blazy'] = Json::encode($settings['blazy_data']);

    if (!empty($settings['ratio'])) {
      $variables['attributes']['class'][] = 'blazy--ratio';
    }
  }

  /**
   * Overrides variables for responsive-image.html.twig templates.
   */
  public static function buildResponsiveImageAttributes(&$variables) {
    // Do not proceed if picture element.
    if (!$variables['output_image_tag']) {
      return;
    }
    if (!isset($config)) {
      $config = self::getConfig();
    }

    // Do not proceed if disabled globally, or not a Blazy formatter.
    if (!$config['responsive_image'] || !isset($variables['attributes']['data-srcset'])) {
      return;
    }

    // We are here either using Blazy, or core Responsive image formatters.
    $srcset = $variables['attributes']['srcset'];

    $variables['img_element']['#attributes']['class'][] = 'b-lazy b-responsive';
    $variables['img_element']['#attributes']['data-srcset'] = $srcset->value();
    $variables['img_element']['#attributes']['srcset'] = '';

    if ($config['one_pixel']) {
      $variables['img_element']['#uri'] = static::PLACEHOLDER;
    }

    $variables['img_element']['#attached']['drupalSettings']['blazy'] = $config['blazy'];
  }

  /**
   * Implements hook_config_schema_info_alter().
   */
  public static function configSchemaInfoAlter(array &$definitions, $formatter = 'blazy_base', $settings = []) {
    if (isset($definitions[$formatter])) {
      $mappings = &$definitions[$formatter]['mapping'];
      $settings = $settings ?: BlazyDefault::extendedSettings();
      foreach ($settings as $key => $value) {
        $mappings[$key]['type'] = $key == 'breakpoints' ? 'mapping' : (is_array($value) ? 'sequence' : gettype($value));

        if (!is_array($value)) {
          $mappings[$key]['label'] = Unicode::ucfirst(str_replace('_' , ' ' , $key));
        }
      }
      foreach (BlazyDefault::getConstantBreakpoints() as $key) {
        $mappings['breakpoints']['mapping'][$key]['type'] = 'mapping';
        foreach (['breakpoint', 'width', 'image_style'] as $item) {
          $value = $item == 'width' ? 'integer' : 'string';
          $mappings['breakpoints']['mapping'][$key]['mapping'][$item]['type']  = $value;
          $mappings['breakpoints']['mapping'][$key]['mapping'][$item]['label'] = Unicode::ucfirst(str_replace('_' , ' ' , $item));
        }
      }
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
    return parent::getHtmlId($string, $id);
  }

}
