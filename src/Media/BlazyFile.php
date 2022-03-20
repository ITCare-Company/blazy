<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Site\Settings;
use Drupal\file\FileInterface;
use Drupal\blazy\Blazy;

/**
 * Provides file_BLAH BC for D8 - D10+ till D11 rules.
 *
 * @todo remove deprecated functions post D11, not D10, and when D8 is dropped.
 */
class BlazyFile {

  /**
   * Determines whether the URI has a valid scheme for file API operations.
   *
   * @param string $uri
   *   The URI to be tested.
   *
   * @return bool
   *   TRUE if the URI is valid.
   */
  public static function isValidUri($uri): bool {
    if (!empty($uri) && $manager = Blazy::streamWrapperManager()) {
      return $manager->isValidUri($uri);
    }
    return FALSE;
  }

  /**
   * Creates a relative or absolute web-accessible URL string.
   *
   * @param string $uri
   *   The file uri.
   * @param bool $relative
   *   Whether to return an relative or absolute URL.
   *
   * @return string
   *   Returns an absolute web-accessible URL string.
   */
  public static function createUrl($uri, $relative = FALSE): string {
    if ($gen = Blazy::fileUrlGenerator()) {
      // @todo recheck ::generateAbsoluteString doesn't return web-accessible
      // protocol as expected, required by getimagesize to work correctly.
      return $relative ? $gen->generateString($uri) : $gen->generateAbsoluteString($uri);
    }

    $function = 'file_create_url';
    return is_callable($function) ? $function($uri) : '';
  }

  /**
   * Transforms an absolute URL of a local file to a relative URL.
   *
   * Blazy Filter or OEmbed may pass mixed (external) URI upstream.
   *
   * @param string $uri
   *   The file uri.
   * @param object $style
   *   The optional image style instance.
   * @param array $options
   *   The options: default url, sanitize.
   *
   * @return string
   *   Returns an absolute URL of a local file to a relative ne.
   *
   * @see BlazyOEmbed::getExternalImageItem()
   * @see BlazyFilter::getImageItemFromImageSrc()
   */
  public static function transformRelative($uri, $style = NULL, array $options = []): string {
    $url = $options['url'] ?? '';
    $sanitize = $options['sanitize'] ?? FALSE;

    if (empty($uri)) {
      return $url;
    }

    $data_uri = $url && mb_substr($url, 0, 10) === 'data:image';

    // Returns as is if an external URL: UCG or external OEmbed image URL.
    if (UrlHelper::isExternal($uri)) {
      $url = $uri;
    }
    else {
      // @todo re-check this based on the need.
      if (($data_uri || empty($url) || $style) && self::isValidUri($uri)) {
        $url = $style ? $style->buildUrl($uri) : self::createUrl($uri);

        if ($gen = Blazy::fileUrlGenerator()) {
          $url = $gen->transformRelative($url);
        }
        else {
          $function = 'file_url_transform_relative';
          $url = is_callable($function) ? $function($url) : $url;
        }
      }
    }

    // If transform failed, returns default URL, or URI as is.
    $url = $url ?: $uri;

    // Just in case, an attempted kidding gets in the way, relevant for UGC.
    // @todo re-check to completely remove data URI.
    if ($sanitize && !$data_uri) {
      $url = UrlHelper::stripDangerousProtocols($url);
    }

    return $url ?: '';
  }

  /**
   * Returns URI from the given image URL, relevant for unmanaged/ UGC files.
   *
   * Converts `/sites/default/files/image.jpg` into `public://image.jpg`.
   *
   * @todo re-check if core has this type of conversion.
   */
  public static function buildUri($url): ?string {
    if (!UrlHelper::isExternal($url) && $normal_path = UrlHelper::parse($url)['path']) {
      // If the request has a base path, remove it from the beginning of the
      // normal path as it should not be included in the URI.
      $base_path = \Drupal::request()->getBasePath();
      if ($base_path && mb_strpos($normal_path, $base_path) === 0) {
        $normal_path = str_replace($base_path, '', $normal_path);
      }

      $public_path = Settings::get('file_public_path', 'sites/default/files');

      // Only concerns for the correct URI, not image URL which is already being
      // displayed via SRC attribute. Don't bother language prefixes for IMG.
      if ($public_path && mb_strpos($normal_path, $public_path) !== FALSE) {
        $rel_path = str_replace($public_path, '', $normal_path);
        $uri = Blazy::streamWrapperManager()->normalizeUri($rel_path);

        // @todo re-check why the scheme is gone since 2.9. It was there <= 2.5.
        if (substr($uri, 0, 2) === '//') {
          $uri = 'public:' . $uri;
        }

        return $uri;
      }
    }
    return NULL;
  }

