<?php

namespace Drupal\blazy\Utility;

use Drupal\Component\Utility\Xss;
use Michelf\MarkdownExtra;
use League\CommonMark\CommonMarkConverter;

/**
 * Provides markdown utilities only useful for the help text.
 */
class BlazyMarkdown {

  /**
   * The blazy filter ID identified by field_name.
   *
   * @var array
   */
  private static $filterId = [];

  /**
   * Checks if we have the needed classes.
   */
  public static function isApplicable() {
    return class_exists('Michelf\MarkdownExtra') || class_exists('League\CommonMark\CommonMarkConverter');
  }

  /**
   * Processes Markdown text, and convert into HTML suitable for the help text.
   *
   * @param string $string
   *   The string to apply the Markdown filter to.
   * @param bool $sanitize
   *   True, if the string should be sanitized.
   *
   * @return string
   *   The filtered, or raw converted string.
   */
  public static function parse($string = '', $sanitize = TRUE) {
    if (!self::isApplicable()) {
      return '<pre>' . $string . '</pre>';
    }

    if (class_exists('Michelf\MarkdownExtra')) {
      $string = MarkdownExtra::defaultTransform($string);
    }
    elseif (class_exists('League\CommonMark\CommonMarkConverter')) {
      $converter = new CommonMarkConverter();
      $string = $converter->convertToHtml($string);
    }

    // We do not pass it to FilterProcessResult, as this is meant simple.
    return $sanitize ? Xss::filterAdmin($string) : $string;
  }

  /**
   * Checks if Blazy filter is enabled, and pass it settings to field template.
   */
  public static function isBlazyFilter(array &$variables) {
    $key = $variables['field_name'];

    if (!isset(static::$filterId[$key])) {
      if ($item = $variables['items'][0]) {
        $text = isset($item['content'], $item['content']['#text']) ? $item['content']['#text'] : NULL;
        $format = isset($item['content'], $item['content']['#format']) ? $item['content']['#format'] : NULL;
        if ($text && $format) {
          if (stripos($text, 'img ') !== FALSE || stripos($text, 'iframe ') !== FALSE) {
            $account = isset($variables['user']) ? $variables['user'] : NULL;
            $formats = \filter_formats($account);
            if ($formats && isset($formats[$format], $formats[$format]->filters()->getConfiguration()['blazy_filter'])) {
              $settings = $formats[$format]->filters()->getConfiguration()['blazy_filter']['settings'];
              $variables['element']['#blazy'] = $settings;
            }
          }
        }
      }

      static::$filterId[$key] = TRUE;
    }
  }

}
