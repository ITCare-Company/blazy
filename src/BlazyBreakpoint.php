<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\NestedArray;
use Drupal\image\Entity\ImageStyle;

/**
 * Implements BlazyBreakpointInterface.
 */
class BlazyBreakpoint implements BlazyBreakpointInterface {

  /**
   * Checks if the image style contains crop in the effect name.
   *
   * @var array
   */
  private static $isCrop;

  /**
   * Returns available styles with crop in the effect name.
   *
   * @var array
   */
  private static $cropStyles;

  /**
   * Returns available image styles with crop in the name.
   */
  public static function cropStyles() {
    if (!isset(static::$cropStyles)) {
      static::$cropStyles = [];
      foreach (\Drupal::service('blazy.manager')->entityLoadMultiple('image_style') as $style) {
        foreach ($style->getEffects() as $effect) {
          if (strpos($effect->getPluginId(), 'crop') !== FALSE) {
            static::$cropStyles[$style->getName()] = $style;
            break;
          }
        }
      }
    }
    return static::$cropStyles;
  }

  /**
   * {@inheritdoc}
   */
  public static function isCrop($style) {
    if (!isset(static::$isCrop[$style])) {
      static::$isCrop[$style] = self::cropStyles() && isset(self::cropStyles()[$style]) ? self::cropStyles()[$style] : FALSE;
    }
    return static::$isCrop[$style];
  }

  /**
   * {@inheritdoc}
   */
  public static function attributes(array &$attributes, array &$settings) {
    Blazy::lazyAttributes($attributes, $settings);

    // Only provide multi-serving image URLs if breakpoints are provided.
    if (empty($settings['breakpoints'])) {
      return;
    }

    $srcset = $json = [];
    // https://css-tricks.com/sometimes-sizes-is-quite-important/
    // For older iOS devices that don't support w descriptors in srcset, the
    // first source item in the list will be used.
    $settings['breakpoints'] = array_reverse($settings['breakpoints']);
    foreach ($settings['breakpoints'] as $key => $breakpoint) {
      if (!($style = ImageStyle::load($breakpoint['image_style']))) {
        continue;
      }

      // Supports multi-breakpoint aspect ratio with irregular sizes.
      // Yet, only provide individual dimensions if not already set.
      // See Drupal\blazy\BlazyFormatter::setImageDimensions().
      $width = self::widthFromDescriptors($breakpoint['width']);
      if ($width && !empty($settings['_breakpoint_ratio']) && empty($settings['blazy_data']['dimensions'])) {
        $dimensions = Blazy::transformDimensions($style, $settings);

        $json[$width] = round((($dimensions['height'] / $dimensions['width']) * 100), 2);
      }

      $url = Blazy::transformRelative($settings['uri'], $style);
      $settings['breakpoints'][$key]['url'] = $url;

      // Still working with GridStack multi-image-style per grid box at 2019.
      if (!empty($settings['background'])) {
        $attributes['data-src-' . $key] = $url;
      }
      else {
        $width = trim($breakpoint['width']);
        $width = is_numeric($width) ? $width . 'w' : $width;
        $srcset[] = $url . ' ' . $width;
      }
    }

    if ($srcset) {
      $settings['srcset'] = implode(', ', $srcset);

      $attributes['srcset'] = '';
      $attributes['data-srcset'] = $settings['srcset'];
      $attributes['sizes'] = '100w';

      if (!empty($settings['sizes'])) {
        $attributes['sizes'] = trim($settings['sizes']);
        $settings['_sizes'] = $settings['sizes'];
        unset($attributes['height'], $attributes['width']);
      }
    }

    if ($json) {
      $settings['blazy_data']['dimensions'] = $json;
    }
  }

  /**
   * Gets the numeric "width" part from a descriptor.
   */
  public static function widthFromDescriptors($descriptor = '') {
    // Dynamic multi-serving aspect ratio with backward compatibility.
    $descriptor = trim($descriptor);
    if (is_numeric($descriptor)) {
      return (int) $descriptor;
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

    return is_numeric($width) ? (int) $width : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function cleanUpBreakpoints(array &$settings = []) {
    if (!empty($settings['breakpoints'])) {
      $breakpoints = array_filter(array_map('array_filter', $settings['breakpoints']));

      $settings['breakpoints'] = NestedArray::filter($breakpoints, function ($breakpoint) {
        return !(is_array($breakpoint) && (empty($breakpoint['width']) || empty($breakpoint['image_style'])));
      });
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function buildDataBlazy(array &$settings, $item = NULL) {
    // Identify that Blazy can be activated by breakpoints, regardless results.
    $settings['blazy'] = TRUE;

    // Bail out if blazy_data defined at BlazyFormatter::setImageDimensions().
    // Blazy doesn't always deal with image formatters, see self::isBlazy().
    if (!empty($settings['blazy_data'])) {
      return;
    }

    // May be set at BlazyFormatter::setImageDimensions() if using formatters,
    // yet not set from non-formatters like views fields, see self::isBlazy().
    Blazy::firstImageDimensions($settings, $item);

    $sources = $styles = [];
    $end = end($settings['breakpoints']);

    // Check for cropped images at the 5 given styles before any hard work.
    // Ok as run once at the top container regardless of thousand of images.
    foreach ($settings['breakpoints'] as $key => $breakpoint) {
      if ($style = self::isCrop($breakpoint['image_style'])) {
        $styles[$key] = $style;
      }
    }

    // Bail out if not all images are cropped at all breakpoints.
    // The site builder just don't read the performance tips section.
    if (count($styles) != count($settings['breakpoints'])) {
      return;
    }

    // We have all images cropped here.
    foreach ($settings['breakpoints'] as $key => $breakpoint) {
      if (!($width = self::widthFromDescriptors($breakpoint['width']))) {
        continue;
      }

      // Sets dimensions once, and let all images inherit.
      if (($style = $styles[$key]) && (!empty($settings['first_uri']) && !empty($settings['ratio']))) {
        $dimensions = Blazy::transformDimensions($style, $settings, TRUE);

        $padding = round((($dimensions['height'] / $dimensions['width']) * 100), 2);
        $settings['blazy_data']['dimensions'][$width] = $padding;

        // Only set padding-bottom for the last breakpoint to avoid FOUC.
        if ($end['width'] == $breakpoint['width']) {
          $settings['padding_bottom'] = $padding;
        }
      }

      // If BG, provide [data-src-BREAKPOINT], regardless uri or ratio.
      if (!empty($settings['background'])) {
        $sources[] = ['width' => (int) $width, 'src' => 'data-src-' . $key];
      }
    }

    // Supported modules can add blazy_data as [data-blazy] to the container.
    // This also informs individual images to not work with dimensions any more
    // as _all_ breakpoint image styles contain 'crop'.
    // As of Blazy v1.6.0 applied to BG only.
    if ($sources) {
      $settings['blazy_data']['breakpoints'] = $sources;
    }

    if (!empty($settings['use_ajax'])) {
      $settings['blazy_data']['useAjax'] = TRUE;
    }
  }

}
