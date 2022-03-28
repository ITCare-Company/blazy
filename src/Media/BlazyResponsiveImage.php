<?php

namespace Drupal\blazy\Media;

use Drupal\Core\Cache\Cache;
use Drupal\blazy\Blazy;
use Drupal\blazy\Theme\BlazyAttribute;

/**
 * Provides responsive image utilities.
 *
 * @todo recap similiraties and make them plugins.
 */
class BlazyResponsiveImage {

  /**
   * The Responsive image styles.
   *
   * @var array
   */
  private static $styles;

  /**
   * Makes Responsive image usable as CSS background image sources.
   *
   * This is per item dependent on URI, the self::dimensions() is global.
   *
   * @todo use resimage.dimensions once BlazyFormatter + BlazyFilter synced,
   * and Picture are checked with its multiple dimensions aka art direction.
   */
  public static function background(array &$attributes, array &$settings): void {
    $blazies = $settings['blazies'];
    $resimage = $blazies->get('resimage.style');

    if (empty($settings['background']) || !$resimage) {
      return;
    }

    if ($styles = self::styles($resimage)) {
      $srcset = $ratios = [];
      $ratios = $blazies->get('ratios', []);

      foreach (array_values($styles['styles']) as $style) {
        $styled = array_merge($settings, BlazyImage::transformDimensions($style, $settings, FALSE));

        // Sort image URLs based on width.
        $data = BlazyImage::background($styled, $style);
        $srcset[$styled['width']] = $data;
        $ratios[$styled['width']] = $data['ratio'];
      }

      // Sort the srcset from small to large image width or multiplier.
      ksort($srcset);
      ksort($ratios);

      // Prevents NestedArray from making these indices.
      $blazies->set('bgs', (object) $srcset)
        ->set('ratios', $ratios)
        ->set('item.padding_bottom', end($ratios));

      // To make compatible with old bLazy (not Bio) which expects no 1px
      // for [data-src], else error, provide a real smallest image. Bio will
      // map it to the current breakpoint later.
      $bg = reset($srcset);
      $unlazy = $blazies->is('undata');
      $old_url = $blazies->get('image.url', $settings['image_url'] ?? '');
      $new_url = $unlazy ? $old_url : $bg['src'];

      // @todo remove.
      $settings['image_url'] = $new_url;

      $blazies->set('is.unlazy', $unlazy)
        ->set('image.url', $new_url);

      BlazyAttribute::lazy($attributes, $settings);
    }
  }

  /**
   * Sets dimensions once to reduce method calls for Responsive image.
   */
  public static function dimensions(array &$settings, $initial = TRUE): void {
    $blazies = $settings['blazies'];
    $dimensions = $blazies->get('resimage.dimensions', []);
    $resimage = $blazies->get('resimage.style');

    if ($dimensions || !$resimage) {
      return;
    }

    $styles = self::styles($resimage);
    $names = $ratios = [];

    foreach (array_values($styles['styles']) as $style) {
      $styled = BlazyImage::transformDimensions($style, $settings, $initial);

      // In order to avoid layout reflow, we get dimensions beforehand.
      $width = $styled['width'];
      $height = $styled['height'];

      // @todo merge ratios into dimensions elsewhere.
      $names[$width] = $style->id();
      $ratios[$width] = $ratio = BlazyImage::ratio($styled);
      $dimensions[$width] = [
        'width' => $width,
        'height' => $height,
        'ratio' => $ratio,
      ];
    }

    // Sort the srcset from small to large image width or multiplier.
    ksort($dimensions);
    ksort($names);
    ksort($ratios);

    // Informs individual images that dimensions are already set once.
    // Dynamic aspect ratio is useless without JS.
    $blazies->set('resimage.dimensions', $dimensions)
      ->set('is.dimensions', TRUE)
      ->set('item.padding_bottom', end($ratios))
      ->set('ratios', $ratios)
      ->set('resimage.ids', array_values($names));

    if (!$blazies->get('image.dimensions.styled')) {
      $blazies->set('image.dimensions.styled', end($dimensions));
    }
  }

