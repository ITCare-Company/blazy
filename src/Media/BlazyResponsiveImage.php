<?php

namespace Drupal\blazy\Media;

use Drupal\Core\Cache\Cache;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazySettings;
use Drupal\blazy\Theme\BlazyAttribute;

/**
 * Provides responsive image utilities.
 */
class BlazyResponsiveImage {

  /**
   * The Responsive image styles.
   *
   * @var array
   */
  private static $styles;

  /**
   * Build out Responsive image.
   */
  public static function background(array &$attributes, array &$settings) {
    $blazies = $settings['blazies'];

    // Makes Responsive image usable as CSS background image sources.
    // @todo merge it with BlazyFormatter + BlazyFilter.
    $resimage = $blazies->get('resimage', []);
    if ($settings['background'] && isset($resimage['styles'])) {
      $srcset = $ratios = [];

      foreach ($resimage['styles'] as $style) {
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

      // To make compatible with old bLazy which expects no placeholder, provide
      // a real smallest image. Bio will map it to the current breakpoint later.
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
  public static function dimensions(array &$settings = [], $initial = TRUE): BlazySettings {
    $blazies = $settings['blazies'];
    $dimensions = $blazies->get('dimensions', []);
    if ($dimensions) {
      return $blazies;
    }

    $ratios = [];
    $resimage = $blazies->get('resimage.style');
    foreach (self::styles($resimage)['styles'] as $style) {
      $styled = BlazyImage::transformDimensions($style, $settings, $initial);

      // In order to avoid layout reflow, we get dimensions beforehand.
      $width = $styled['width'];
      $height = $styled['height'];

      // @todo merge ratios into dimensions elsewhere.
      $ratios[$width] = $ratio = empty($width) ? 100 : round((($height / $width) * 100), 2);
      $dimensions[$width] = [
        'width' => $width,
        'height' => $height,
        'ratio' => $ratio,
      ];
    }

    // Sort the srcset from small to large image width or multiplier.
    ksort($dimensions);
    ksort($ratios);

    // Informs individual images that dimensions are already set once.
    // Dynamic aspect ratio is useless without JS.
    $blazies->set('dimensions', $dimensions)
      ->set('is.dimensions', TRUE)
      ->set('item.padding_bottom', end($ratios))
      ->set('ratios', $ratios);

    return $blazies;
  }

  /**
   * Provides Responsive image sources relevant for link preload.
   */
  public static function sources(array &$settings = []): array {
    if (!($manager = Blazy::breakpointManager())) {
      return [];
    }

    $blazies = $settings['blazies'];
    $func = function ($uri) use ($manager, $settings, $blazies) {
      $fallback = NULL;
      $sources = $variables = [];
      $style = $blazies->get('resimage.style');
      $dimensions = $blazies->get('dimensions', []);
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
  public static function dimensionsAndSources(array &$settings = [], $initial = TRUE): void {
    $blazies = $settings['blazies'];
    $preload = !empty($settings['preload']);

    // @todo merge background here.
    if ($preload || $blazies->is('fluid')) {
      BlazyResponsiveImage::dimensions($settings, $initial);
    }
    if ($preload) {
      BlazyResponsiveImage::sources($settings);
    }
  }

  /**
   * Modifies fallback image style.
   */
  public static function fallback(array &$settings): void {
    $blazies = $settings['blazies'];

    // Mimicks private _responsive_image_image_style_url, #3119527.
    if (empty($settings['image_style'])
      && $resimage = $blazies->get('resimage.style')) {
      $fallback = $resimage->getFallbackImageStyle();
      if ($fallback == '_empty image_') {
        $url = $blazies->get('placeholder');

        // @todo remove.
        $settings['image_url'] = $url;
        $blazies->set('image.url', $url);
      }
      else {
        $settings['image_style'] = $fallback;
      }
    }
  }

  /**
   * Defines the Responsive image id, styles and caches tags.
   */
  public static function define(&$blazies, $resimage) {
    $id = $resimage->id();
    $styles = BlazyResponsiveImage::styles($resimage);

    $blazies->set('resimage.id', $id)
      ->set('resimage.caches', $styles['caches'] ?? [])
      ->set('resimage.styles', $styles['styles'] ?? []);
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
        'styles' => $image_styles,
      ];
    }
    return static::$styles[$id];
  }

}
