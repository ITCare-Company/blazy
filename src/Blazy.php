<?php

namespace Drupal\blazy;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Site\Settings;
use Drupal\Core\Template\Attribute;
use Drupal\image\Entity\ImageStyle;

/**
 * Implements BlazyInterface.
 */
class Blazy implements BlazyInterface {

  // @todo remove at blazy:8.x-3.0 or sooner.
  use BlazyDeprecatedTrait;

  /**
   * The blazy HTML ID.
   *
   * @var int
   */
  private static $blazyId;

  /**
   * {@inheritdoc}
   */
  public static function generatePlaceholder($width, $height): string {
    return 'data:image/svg+xml;charset=utf-8,%3Csvg xmlns%3D\'http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg\' viewBox%3D\'0 0 ' . $width . ' ' . $height . '\'%2F%3E';
  }

  /**
   * Prepares variables for blazy.html.twig templates.
   */
  public static function preprocessBlazy(array &$variables) {
    $element = $variables['element'];
    foreach (BlazyDefault::themeProperties() as $key) {
      $variables[$key] = isset($element["#$key"]) ? $element["#$key"] : [];
    }

    // Provides optional attributes, see BlazyFilter.
    foreach (BlazyDefault::themeAttributes() as $key) {
      $key = $key . '_attributes';
      $variables[$key] = empty($element["#$key"]) ? [] : new Attribute($element["#$key"]);
    }

    // Provides sensible default html settings to shutup notices when lacking.
    $item      = $variables['item'];
    $settings  = &$variables['settings'];
    $settings += BlazyDefault::itemSettings();

    // Still provides a failsafe for direct theme call with a valid Image item.
    if (empty($settings['uri']) && $item) {
      $settings['uri'] = ($entity = $item->entity) && empty($item->uri) ? $entity->getFileUri() : $item->uri;
    }

    // Do not proceed if no URI is provided.
    if (empty($settings['uri'])) {
      return;
    }

    // URL and dimensions are built out at BlazyManager::preRenderBlazy().
    // Still provides a failsafe for direct call to theme_blazy().
    if (empty($settings['_api'])) {
      self::urlAndDimensions($settings, $item);
    }

    // Build regular image if not using responsive image.
    // (Responsive) image is optional for Video, or image as CSS background.
    // The Responsive image itself is built out at BlazyManager::build().
    if (empty($settings['responsive_image_style_id']) && empty($settings['background'])) {
      self::buildImage($variables);
    }

    // Prepare a media player, and allow a tiny video preview without iframe.
    if ($settings['use_media'] && empty($settings['_noiframe'])) {
      self::buildIframe($variables);
    }

    // (Responsive) image is optional for Video, or image as CSS background.
    if ($variables['image']) {
      self::imageAttributes($variables);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function urlAndDimensions(array &$settings, $item = NULL) {
    // BlazyFilter, or image style with crop, may already set these.
    Blazy::imageDimensions($settings, $item);

    // Overrides lazy with blazy for explicit call to reduce another param.
    if (!empty($settings['blazy'])) {
      $settings['lazy'] = 'blazy';
    }

    // Provides image_url, not URI, expected by lazyload.
    $uri = $settings['uri'];
    $image_url = self::isValidUri($uri) ? self::transformRelative($uri) : $uri;
    $settings['image_url'] = $settings['image_url'] ?: $image_url;

    // Image style modifier can be multi-style images such as GridStack.
    if (!empty($settings['image_style']) && ($style = ImageStyle::load($settings['image_style']))) {
      $settings['image_url'] = self::transformRelative($uri, $style);
      $settings['cache_tags'] = $style->getCacheTags();

      // Only re-calculate dimensions if not cropped, nor already set.
      if (empty($settings['_dimensions'])) {
        $settings = array_merge($settings, self::transformDimensions($style, $settings));
      }
    }

    // The SVG placeholder should accept either original, or styled image.
    $settings['placeholder'] = empty($settings['placeholder']) ? static::generatePlaceholder($settings['width'], $settings['height']) : $settings['placeholder'];

    // Just in case, an attempted kidding gets in the way, relevant for UGC.
    $use_data_uri = !empty($settings['use_data_uri']) && substr($settings['image_url'], 0, 10) === 'data:image';
    if (!$use_data_uri) {
      $settings['image_url'] = UrlHelper::stripDangerousProtocols($settings['image_url']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function buildResponsiveImage(array &$settings) {
    return [
      '#type' => 'responsive_image',
      '#responsive_image_style_id' => $settings['responsive_image_style_id'],
      '#uri' => $settings['uri'],
      '#attributes' => [
        'data-b-lazy' => $settings['one_pixel'],
        'data-placeholder' => $settings['placeholder'],
      ],
    ];
  }

  /**
   * Modifies variables for blazy (non-)lazyloaded image.
   */
  public static function buildImage(array &$variables) {
    $settings = $variables['settings'];

    // Supports either lazy loaded image, or not.
    $variables['image'] += [
      '#theme' => 'image',
      '#uri' => empty($settings['lazy']) ? $settings['image_url'] : $settings['placeholder'],
    ];
  }

  /**
   * Modifies $variables to provide optional (Responsive) image attributes.
   */
  public static function imageAttributes(array &$variables) {
    $item = $variables['item'];
    $settings = $variables['settings'];
    $image = &$variables['image'];
    $attributes = &$variables['item_attributes'];

    // Respects hand-coded image attributes.
    if ($item) {
      if (!isset($attributes['alt'])) {
        $attributes['alt'] = isset($item->alt) ? $item->alt : NULL;
      }

      // Do not output an empty 'title' attribute.
      if (isset($item->title) && (mb_strlen($item->title) != 0)) {
        $attributes['title'] = $item->title;
      }
    }

    // Only output dimensions for non-svg. Respects hand-coded image attributes.
    // Do not pass it to $attributes to also respect both (Responsive) image.
    if (empty($settings['_sizes']) && !isset($attributes['width']) && $settings['extension'] != 'svg') {
      $image['#height'] = $settings['height'];
      $image['#width'] = $settings['width'];
    }

    // Provides [data-(src|lazy)] attributes for (Responsive) image.
    if (!empty($settings['lazy'])) {
      self::lazyAttributes($attributes, $settings);
    }

    $attributes['class'][] = 'media__image';
    self::commonAttributes($attributes, $variables['settings']);
    $image['#attributes'] = empty($image['#attributes']) ? $attributes : NestedArray::mergeDeep($image['#attributes'], $attributes);
  }

  /**
   * {@inheritdoc}
   */
  public static function iframeAttributes(array $settings) {
    $attributes['data-src']        = $settings['embed_url'];
    $attributes['src']             = 'about:blank';
    $attributes['class'][]         = 'b-lazy media__iframe';
    $attributes['allowfullscreen'] = TRUE;

    self::commonAttributes($attributes, $settings);

    // Adds specific Youtube attributes, related to mobile apps, etc.
    if (strpos($settings['embed_url'], 'youtu') !== FALSE) {
      $attributes['allow'] = 'autoplay; accelerometer; encrypted-media; gyroscope; picture-in-picture';
    }

    return $attributes;
  }

  /**
   * {@inheritdoc}
   */
  public static function buildIframe(array &$variables) {
    $settings           = &$variables['settings'];
    $variables['image'] = empty($settings['media_switch']) ? [] : $variables['image'];
    $settings['player'] = empty($settings['player']) ? (empty($settings['lightbox']) && $settings['media_switch'] != 'content') : $settings['player'];

    // Pass iframe attributes to template.
    $variables['iframe_attributes'] = new Attribute(self::iframeAttributes($settings));

    // Iframe is removed on lazyloaded, puts data at non-removable storage.
    $variables['attributes']['data-media'] = Json::encode(['type' => $settings['type'], 'scheme' => $settings['scheme']]);
  }

  /**
   * {@inheritdoc}
   */
  public static function lazyAttributes(array &$attributes, array $settings = []) {
    // Slick has its own class and methods: ondemand, anticipative, progressive.
    $attributes['class'][] = $settings['lazy_class'];
    $attributes['data-' . $settings['lazy_attribute']] = $settings['image_url'];
  }

  /**
   * Provide common attributes for IMG, IFRAME, VIDEO, DIV, etc. elements.
   */
  public static function commonAttributes(array &$attributes, array $settings = []) {
    $attributes['class'][] = 'media__element';

    // Support browser native lazy loading as per 8/2019 specific to Chrome 76+.
    // See https://web.dev/native-lazy-loading/
    if (!empty($settings['native'])) {
      $attributes['loading'] = 'lazy';
    }
  }

  /**
   * Modifies container attributes with aspect ratio for iframe, image, etc.
   */
  public static function aspectRatioAttributes(array &$attributes, array &$settings) {
    $settings['ratio'] = empty($settings['ratio']) ? '' : str_replace(':', '', $settings['ratio']);
    $attributes['class'][] = 'media--ratio media--ratio--' . $settings['ratio'];

    if ($settings['width'] && $settings['ratio'] == 'fluid') {
      // If "lucky", Blazy/ Slick Views galleries may already set this once.
      // Lucky when you don't flatten out the Views output earlier.
      $padding = $settings['padding_bottom'] ?: round((($settings['height'] / $settings['width']) * 100), 2);
      $attributes['style'] = 'padding-bottom: ' . $padding . '%';

      // Provides hint to breakpoints to work with multi-breakpoint ratio.
      $settings['_breakpoint_ratio'] = $settings['ratio'];

      // Views rewrite results or Twig inline_template may strip out `style`
      // attributes, provide hint to JS.
      $attributes['data-ratio'] = $padding;
    }
  }

  /**
   * Overrides variables for responsive-image.html.twig templates.
   */
  public static function preprocessResponsiveImage(array &$variables) {
    $image = &$variables['img_element'];
    $attributes = &$variables['attributes'];
    $placeholder = empty($attributes['data-placeholder']) ? static::PLACEHOLDER : $attributes['data-placeholder'];

    // Modifies <picture> [data-srcset] attributes on <source> elements.
    if (!$variables['output_image_tag']) {
      /** @var \Drupal\Core\Template\Attribute $source */
      if (isset($variables['sources']) && is_array($variables['sources'])) {
        foreach ($variables['sources'] as &$source) {
          $source->setAttribute('data-srcset', $source['srcset']->value());
          $source->removeAttribute('srcset');
        }
      }

      // Prevents invalid IMG tag when one pixel placeholder is disabled.
      $image['#uri'] = $placeholder;
      $image['#srcset'] = '';

      // Cleans up the no-longer relevant attributes for controlling element.
      unset($attributes['data-srcset'], $image['#attributes']['data-srcset']);
    }
    else {
      // Modifies <img> element attributes.
      $image['#attributes']['data-srcset'] = $attributes['srcset']->value();
      $image['#attributes']['srcset'] = '';
    }

    // The [data-b-lazy] is a flag indicating 1px placeholder.
    // This prevents double-downloading the fallback image, if enabled.
    if (!empty($attributes['data-b-lazy'])) {
      $image['#uri'] = $placeholder;
    }

    // More shared-with-image attributes are set at self::imageAttributes().
    $image['#attributes']['class'][] = 'b-responsive';

    // Cleans up the no-longer needed flags:
    foreach (['placeholder', 'b-lazy'] as $key) {
      unset($attributes['data-' . $key], $image['#attributes']['data-' . $key]);
    }
  }

  /**
   * Overrides variables for file-video.html.twig templates.
   */
  public static function preprocessFileVideo(array &$variables) {
    foreach ($variables['files'] as $files) {
      $source_attributes = &$files['source_attributes'];
      $source_attributes->setAttribute('data-src', $source_attributes['src']->value());
      $source_attributes->setAttribute('src', '');
    }
  }

  /**
   * Overrides variables for field.html.twig templates.
   */
  public static function preprocessField(array &$variables) {
    // Defines [data-blazy] attribute as required by the Blazy loader.
    $settings = $variables['element']['#blazy'];
    $variables['attributes']['class'][] = 'blazy';
    $variables['attributes']['data-blazy'] = empty($settings['blazy_data']) ? '' : Json::encode($settings['blazy_data']);
    if (!empty($settings['media_switch'])) {
      $switch = str_replace('_', '-', $settings['media_switch']);
      $variables['attributes']['data-' . $switch . '-gallery'] = TRUE;
    }
  }

  /**
   * A wrapper for file_url_transform_relative() to pass tests anywhere else.
   */
  public static function transformRelative($uri, $style = NULL) {
    $url = $style ? $style->buildUrl($uri) : file_create_url($uri);
    return file_url_transform_relative($url);
  }

  /**
   * {@inheritdoc}
   */
  public static function transformDimensions($style, array $data, $initial = FALSE) {
    $width  = $initial ? '_width' : 'width';
    $height = $initial ? '_height' : 'height';
    $uri    = $initial ? 'first_uri' : 'uri';
    $dim    = ['width' => $data[$width], 'height' => $data[$height]];

    $style->transformDimensions($dim, $uri);

    // Sometimes they are string, cast them integer to reduce JS logic.
    return ['width' => (int) $dim['width'], 'height' => (int) $dim['height']];
  }

  /**
   * {@inheritdoc}
   */
  public static function imageDimensions(array &$settings, $item = NULL, $initial = FALSE) {
    $width = $initial ? '_width' : 'width';
    $height = $initial ? '_height' : 'height';

    if (empty($settings[$width])) {
      $settings[$width] = $item && isset($item->width) ? $item->width : NULL;
      $settings[$height] = $item && isset($item->height) ? $item->height : NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function sanitize(array $attributes = []) {
    $clean_attributes = [];
    $tags = ['href', 'poster', 'src', 'about', 'data', 'action', 'formaction'];
    foreach ($attributes as $key => $value) {
      if (is_array($value)) {
        // Respects array item containing space delimited classes: aaa bbb ccc.
        $value = implode(' ', $value);
        $clean_attributes[$key] = array_map('\Drupal\Component\Utility\Html::cleanCssIdentifier', explode(' ', $value));
      }
      else {
        // Since Blazy is lazyloading known URLs, sanitize attributes which
        // make no sense to stick around within IMG or IFRAME tags.
        $kid = substr($key, 0, 2) === 'on' || in_array($key, $tags);
        $key = $kid ? 'data-' . $key : $key;
        $clean_attributes[$key] = $kid ? Html::cleanCssIdentifier($value) : Html::escape($value);
      }
    }
    return $clean_attributes;
  }

  /**
   * Returns the trusted HTML ID of a single instance.
   */
  public static function getHtmlId($string = 'blazy', $id = '') {
    if (!isset(static::$blazyId)) {
      static::$blazyId = 0;
    }

    // Do not use dynamic Html::getUniqueId, otherwise broken AJAX.
    $id = empty($id) ? ($string . '-' . ++static::$blazyId) : $id;
    return Html::getId($id);
  }

  /**
   * Returns the URI from the given image URL, relevant for unmanaged files.
   */
  public static function buildUri($image_url) {
    if (!UrlHelper::isExternal($image_url) && $normal_path = UrlHelper::parse($image_url)['path']) {
      $public_path = Settings::get('file_public_path');

      // Only concerns for the correct URI, not image URL which is already being
      // displayed via SRC attribute. Don't bother language prefixes for IMG.
      if ($public_path && strpos($normal_path, $public_path) !== FALSE) {
        $rel_path = str_replace($public_path, '', $normal_path);
        return file_build_uri($rel_path);
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function isValidUri($uri) {
    if (version_compare(\Drupal::VERSION, '8.8', '>=')) {
      // Adds a check to pass the tests due to non-DI.
      return \Drupal::hasService('stream_wrapper_manager') ? \Drupal::service('stream_wrapper_manager')->isValidUri($uri) : FALSE;
    }
    else {
      // Because this code only runs for older Drupal versions, we do not need
      // or want IDEs or the Upgrade Status module warning people about this
      // deprecated code usage. Setting the function name dynamically
      // circumvents those warnings.
      $function = 'file_valid_uri';
      return $function($uri);
    }
  }

  /**
   * Implements hook_config_schema_info_alter().
   *
   * @todo deprecate it for BlazyAlter::configSchemaInfoAlter at blazy:8.x-2.0.
   */
  public static function configSchemaInfoAlter(array &$definitions, $formatter = 'blazy_base', array $settings = []) {
    BlazyAlter::configSchemaInfoAlter($definitions, $formatter, $settings);
  }

}
