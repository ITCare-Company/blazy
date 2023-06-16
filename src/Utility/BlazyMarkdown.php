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
   * Processes Markdown text, and convert into HTML suitable for the help text.
   *
   * @param string $text
   *   The text to apply the Markdown filter to.
   * @param bool $sanitize
   *   True, if the text should be sanitized.
   * @param bool $help
   *   True, if the text will be used for Help pages.
   *
   * @return string
   *   The filtered, or raw converted text.
   */
  public static function parse($text, $sanitize = TRUE, $help = TRUE): string {
    if (!self::isApplicable()) {
      $text = $sanitize ? Xss::filterAdmin($text) : $text;
      return $help ? '<pre>' . $text . '</pre>' : $text;
    }

    if (class_exists('Michelf\MarkdownExtra')) {
      $text = MarkdownExtra::defaultTransform($text);
    }
    elseif (class_exists('League\CommonMark\CommonMarkConverter')) {
      $converter = new CommonMarkConverter();
      if (method_exists($converter, 'convert')) {
        $text = $converter->convert($text);
      }
      else {
        // Deprecated since 2.2.
        $method = 'convertToHtml';
        if (is_callable([$converter, $method])) {
          $text = $converter->{$method}($text);
        }
      }
    }

    // We do not pass it to FilterProcessResult, as this is meant simple.
    return $sanitize ? Xss::filterAdmin($text) : $text;
  }

  /**
   * Checks if we have the needed classes.
   */
  private static function isApplicable(): bool {
    return class_exists('Michelf\MarkdownExtra') || class_exists('League\CommonMark\CommonMarkConverter');
  }

}