  /**
   * Provides Responsive image sources relevant for link preload.
   */
  public static function sources(array &$settings): array {
    if (!($manager = Blazy::breakpointManager())) {
      return [];
    }

    $blazies = $settings['blazies'];
    $func = function ($uri) use ($manager, $settings, $blazies) {
      $fallback = NULL;
      $sources = $variables = [];
      $style = $blazies->get('resimage.style');
      $dimensions = $blazies->get('resimage.dimensions', []);
      $end = end($dimensions);

      $variables['uri'] = $uri;
      foreach (['width', 'height'] as $key) {
        $variables[$key] = $end[$key] ?? $settings[$key] ?? NULL;
      }

      $breakpoints = array_reverse($manager->getBreakpointsByGroup($style->getBreakpointGroup()));
      $function = '_responsive_image_build_source_attributes';
      if (is_callable($function)) {
        $fallback = \_responsive_image_image_style_url($style->getFallbackImageStyle(), $variables['uri']);
        foreach ($style->getKeyedImageStyleMappings() as $breakpoint_id => $multipliers) {
          if (isset($breakpoints[$breakpoint_id])) {
            $sources[] = $function($variables, $breakpoints[$breakpoint_id], $multipliers);
          }
        }
      }

      $blazies->set('resimage.fallback.url', $fallback);
      return empty($sources) ? [] : [
        'items' => $sources,
        'fallback' => $fallback,
      ];
    };

    $output = [];
    if ($uris = $blazies->get('uris')) {
      // Preserves indices even if empty to have correct mixed media elsewhere.
      foreach ($uris as $uri) {
        $output[] = empty($uri) ? [] : $func($uri);
      }
    }

    $blazies->set('resimage.sources', $output);

    return $output;
  }

  /**
   * Modifies dimensions and sources.
   */
  public static function dimensionsAndSources(array &$settings, $initial = TRUE): void {
    // Do not limit to preload or fluid, to re-use this for background, etc.
    self::dimensions($settings, $initial);

    // Currently only needed by Preload.
    if (!empty($settings['preload'])) {
      self::sources($settings);
    }
  }

  /**
   * Modifies fallback image style.
   */
  public static function fallback(array &$settings, $placeholder): void {
    $blazies = $settings['blazies'];
    $id = '_empty image_';
    $width = $height = 1;
    $data_src = $placeholder;

    // If not enabled via UI, by default, always 1px, or the custom Placeholder.
    if ($blazies->get('ui.one_pixel') || !empty($settings['image_style'])) {
      return;
    }

    // Mimicks private _responsive_image_image_style_url, #3119527.
    if ($resimage = $blazies->get('resimage.style')) {
      $fallback = $resimage->getFallbackImageStyle();

      if ($fallback == $id) {
        $data_src = $placeholder;
      }
      else {
        $settings['image_style'] = $id = $fallback;
        if ($blazy = Blazy::service('blazy.manager')) {
          $uri = $blazies->get('uri');

          // @todo use dimensions based on the chosen fallback.
          if ($uri && $style = $blazy->entityLoad($id, 'image_style')) {
            $data_src = BlazyFile::transformRelative($uri, $style);
            $tn_uri = $style->buildUri($uri);

            [
              'width' => $width,
              'height' => $height,
            ] = BlazyImage::transformDimensions($style, $settings);

            $placeholder = Placeholder::generate($width, $height);
            $blazies->set('resimage.fallback.style', $style);
            $blazies->set('resimage.fallback.uri', $tn_uri);
          }
        }
      }

      $blazies->set('resimage.fallback.url', $data_src);
    }

    if ($data_src) {
      // The controller `data-src` attribute, might be valid image thumbnail.
      $blazies->set('image.url', $data_src);
      $blazies->set('placeholder.id', $id);
      // The controller `src` attribute, the placeholder.
      $blazies->set('placeholder.url', $placeholder);
      $blazies->set('placeholder.width', $width);
      $blazies->set('placeholder.height', $height);
    }
  }

  /**
   * Defines the Responsive image id, styles and caches tags.
   */
  public static function define(&$blazies, $resimage) {
    $id = $resimage->id();
    $styles = self::styles($resimage);

    // @todo move it out of blazies.
    $blazies->set('resimage.id', $id)
      ->set('resimage.caches', $styles['caches'] ?? []);
  }

  /**
   * Returns the Responsive image styles and caches tags.
   *
   * @param object $resimage
   *   The responsive image style entity.
   *
   * @return array|mixed
   *   The responsive image styles and cache tags.
   */
  public static function styles($resimage): array {
    $id = $resimage->id();

    if (!isset(static::$styles[$id])) {
      $cache_tags = $resimage->getCacheTags();
      $image_styles = \blazy()->entityLoadMultiple('image_style', $resimage->getImageStyleIds());

      foreach ($image_styles as $image_style) {
        $cache_tags = Cache::mergeTags($cache_tags, $image_style->getCacheTags());
      }

      static::$styles[$id] = [
        'caches' => $cache_tags,
        'names' => array_keys($image_styles),
        'styles' => $image_styles,
      ];
    }
    return static::$styles[$id];
  }

}