  /**
   * Extracts uris from file/ media entity.
   *
   * @todo merge urls here as well once puzzles are solved: URI may be fed by
   * field formatters like this, blazy_filter, or manual call.
   */
  public static function urisFromField(array &$settings, $items, array $entities = []): array {
    $blazies = $settings['blazies'];
    if ($uris = $blazies->get('uris')) {
      return $uris;
    }

    $style = $blazies->get('image.style');
    $func = function ($item, $entity = NULL) use (&$settings, $style) {
      $blazies = $settings['blazies'];
      $options = ['entity' => $entity, 'settings' => $settings];

      $image = BlazyImage::item($item, $options);
      $uri = self::uri($image);

      // Only needed the first found image, no problem which with mixed media.
      $_uri = $settings['_uri'] ?? '';
      if ($uri && !$blazies->get('first.uri', $_uri)) {
        $settings['_uri'] = $uri;

        $url = self::transformRelative($uri, $style);
        $blazies->set('first.image_url', $url)
          ->set('first.item', $image)
          ->set('first.uri', $uri);
      }

      return $uri;
    };

    $uris = $urls = [];
    foreach ($items as $key => $item) {
      // Respects empty URI to keep indices intact for correct mixed media.
      $uri = $func($item, $entities[$key] ?? NULL);
      $uris[] = $uri;
      $urls[] = $uri ? self::transformRelative($uri, $style) : '';
    }

    $blazies->set('uris', $uris);
    $blazies->set('urls', $urls);

    return $uris;
  }

  /**
   * Returns URI from image item, fake or valid one, no problem.
   *
   * @todo make it more robust to accept few sources.
   */
  public static function uri($item): ?string {
    if ($item) {
      $file = $item->entity ?? NULL;
      return $file instanceof FileInterface ? $file->getFileUri() : ($item->uri ?? '');
    }
    return '';
  }

  /**
   * Returns the file entity from any object, or just settings, if applicable.
   */
  public static function item($object = NULL, array $settings = []): ?object {
    $blazies = $settings['blazies'] ?? NULL;
    $entity = $object;

    // Bail out early if we are given what we want.
    if ($entity instanceof FileInterface) {
      return $entity;
    }

    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $object */
    if ($object instanceof EntityReferenceItem) {
      /** @var \Drupal\file\Entity\File $entity */
      $entity = $object->entity;
    }
    elseif ($object instanceof EntityReferenceFieldItemListInterface) {
      /** @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $object */
      /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $image */
      if ($image = $object->first()) {
        /** @var \Drupal\file\Entity\File $entity */
        $entity = $image->entity;
      }
    }

    // BlazyFilter without any entity/ formatters associated with.
    if (!($entity instanceof FileInterface)) {
      if ($manager = Blazy::service('blazy.manager')) {
        $uri = $settings['uri'] ?? '';
        $uuid = $blazies ? $blazies->get('entity.uuid') : NULL;
        $file = $uuid ? $manager->loadByUuid($uuid, 'file') : NULL;

        if (!$file && self::isValidUri($uri)) {
          if ($files = $manager->loadByProperties(['uri' => $uri], 'file')) {
            $file = reset($files);
          }
        }
        $entity = $file ?: $entity;
      }
    }

    return $entity instanceof FileInterface ? $entity : NULL;
  }

  /**
   * Prepares extension, image styles, lightboxes.
   *
   * Also checks if an extension should not use image style: apng svg gif, etc.
   */
  public static function prepare(array &$settings, $item = NULL): bool {
    $blazies = $settings['blazies'];
    if (!($uri = $blazies->get('uri'))) {
      return FALSE;
    }

    $pathinfo = pathinfo($uri);
    $ext = $pathinfo['extension'] ?? '';
    $supported = $blazies->is('richbox', !empty($settings['_richbox']));
    $richbox = $blazies->get('colorbox') || $blazies->get('mfp') || $supported;

    $extensions = ['svg'];
    if ($unstyles = $blazies->get('ui.unstyled_extensions')) {
      $extensions = array_merge($extensions, array_map('trim', explode(' ', mb_strtolower($unstyles))));
      $extensions = array_unique($extensions);
    }

    // Disable image style if so configured.
    $unstyled = $ext && in_array($ext, $extensions);
    if ($unstyled) {
      $images = ['box', 'box_media', 'image', 'thumbnail', 'responsive_image'];
      foreach ($images as $image) {
        $settings[$image . '_style'] = '';
      }
    }

    // Re-define, if the provided API by-passed, or different/ altered per item.
    $blazies->set('is.external', UrlHelper::isExternal($uri))
      ->set('is.richbox', $richbox)
      ->set('is.unstyled', $unstyled)
      ->set('media.extension', $ext);

    return $unstyled;
  }

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

    // Supports multiple sources.
    if ($sources = $blazies->get('resimage.sources', [])) {
      foreach ($sources as $source) {
        $url = $source['fallback'];

        // Preloading 1px data URI makes no sense, see if image_url exists.
        $data_uri = $url && mb_substr($url, 0, 10) === 'data:image';
        $image_url = $blazies->get('image.url', $settings['image_url'] ?? '');
        $image_url = $image_url ?: $blazies->get('first.url');
        if ($data_uri && $image_url) {
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
      $urls = $blazies->get('urls', []);
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
   * Prepares CSS background image.
   *
   * @todo remove and merge it with BlazyImage::urlAndStyle().
   */
  public static function backgroundImage(array $settings, $style = NULL) {
    return BlazyImage::background($settings, $style);
  }

}
