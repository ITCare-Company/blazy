<?php

namespace Drupal\blazy\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\blazy\Blazy;

/**
 * Provides common cache utility static methods.
 */
class BlazyCache {

  /**
   * Return the available lightboxes, to be cached to avoid disk lookups.
   */
  public static function lightboxes($root): array {
    $lightboxes = [];
    if (function_exists('colorbox_theme')) {
      $lightboxes[] = 'colorbox';
    }

    // @todo remove deprecated unmaintained photobox.
    // Most lightboxes are unmantained, only supports mostly used, or robust.
    $paths = [
      'photobox' => 'photobox/photobox/jquery.photobox.js',
      'mfp' => 'magnific-popup/dist/jquery.magnific-popup.min.js',
    ];

    foreach ($paths as $key => $path) {
      if (is_file($root . '/libraries/' . $path)) {
        $lightboxes[] = $key;
      }
    }
    return $lightboxes;
  }

  /**
   * Return the cache metadata common for all blazy-related modules.
   */
  public static function metadata(array $build = []): array {
    $manager  = Blazy::service('blazy.manager');
    $settings = Blazy::toHashtag($build) ?: $build;

    // @todo renove after sub-modules, including some fallback settings.
    Blazy::verify($settings);

    $blazies   = $settings['blazies'];
    $namespace = $blazies->get('namespace', 'blazy');
    $count     = $blazies->get('count', count($settings));
    $max_age   = $manager->config('cache.page.max_age', 'system.performance');
    $max_age   = empty($settings['cache']) ? $max_age : $settings['cache'];
    $id        = Blazy::getHtmlId($namespace . $count);
    $id        = $blazies->get('css.id', $id);

    // Put them into cxahe.
    $cache             = [];
    $suffixes[]        = $count;
    $cache['tags']     = Cache::buildTags($namespace . ':' . $id, $suffixes, '.');
    $cache['contexts'] = ['languages', 'url.site'];
    $cache['max-age']  = $max_age;
    $cache['keys']     = $blazies->get('cache.metadata.keys', [$id]);

    if ($tags = $blazies->get('cache.metadata.tags', [])) {
      $cache['tags'] = Cache::mergeTags($cache['tags'], $tags);
    }

    return $cache;
  }

}
