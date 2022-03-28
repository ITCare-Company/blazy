<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\UrlHelper;

/**
 * Provides preload utility.
 *
 * @todo recap similiraties and make them plugins.
 */
class Preloader {

  /**
   * Preload late-discovered resources for better performance.
   *
   * @see https://web.dev/preload-critical-assets/
   * @see https://caniuse.com/?search=preload
   * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Link_types/preload
   * @see https://developer.chrome.com/blog/new-in-chrome-73/#more
   * @todo support multiple hero images like carousels.
   */
  public static function preload(array &$load, array $settings = []): void {
    $blazies = $settings['blazies'];
    $uris = $blazies->get('uris', []);
    if (empty($uris)) {
      return;
    }

    $mime = mime_content_type($uris[0]);
    [$type] = array_map('trim', explode('/', $mime, 2));

    $link = function ($url, $uri = NULL, $item = NULL) use ($mime, $type): array {
      // Each field may have different mime types for each image just like URIs.
      $mime = $uri ? mime_content_type($uri) : $mime;
      if ($item) {
        $item_type = $item['type'] ?? NULL;
        $mime = $item_type ? $item_type->value() : $mime;
      }

      [$type] = array_map('trim', explode('/', $mime, 2));
      $key = hash('md2', $url);

      $attrs = [
        'rel' => 'preload',
        'as' => $type,
        'href' => $url,
        'type' => $mime,
      ];

      $suffix = '';
      if ($srcset = ($item['srcset'] ?? NULL)) {
        $suffix = '_responsive';
        $attrs['imagesrcset'] = $srcset->value();

        if ($sizes = ($item['sizes'] ?? NULL)) {
          $attrs['imagesizes'] = $sizes->value();
        }
      }

      // Checks for external URI.
      if (UrlHelper::isExternal($uri ?: $url)) {
        $attrs['crossorigin'] = TRUE;
      }

      return [
        [
          '#tag' => 'link',
          '#attributes' => $attrs,
        ],
        'blazy' . $suffix . '_' . $type . $key,
      ];
    };

    $links = [];
    $urls = $blazies->get('urls', []);

    // Responsive image with multiple sources.
    if ($sources = $blazies->get('resimage.sources', [])) {
      foreach ($sources as $index => $source) {
        $url = $source['fallback'];

        // Preloading 1px data URI makes no sense, see if image_url exists.
        $data_uri = $url && mb_substr($url, 0, 10) === 'data:image';
        if ($data_uri && ($image_url = $urls[$index] ?? NULL)) {
          $url = $image_url;
        }

        foreach ($source['items'] as $key => $item) {
          if (!empty($item['srcset'])) {
            $links[] = $link($url, NULL, $item);
          }
        }
      }
    }
    else {
      // Regular plain old images.
      foreach ($uris as $key => $uri) {
        // URI might be empty with mixed media, but indices are preserved.
        if ($uri && ($url = $urls[$key] ?? NULL)) {
          $links[] = $link($url, $uri);
        }
      }
    }

    if ($links) {
      foreach ($links as $key => $value) {
        $load['html_head'][$key] = $value;
      }
    }
  }

  /**
   * Extracts uris from file/ media entity, relevant for the new option Preload.
   *
   * Also extract the found image for gallery/ zoom like, ElevateZoomPlus, etc.
   *
   * @todo merge urls here as well once puzzles are solved: URI may be fed by
   * field formatters like this, blazy_filter, or manual call.
   */
  public static function prepare(array &$settings, $items, array $entities = []): array {
    $blazies = $settings['blazies'];
    if ($uris = $blazies->get('uris')) {
      return $uris;
    }

    $style = $blazies->get('image.style');
    $func = function ($item, $entity = NULL) use (&$settings, $blazies, $style) {
      $options = ['entity' => $entity, 'settings' => $settings];

      $image = BlazyImage::item($item, $options);
      $uri = BlazyFile::uri($image);

      // Only needed the first found image, no problem which with mixed media.
      $_uri = $settings['_uri'] ?? '';
      if ($uri && !$blazies->get('first.uri', $_uri)) {
        $settings['_uri'] = $uri;

        $url = BlazyFile::transformRelative($uri, $style);
        $blazies->set('first.image_url', $url)
          ->set('first.item', $image)
          ->set('first.uri', $uri);

        // The first image dimensions to differ from individual item dimensions.
        BlazyImage::dimensions($settings, $image, TRUE);
      }

      return $uri;
    };

    $uris = $urls = [];
    foreach ($items as $key => $item) {
      // Respects empty URI to keep indices intact for correct mixed media.
      $uri = $func($item, $entities[$key] ?? NULL);
      $uris[] = $uri;
      $urls[] = $uri ? BlazyFile::transformRelative($uri, $style) : '';
    }

    $blazies->set('uris', $uris);
    $blazies->set('urls', $urls);

    return $uris;
  }

}
