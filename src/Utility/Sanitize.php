<?php

namespace Drupal\blazy\Utility;

use Drupal\Component\Utility\Html;

/**
 * Provides common sanitization methods.
 *
 * @todo checks for core equivalents.
 */
class Sanitize {

  /**
   * Returns the sanitized attributes for user-defined (UGC Blazy Filter).
   *
   * When IMG and IFRAME are allowed for untrusted users, trojan horses are
   * welcome. Hence sanitize attributes relevant for BlazyFilter. The rest
   * should be taken care of by HTML filters after Blazy.
   *
   * @param array $attributes
   *   The given attributes to sanitize.
   * @param bool $escaped
   *   Sets to FALSE to avoid double escapes, for further processing.
   *
   * @return array
   *   The sanitized $attributes suitable for UGC, such as Blazy filter.
   */
  public static function attribute(array $attributes, $escaped = TRUE): array {
    $output = [];
    $tags = ['href', 'poster', 'src', 'about', 'data', 'action', 'formaction'];

    if (empty($attributes)) {
      return $output;
    }

    foreach ($attributes as $key => $value) {
      $key = Html::escape($key);
      if (is_array($value)) {
        // Respects array item containing space delimited classes: aaa bbb ccc.
        $value = implode(' ', $value);
        $output[$key] = array_map('\Drupal\Component\Utility\Html::cleanCssIdentifier', explode(' ', $value));
      }
      else {
        // Since Blazy is lazyloading known URLs, sanitize attributes which
        // make no sense to stick around within IMG or IFRAME tags.
        $kid = mb_substr($key, 0, 2) === 'on' || in_array($key, $tags);
        $key = $kid ? 'data-' . $key : $key;
        $escaped_value = $escaped ? Html::escape($value) : $value;
        $output[$key] = $kid ? Html::cleanCssIdentifier($value) : $escaped_value;
      }
    }
    return $output;
  }

  /**
   * Returns the unstripped content after being stripped.
   *
   * Xss::filter() stripped a few useful and assumed safe attributes and its
   * values. This method corrects very few known safe ones while still keeping
   * safety in mind.
   *
   * @param string $content
   *   The given string content.
   * @param array $options
   *   The options.
   *
   * @return string
   *   The content after corrections.
   *
   * @see https://www.drupal.org/project/drupal/issues/3109650
   */
  public static function unstrip($content, array $options): string {
    $prestyle = $options['prestyle'] ?? '';
    $style = $options['style'] ?? '';

    // @todo remove when local videos are generated dynamically like remote.
    if (self::has($content, 'src="blank"')) {
      $content = str_replace('src="blank"', 'src="about:blank"', $content);
    }

    // Fixed for 404 images when data URI is enabled via UI or trusted.
    $blazy = self::has($content, 'b-lazy');
    if ($blazy && self::has($content, 'src="image/"')) {
      $data_uri = self::has($content, 'base64')
        || self::has($content, 'svg+xml');

      if ($data_uri) {
        $content = str_replace('src="image/"', 'src="data:image/"', $content);
      }
    }

    if ($style && $prestyle && self::has($content, $prestyle)) {
      $content = str_replace($prestyle, $prestyle . ' style="' . $style . '"', $content);
    }
    return $content;
  }

  /**
   * Returns TRUE if it has the needle.
   */
  private static function has($content, $needle) {
    return strpos($content, $needle) !== FALSE;
  }

}
