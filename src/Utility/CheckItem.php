<?php

namespace Drupal\blazy\Utility;

use Drupal\Component\Utility\UrlHelper;
use Drupal\blazy\Media\BlazyFile;

/**
 * Provides feature check methods at item level, or individually.
 *
 * @todo remove most $settings once migrated and after sub-modules and tests.
 */
class CheckItem {

  /**
   * Checks for essential settings: URI, delta, cache and initial delta.
   *
   * The initial delta related to option `Loading: slider`, the initial is not
   * lazyloaded, the rest are. Sometimes the initial delta is not always 0 as
   * normally seen at slider option name: `initial slide` or `start`.
   *
   * Image URI might be NULL given rich media like Facebook, etc., no problem.
   * That is why this is called twice. Once to check, another to re-check.
   */
  public static function essentials(array &$settings, $item = NULL, $delta = -1): void {
    $blazies = $settings['blazies'];
    $delta   = $settings['delta'] ?? $blazies->get('delta', $delta);
    $initial = $delta == $blazies->get('initial', -2);
    $uri     = BlazyFile::uri($item, $settings);

    // This means re-definition since URI can be fed from any sources uptream.
    $blazies->set('delta', $delta)
      ->set('is.initial', $initial)
      ->set('uri', $uri);

    // File cache tags.
    if ($item && ($file = ($item->entity ?? NULL))) {
      $tags = $file->getCacheTags();
      $blazies->set('cache.file.tags', $tags);
    }

    // @todo remove after sub-modules.
    $settings['delta'] = $delta;
    $settings['uri'] = $uri;
  }

  /**
   * Checks for multimedia settings, per item to address mixed media.
   *
   * @requires self::essentials()
   *
   * Bundles should not be coupled with embed_url to allow various bundles
   * and use media.source to be more precise instead.
   *
   * @todo remove $type, a legacy VEF period, which knew no bundles, or sources.
   */
  public static function multimedia(array &$settings): void {
    $blazies   = $settings['blazies'];
    $source    = $blazies->get('media.source');
    $type      = $settings['type'] ?? 'image';
    $type      = $settings['type'] = $blazies->get('media.type') ?: $type;
    $bundle    = $blazies->get('media.bundle', $settings['bundle'] ?? '');
    $embed_url = $blazies->get('media.embed_url', $settings['embed_url'] ?? '');
    $videos    = ['oembed:video', 'video_embed_field'];
    $medias    = array_merge(['audio_file', 'video_file'], $videos);
    $is_video  = $source && in_array($source, $videos);
    $is_media  = $bundle && in_array($source, $medias);
    $is_remote = $bundle == 'remote_video' || $type == 'video';
    $is_remote = $embed_url && ($is_video || $is_remote);
    $switch    = $settings['media_switch'] ?? NULL;
    $switch    = $switch ?: $blazies->get('switch');
    $is_iframe = $is_remote && $switch == '';
    $is_player = $is_remote && $switch == 'media';

    // Also addresses mixed media unique per item, also for convenient.
    $blazies->set('is.iframe', $is_iframe)
      ->set('is.multimedia', $is_media)
      ->set('is.player', $is_player)
      ->set('is.remote', $is_remote)
      ->set('is.video', $is_remote)
      ->set('media.type', $type)
      ->set('switch', $switch);
  }

  /**
   * Checks if an extension should not use image style: apng svg gif, etc.
   *
   * @requires self::essentials(), self::multimedia()
   */
  public static function unstyled(array &$settings, $item = NULL) {
    $blazies = $settings['blazies'];
    if (!($uri = $blazies->get('uri'))) {
      return;
    }

    $pathinfo = pathinfo($uri);
    $ext = $pathinfo['extension'] ?? '';
    $extensions = ['svg'];

    // Extensions without image styles: animated GIF, APNG, SVG, etc.
    if ($unstyles = $blazies->get('ui.unstyled_extensions')) {
      $extensions = array_merge($extensions,
      array_map('trim', explode(' ', mb_strtolower($unstyles))));
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
      ->set('is.unstyled', $unstyled)
      ->set('image.extension', $ext);
  }

  /**
   * Checks lazy insanity given various features/ media types + loading option.
   *
   * @requires self::multimedia()
   *
   * Some duplicate rules are to address non-blazy formatters like embedded
   * Image formatter within Blazy ecosystem, but not using Blazy formatter, etc.
   * The lazy insanity:
   * - Respects `No Javascript: lazy` aka decoupled lazy loader.
   * - Respects `Loading priority` to avoid anti-pattern.
   * - Respects `Loading: slider`, the initial is not lazyloaded, the rest are.
   * - Respects sub-module lazy attributes and methods:
   *   - Splide: nearby and sequential.
   *   - Slick: anticipated, ondemand and progressive.
   *   Unless they are incapable of dealing with: iframe, BG, Picture, BG, etc.
   *
   * @todo needs a recap to move some container-level here if they must live at
   * individual level, such as non-blazy Image formatter within Blazy ecosystem.
   */
  public static function insanity(array &$settings): void {
    $blazies    = $settings['blazies'];
    $ratio      = $settings['ratio'] ?? '';
    $unlazy     = $blazies->is('slider') && $blazies->is('initial');
    $unlazy     = $unlazy ? TRUE : $blazies->is('unlazy');
    $use_loader = $settings['use_loading'] ?? $blazies->get('use.loader');
    $use_loader = $unlazy ? FALSE : $use_loader;
    $is_unblur  = $blazies->is('sandboxed')
      || $blazies->is('unstyled') || $blazies->is('iframe');
    $is_blazy   = $blazies->get('lazy.id') == 'blazy' && $blazies->is('blazy');
    $is_blur    = $blazies->is('blur') && $is_blazy && !$is_unblur;

    // Supports core Image formatter embedded within Blazy ecosystem.
    $is_fluid = $blazies->is('fluid') ?: $ratio == 'fluid';

    // @todo better logic to support loader as required, must decouple loader.
    // @todo $lazy = $settings['loading'] == 'lazy';
    // @todo $lazy = $blazies->is('blazy') && ($blazies->get('libs.compat') || $lazy);
    // Redefines some since this can be fed by anyone, including custom works.
    $blazies->set('is.fluid', $is_fluid)
      ->set('is.blur', $is_blur)
      ->set('is.unlazy', $unlazy)
      ->set('use.loader', $use_loader)
      ->set('was.prepare', TRUE);

    // Overrides sub-modules which know not iframe, Picture, Video, BG, Blur.
    if ($is_blazy || $is_blur) {
      $blazies->set('lazy.attribute', 'src')
        ->set('lazy.class', 'b-lazy')
        ->set('lazy.id', 'blazy')
        ->set('is.blazy', TRUE);
    }
  }

}
