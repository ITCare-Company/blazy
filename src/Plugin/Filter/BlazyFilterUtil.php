<?php

namespace Drupal\blazy\Plugin\Filter;

use Drupal\Component\Utility\Crypt;
use Drupal\blazy\Blazy;
use Drupal\blazy\internals\Internals;

/**
 * Provides shared filter utilities.
 */
class BlazyFilterUtil extends Shortcode {

  /**
   * Returns a randomized id.
   */
  public static function getId($id = 'blazy-filter'): string {
    return Internals::getHtmlId(str_replace('_', '-', $id) . '-' . Crypt::randomBytesBase64(8));
  }

  /**
   * Returns settings for attachments.
   */
  public static function attach(array $settings = []): array {
    $all = ['blazy' => TRUE, 'filter' => TRUE, 'ratio' => TRUE] + $settings;
    $all['media_switch'] = $switch = $settings['media_switch'] ?? '';

    if (!empty($settings[$switch])) {
      $all[$switch] = $settings[$switch];
    }

    return $all;
  }

  /**
   * Returns the inner HTMLof the DOMElement node.
   *
   * See https://www.php.net/manual/en/class.domelement.php#101243
   */
  public static function getHtml(\DOMElement $node): ?string {
    $text = '';
    foreach ($node->childNodes as $child) {
      if ($child instanceof \DOMElement) {
        $text .= $child->ownerDocument->saveXML($child);
      }
    }
    return $text;
  }

  /**
   * Removes nodes.
   */
  public static function removeNodes(&$nodes): void {
    foreach ($nodes as $node) {
      if ($node->parentNode) {
        $node->parentNode->removeChild($node);
      }
    }
  }

  /**
   * Return valid nodes based on the allowed tags.
   */
  public static function validNodes(\DOMDocument $dom, array $allowed_tags = [], $exclude = ''): array {
    $valid_nodes = [];
    foreach ($allowed_tags as $allowed_tag) {
      $nodes = $dom->getElementsByTagName($allowed_tag);
      /* @phpstan-ignore-next-line */
      if ($nodes->length > 0) {
        foreach ($nodes as $node) {
          if ($exclude && $node->hasAttribute($exclude)) {
            continue;
          }

          $valid_nodes[] = $node;
        }
      }
    }
    return $valid_nodes;
  }

  /**
   * Returns a valid node, excluding blur/ noscript images.
   */
  public static function getValidNode($children) {
    $child = $children->item(0);
    $class = $child->getAttribute('class');
    $is_blur = $class && strpos($class, 'b-blur') !== FALSE;
    $is_bg = $class && strpos($class, 'b-bg') !== FALSE;

    if ($is_blur && !$is_bg) {
      $child = $children->item(1) ?: $child;
    }
    return $child;
  }

  /**
   * Returns a image/ iframe src.
   *
   * Checks if we have a valid file entity, not hard-coded image URL.
   */
  public static function getValidSrc($node, $use_data_uri = FALSE): ?string {
    $url = '';

    // Prevents data URI from screwing up, unless consciously required.
    $func = function ($input, $key) use ($use_data_uri) {
      $check = trim($input ?: '');
      if ($check) {
        $data_uri = Blazy::isDataUri($check);
        // @todo recheck against sub-modules priority order in Filter admin.
        // The SRC might be 1px, but DATA-SRC is the real data URI.
        if (!$data_uri || ($data_uri && $use_data_uri)) {
          return $check;
        }
      }
      return '';
    };

    // Prioritize data-src for sub-module filters after Blazy.
    foreach (['data-src', 'src'] as $key) {
      $src = $node->getAttribute($key);
      $check = $func($src, $key);

      if ($check) {
        $url = $check;
        break;
      }
    }

    // If starts with 2 slashes, it is always external.
    if ($url && mb_substr($url, 0, 2) === '//') {
      // We need to query stored SRC for image dimensions, https is enforced.
      $url = 'https:' . $url;
    }

    return $url;
  }

  /**
   * Returns DOMElement nodes expected to be grid, or slide items.
   */
  public static function getNodes(\DOMDocument $dom, $tag = '//grid') {
    $xpath = new \DOMXPath($dom);

    return $xpath->query($tag);
  }

  /**
   * Returns attributes extracted from a DOMElement if any.
   */
  public static function getAttribute(\DOMElement $node, array $excludes = []): array {
    $attributes = [];
    /* @phpstan-ignore-next-line */
    if ($node && $node->attributes->length) {
      foreach ($node->attributes as $attribute) {
        $name  = $attribute->nodeName;
        $value = $attribute->nodeValue;

        if ($excludes && in_array($name, $excludes)) {
          continue;
        }

        if ($name == 'class') {
          $value = array_map('trim', explode(' ', $value));
        }

        $attributes[$name] = $value;
      }
    }

    // Sanitization is done downstream, not here.
    return $attributes;
  }

  /**
   * Extract grids from the node attribute.
   */
  public static function toGrid(\DOMElement $node, array &$settings): void {
    if ($check = $node->getAttribute('grid')) {
      $blazies = $settings['blazies'];
      [$settings['style'], $grid, $settings['visible_items']] = array_pad(array_map('trim', explode(":", $check, 3)), 3, NULL);

      if ($grid) {
        $grid = strip_tags($grid);
        [
          $settings['grid_small'],
          $settings['grid_medium'],
          $settings['grid'],
        ] = array_pad(array_map('trim', explode("-", $grid, 3)), 3, NULL);

        $is_grid = !empty($settings['style']) && !empty($settings['grid']);
        $blazies->set('is.grid', $is_grid);

        if (!empty($settings['style'])) {
          // Babysits typo due to hardcoding. The expected is flex, not flexbox.
          if ($settings['style'] == 'flexbox') {
            $settings['style'] = 'flex';
          }
          Internals::toNativeGrid($settings);
        }
      }
    }
  }

}
