<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\media\MediaInterface;
use Drupal\blazy\Blazy;
use Drupal\blazy\Utility\CheckItem;

/**
 * Provides extra utilities to work with core Media.
 *
 * This class makes it possible to have a mixed display of all media entities,
 * useful for Blazy Grid, Slick Carousel, GridStack contents as mixed media.
 * This approach is alternative to regular preprocess overrides, still saner
 * than iterating over unknown like template_preprocess_media_BLAH, etc.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Media integration is being reworked.
 *
 * @todo rework this for core Media, and refine for theme_blazy(). Two big TODOs
 * for the next releases is to replace ImageItem references into just $settings,
 * and convert this into non-static to move most BlazyOEmbed stuffs here.
 * Not urgent, the important is to make it just work with minimal regressions.
 * @todo recap similiraties and make them plugins.
 */
class BlazyMedia {

  /**
   * Builds the media field which is not understood by theme_blazy().
   *
   * @param object $media
   *   The media being rendered.
   * @param array $settings
   *   The contextual settings array.
   *
   * @return array
   *   The renderable array of the media field, or empty if not applicable.
   */
  public static function build($media, array &$settings): array {
    Blazy::verify($settings);
    $blazies = $settings['blazies'];

    // Prevents fatal error with disconnected internet when having ME Facebook,
    // ME SlideShare, resorted to static thumbnails to avoid broken displays.
    if ($input = $blazies->get('media.input_url')) {
      try {
        \Drupal::httpClient()->get($input, ['timeout' => 3]);
      }
      catch (\Exception $e) {
        return [];
      }
    }

    // Local video, FB, Twitter, etc. is rich to be simple due to terracota,
    // can be refined later when Blazy supports more media types better.
    $blazies->set('media.type', 'rich');

    $view_mode = $blazies->get('media.view_mode') ?: $settings['view_mode'] ?? 'default';
    $source_field = $blazies->get('media.source_field');
    $options = $blazies->is('local_video') ? ['type' => 'file_video'] : $view_mode;

    $build = $media->get($source_field)->view($options);
    $build['#settings'] = $settings;

    return isset($build[0]) ? self::unfield($build) : $build;
  }

  /**
   * Extracts needed info from a media.
   */
  public static function extract(MediaInterface $media, $view_mode = NULL, $langcode = NULL): array {
    $source = $media->getSource();
    $definition = $source->getPluginDefinition();
    $source_id = $source->getPluginId();
    $uri = '';

    try {
      // GuzzleHttp\Exception\ConnectException: cURL error 6:
      // Could not resolve host: soundcloud.com.
      // @todo recheck and replace if any direct method for URI.
      if ($attr = ($definition['thumbnail_uri_metadata_attribute'] ?? '')) {
        $uri = $source->getMetadata($media, $attr);
      }
    }
    catch (\Exception $e) {
      // No need to be harsh here, likely disconnected internet, we can always
      // display stored thumbnails, if already.
    }

    // Extracts common entity properties.
    $info = CheckItem::entity($media, $langcode);

    // Extracts specific values for this media entity.
    $output = [
      'source'       => $source_id,
      'source_field' => $source->getConfiguration()['source_field'],
      'thumbnail'    => $uri,
      'view_mode'    => $view_mode ?: 'default',
    ] + $info['data'];

    return ['data' => $output, 'entity' => $info['entity']];
  }

  /**
   * Prepares media item data to provide image item.
   */
  public static function prepare(array &$data) {
    $media     = $data['#entity'];
    $settings  = &$data['settings'];
    $blazies   = $settings['blazies'];
    $view_mode = $settings['view_mode'] ?? NULL;
    $langcode  = $blazies->get('language.current');
    $result    = self::extract($media, $view_mode, $langcode);
    $media     = $result['entity'] ?? NULL;
    $info      = $result['data'] ?? [];
    $id        = $info['id'] ?? NULL;
    $rid       = $info['rid'] ?? NULL;
    $locals    = ['audio_file', 'video_file'];
    $videos    = ['oembed:video', 'video_embed_field'];
    $source    = $info['source'];
    $medias    = array_merge($locals, $videos);
    $is_local  = $source && in_array($source, $locals);
    $is_media  = $source && in_array($source, $medias);
    $is_remote = $source && in_array($source, $videos);

    // Embed url is not defined here, yet, provides basic media checks.
    $contexts = Cache::mergeContexts(['languages', 'url.site'], $media->getCacheContexts());
    $blazies->set('media', $info)
      ->set('media.instance', $media)
      ->set('cache.metadata.contexts', $contexts, TRUE)
      ->set('cache.metadata.keys', [$id, $rid], TRUE)
      ->set('cache.metadata.max-age', $media->getCacheMaxAge())
      ->set('cache.metadata.tags', $media->getCacheTags(), TRUE)
      ->set('is.playable', $is_remote)
      ->set('is.multimedia', $is_media)
      ->set('is.local_media', $is_local)
      ->set('is.local_video', $source == 'video_file')
      ->set('is.remote_video', $is_remote)
      ->set('is.remote_unknown', !$is_media);

    return $media;
  }

