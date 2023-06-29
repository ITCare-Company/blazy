<?php

namespace Drupal\blazy\Utility;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\blazy\Blazy;

/**
 * Provides common sanitization methods.
 *
 * @todo checks for core equivalents, Xss::filter() is causing 404, etc.
 * @see https://www.drupal.org/project/drupal/issues/3109650
 */
class Sanitize {

  /**
   * Returns the sanitized attributes for user-defined (UGC Blazy Filter).
   *
   * When IMG and IFRAME are allowed for untrusted users, trojan horses are
   * welcome. Hence sanitize attributes relevant for BlazyFilter. The rest
   * should be taken care of by HTML filters before/ after Blazy. Blazy is not
   * responsible for iframes/ images put into text filters, nor managing them.
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
    $list = ['href', 'poster', 'src', 'about', 'data', 'action', 'formaction'];

    if (empty($attributes)) {
      return $output;
    }

    foreach ($attributes as $key => $value) {
      // Since Blazy is lazyloading known URLs, sanitize attributes which
      // make no sense to stick around within IMG or IFRAME tags.
      $key = Html::escape($key);
      $kid = mb_substr($key, 0, 2) === 'on' || in_array($key, $list);
      $key = $kid ? 'data-' . $key : $key;

      if (is_array($value)) {
        // Respects array item containing space delimited classes: aaa bbb ccc.
        $value = implode(' ', $value);
        $output[$key] = array_map('\Drupal\Component\Utility\Html::cleanCssIdentifier', explode(' ', $value));
      }
      else {
        $kid = $kid || self::kid($value);
        $escaped_value = $escaped ? Html::escape($value) : $value;
        $output[$key] = $kid || in_array($key, ['class', 'id'])
          ? Html::cleanCssIdentifier($value) : $escaped_value;
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

    // Fixed for 404 images when data URI is enabled via UI, or trusted.
    if (self::has($content, 'src="image/')) {
      $data_uri = self::has($content, 'base64')
        || self::has($content, 'svg+xml');

      if ($data_uri) {
        $content = str_replace('src="image/', 'src="data:image/', $content);
      }
    }

    // The $prestyle is the only known barrier to limit scopes.
    if ($style && $prestyle && self::has($content, $prestyle)) {
      $content = str_replace($prestyle, $prestyle . ' style="' . $style . '"', $content);
    }

    return $content;
  }

  /**
   * Returns the required URL relevant for UGC.
   *
   * The image itself can be a trojan horse, this is scratching the surface.
   * Blazy is not managing, or uploading images. It just works with them.
   *
   * @param string $url
   *   The given url.
   * @param bool $use_data_uri
   *   Whether to trust data URI.
   *
   * @return string
   *   The required url.
   *
   * @todo re-check to completely remove data URI option.
   */
  public static function url($url, $use_data_uri = FALSE): string {
    // This should be enough, unless data:image is tweakable.
    $allow = Blazy::isDataUri($url) && $use_data_uri;

    // @todo remove if data:image is known untweakable.
    if (self::kid($url)) {
      $allow = FALSE;
    }
    return $allow ? $url : UrlHelper::stripDangerousProtocols($url);
  }

  /**
   * Returns TRUE if it has the needle.
   */
  private static function has($content, $needle) {
    return strpos($content, $needle) !== FALSE;
  }

  /**
   * Returns true if it is another scary joke, relevant for UGC.
   *
   * @param string $value
   *   The given value to check for.
   *
   * @return bool
   *   Whether an attempted kidding, or normal input.
   */
  public static function kid($value): bool {
    $check = strtolower($value);

    // Should use the proper filter before/after Blazy, not this naive.
    // At least useless when already passed to self::attribute() upstream.
    return strpos($check, 'data:text') !== FALSE
      || strpos($check, 'script:') !== FALSE
      || strpos($check, ';&#') !== FALSE
      || strpos($check, '&#x') !== FALSE;
  }

}
