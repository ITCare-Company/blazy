<?php

namespace Drupal\blazy\Plugin\Filter;

use Drupal\Component\Utility\Crypt;
use Drupal\blazy\Blazy;

/**
 * Provides shared filter utilities.
 */
class BlazyFilterUtil {

  /**
   * Returns a randomized id.
   */
  public static function getId($id = 'blazy-filter') {
    return Blazy::getHtmlId(str_replace('_', '-', $id) . '-' . Crypt::randomBytesBase64(8));
  }

  /**
   * Returns settings for attachments.
   */
  public static function attach(array $settings = []) {
    $all = ['blazy' => TRUE, 'filter' => TRUE, 'ratio' => TRUE];
    $all['media_switch'] = $switch = $settings['media_switch'];

    if (!empty($settings[$switch])) {
      $all[$switch] = $settings[$switch];
    }

    return $all;
  }

  /**
   * Returns string between delimiters, or empty if not found.
   */
  public static function getStringBetween($string, $start = '[', $end = ']') {
    $string = ' ' . $string;
    $ini = mb_strpos($string, $start);

    if ($ini == 0) {
      return '';
    }

    $ini += strlen($start);
    $len = mb_strpos($string, $end, $ini) - $ini;
    return trim(substr($string, $ini, $len));
  }

  /**
   * Returns the inner HTMLof the DOMElement node.
   *
   * See http://www.php.net/manual/en/class.domelement.php#101243
   */
  public static function getHtml($node) {
    $text = '';
    foreach ($node->childNodes as $child) {
      if ($child instanceof \DOMElement) {
        $text .= $child->ownerDocument->saveXML($child);
      }
    }
    return $text;
  }

  /**
   * Remove HTML tags from a string.
   */
  public static function unwrap($string, $delimiter = 'splide') {
    $find = ["/\[\/$delimiter\]/si"];
    $pattern = "/\[$delimiter(.*?)\]/";

    if (mb_strpos($string, "$delimiter]</p>") !== FALSE) {
      $find = ["/<p\>\[\/$delimiter\]<\/p>/si"];
      $pattern = "/<p>\[$delimiter(.*?)\]<\/p>/";
    }

    preg_match_all($pattern, $string, $matches);

    if ($matches) {
      foreach ($matches[0] as $match) {
        $value = strip_tags($match);
        $value = str_replace("[", "<", $value);
        $value = str_replace("]", ">", $value);
        $string = str_replace($match, $value, $string);
      }
    }

    return preg_replace($find, ["</$delimiter>"], $string);
  }

  /**
   * Removes nodes.
   */
  public static function removeNodes(&$nodes) {
    foreach ($nodes as $node) {
      if ($node->parentNode) {
        $node->parentNode->removeChild($node);
      }
    }
  }

  /**
   * Return valid nodes based on the allowed tags.
   */
  public static function validNodes(\DOMDocument $dom, array $allowed_tags = [], $exclude = '') {
    $valid_nodes = [];
    foreach ($allowed_tags as $allowed_tag) {
      $nodes = $dom->getElementsByTagName($allowed_tag);
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

}