  /**
   * Returns a media entity from a field, if any.
   */
  public static function fromField($entity, $stage): ?object {
    $media = NULL;
    if (isset($entity->{$stage})
      && $reference = $entity->get($stage)->first()) {
      if ($reference instanceof EntityReferenceItem) {
        $media = $reference->entity;
      }
    }
    return $media instanceof MediaInterface ? $media : NULL;
  }

  /**
   * Returns a field item/ content to be wrapped by theme_blazy().
   *
   * @param array $field
   *   The source renderable array to remove field markups from for DOM diet.
   *
   * @return array
   *   The array of the media item to be wrapped directly by theme_blazy().
   */
  private static function unfield(array $field): array {
    $item      = $field[0];
    $settings  = &$field['#settings'];
    $blazies   = $settings['blazies'];
    $is_iframe = ($item['#tag'] ?? NULL) == 'iframe';

    if (!isset($item['#attributes'])) {
      $item['#attributes'] = [];
    }

    $attributes = &$item['#attributes'];

    // Update iframe/video dimensions based on configurable image style, if any.
    foreach (['width', 'height'] as $key) {
      $default = $settings[$key] ?? NULL;
      if ($dimension = ($blazies->get('image.' . $key) ?: $default)) {
        $attributes[$key] = $dimension;
      }
    }

    // Converts iframes into lazyloaded ones.
    // Iframes: Googledocs, SlideShare. Hardcoded: Spotify.
    // @todo recheck, likely everyone hardly uses iframes lately.
    // No longer per D9.5: Soundcloud.
    if ($is_iframe && $src = ($attributes['src'] ?? FALSE)) {
      self::toPlayable($blazies, $src);
    }
    // Media with local files: video.
    elseif (isset($item['#files'])
      && $file = ($item['#files'][0]['file'] ?? NULL)) {
      // @todo multiple sources, not crucial for now.
      // This is not an image URI, but file video URI.
      // The poster or file image is set via settings.image option instead.
      $blazies->set('media.uri', $file->getFileUri());

      self::toVideo($item, $settings);
    }
    elseif (isset($item['#theme'])) {
      self::toIframe($item, $settings);
    }
    else {
      self::disableFeatures($settings);
    }

    // Clone relevant keys since field wrapper is no longer in use.
    foreach (['attached', 'cache', 'third_party_settings'] as $key) {
      if ($data = $field["#$key"] ?? []) {
        $item["#$key"] = Blazy::merge($data, $item, "#$key");
      }
    }
    // Keep original formatter configurations intact here for custom works.
    // Non-accessible at file_video preprocess, but required by theme_blazy().
    $item['#settings'] = Blazy::settings($settings);

    return $item;
  }

  /**
   * Disable fancy features with the unknown land.
   */
  private static function disableFeatures(array &$settings): void {
    $blazies = $settings['blazies'];
    $settings['media_switch'] = '';
    $blazies->set('switch', '')
      ->set('is.lightbox', FALSE);
  }

  /**
   * Modifies item attributes for iframes if any.
   */
  private static function toIframe(array &$item, array &$settings): void {
    $blazies = $settings['blazies'];
    if ($oembed = Blazy::service('blazy.oembed')) {
      $original = $item;
      $content = $oembed->blazyManager()->renderer()->renderPlain($item);
      $dom = Html::load($content);
      $iframes = $dom->getElementsByTagName('iframe');

      if ($iframes->length > 0 && $iframe = $iframes->item(0)) {
        if ($src = $iframe->getAttribute('src')) {
          self::toPlayable($blazies, $src);

          if (strpos($src, '?url=') === FALSE) {
            $embed_url = $oembed->toEmbedUrl($blazies, $src);
            $blazies->set('media.embed_url', $embed_url);
          }

          $blazies->set('media.type', $blazies->get('media.source'));
        }
      }
      else {
        self::disableFeatures($settings);
      }

      $item = $original;
    }
  }

  /**
   * Modifies settings to support iframes.
   */
  private static function toPlayable($blazies, $src): void {
    $blazies->set('is.iframeable', TRUE)
      ->set('is.playable', TRUE)
      ->set('is.multimedia', TRUE)
      ->set('media.embed_url', $src)
      ->set('media.escaped', TRUE);
  }

  /**
   * Modifies item attributes for local video item.
   */
  private static function toVideo(array &$item, array $settings): void {
    // Do this as $item['#settings'] is not available as file_video variables.
    // @todo re-check, most likely just a single file here.
    foreach ($item['#files'] as &$files) {
      $files['#blazy'] = Blazy::settings($settings);
    }

    $item['#attributes']->setAttribute('data-b-lazy', TRUE);
    if ($blazies = ($settings['blazies'] ?? NULL)) {
      // Disable [data-src] lazy if undata, or richbox is supported.
      if ($blazies->is('undata') || $blazies->is('richbox')) {
        $item['#attributes']->setAttribute('data-b-undata', TRUE);
      }
    }
  }

}
