<?php

namespace Drupal\blazy\Media;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\media\MediaInterface;
use Drupal\blazy\Blazy;
use Drupal\blazy\Theme\BlazyAttribute;

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
  public static function build($media, array &$settings = []): array {
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
  public static function extract(MediaInterface $media, $view_mode = NULL): array {
    $source = $media->getSource();
    $definition = $source->getPluginDefinition();
    $uri = '';

    // @todo recheck and replace if any direct method for URI.
    if ($attr = ($definition['thumbnail_uri_metadata_attribute'] ?? '')) {
      $uri = $source->getMetadata($media, $attr);
    }

    return [
      'bundle'       => $media->bundle(),
      'id'           => $media->id(),
      'label'        => $media->label(),
      'source'       => $source->getPluginId(),
      'source_field' => $source->getConfiguration()['source_field'],
      'thumbnail'    => $uri,
      'url'          => $media->isNew() ? '' : $media->toUrl()->toString(),
      'view_mode'    => $view_mode ?: 'default',
    ];
  }

  /**
   * Prepares media item data to provide image item.
   */
  public static function prepare(array &$data, MediaInterface $media) {
    $settings  = $data['settings'];
    $blazies   = $settings['blazies'];
    $view_mode = $settings['view_mode'] ?? NULL;
    $langcode  = $blazies->get('language.current');

    // Provides translated $media, if any.
    $media = Blazy::translated($media, $langcode);

    // Provides settings.
    $info      = self::extract($media, $view_mode);
    $locals    = ['audio_file', 'video_file'];
    $videos    = ['oembed:video', 'video_embed_field'];
    $source    = $info['source'];
    $bundle    = $info['bundle'];
    $medias    = array_merge($locals, $videos);
    $is_local  = $source && in_array($source, $locals);
    $is_media  = $source && in_array($source, $medias);
    $is_remote = $source && in_array($source, $videos) || $bundle == 'remote_video';

    // Embed url is not defined here, yet, provides basic media checks.
    $blazies->set('media', $info, TRUE)
      ->set('is.multimedia', $is_media)
      ->set('is.local_media', $is_local)
      ->set('is.local_video', $source == 'video_file')
      ->set('is.remote_video', $is_remote);
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
    $item     = $field[0];
    $settings = &$field['#settings'];
    $blazies  = $settings['blazies'];
    $iframe   = ($item['#tag'] ?? NULL) == 'iframe';

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
    // Iframes: Googledocs, SlideShare. Hardcoded: Soundcloud, Spotify.
    // @todo recheck, likely everyone hardly uses iframes lately.
    if ($iframe && $src = ($attributes['src'] ?? FALSE)) {
      $blazies->set('media.embed_url', $src);
      $attributes = Blazy::merge(BlazyAttribute::iframe($settings), $attributes);
    }
    // Media with local files: video.
    elseif (isset($item['#files'])
      && $file = ($item['#files'][0]['file'] ?? NULL)) {
      // @todo multiple sources, not crucial for now.
      // This is not an image URI, but file video URI.
      // The poster or file image is set via settings.image option instead.
      $blazies->set('media.uri', $file->getFileUri());

      self::videoItem($item, $settings);
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
   * Modifies item attributes for local video item.
   */
  private static function videoItem(array &$item, array $settings): void {
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

  /**
   * Extracts image from non-media entities for the main background/ stage.
   *
   * @todo remove after sub-modules anytime by 3.x.
   */
  public static function imageItem(array &$data, $entity): void {
    $settings = &$data['settings'];
    if ($stage = ($settings['image'] ?? FALSE)) {
      BlazyImage::fromField($data, $entity, $stage);
    }
  }

}
