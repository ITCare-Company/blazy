<?php

namespace Drupal\blazy\Utility;

use Drupal\blazy\Blazy;
use Drupal\blazy\internals\Internals;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Theme\Attributes;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides feature check methods at item level.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Please use the public method instead.
 *
 * @todo remove most $settings once migrated and after sub-modules and tests.
 */
class CheckItem {

  /**
   * Provides autoplay URL for lightbox nested iframes to save another click.
   */
  public static function autoplay($url, $check = TRUE): string {
    $func = function ($str, $key) {
      $format1 = '%s&%s=1';
      $first = sprintf($format1, $str, $key);
      $format2 = '%s?%s=1';
      $last = sprintf($format2, $str, $key);

      return self::has($str, '?') ? $first : $last;
    };

    // It doesn't cover all providers, but few, no biggies till needed.
    if (!self::has($url, 'autoplay')
      || self::has($url, 'autoplay=0')) {
      $key = self::has($url, 'soundcloud') ? 'auto_play' : 'autoplay';
      return $func($url, $key);
    }

    // @todo recheck if any side effect/ double escape to cdn/ valid input.
    return $check ? UrlHelper::stripDangerousProtocols($url) : $url;
  }

  /**
   * Returns entity data.
   */
  public static function entity($entity, $langcode): array {
    if (!$entity instanceof EntityInterface) {
      return [];
    }

    $internal_path = $absolute_path = NULL;
    // Deals with UndefinedLinkTemplateException such as paragraphs type.
    // @see #2596385, or fetch the host entity.
    if (!$entity->isNew()) {
      try {
        // Provides translated $entity, if any.
        $entity = Blazy::translated($entity, $langcode);
        $url = $entity->toUrl();

        // $media->toUrl()->toString()
        $internal_path = $url->getInternalPath();
        $absolute_path = $url->setAbsolute()->toString();
      }
      catch (\Exception $ignore) {
        // Do nothing.
      }
    }

    // Only eat what we can chew.
    $data = [
      'bundle'  => $entity->bundle(),
      'id'      => $entity->id(),
      'label'   => $entity->label(),
      'path'    => $internal_path,
      'rid'     => $entity->getRevisionID(),
      'type_id' => $entity->getEntityTypeId(),
      'url'     => $absolute_path,
    ];

    return ['data' => $data, 'entity' => $entity];
  }

  /**
   * Checks for essential settings: URI, delta and initial delta.
   *
   * The initial delta related to option `Loading: slider`, the initial is not
   * lazyloaded, the rest are. Sometimes the initial delta is not always 0 as
   * normally seen at slider option name: `initialSlide` or `start`.
   *
   * Image URI might be NULL given rich media like Facebook, etc., no problem.
   * That is why this is called twice. Once to check, another to re-check.
   */
  public static function essentials(array &$settings, $item, $called = FALSE): void {
    // Define the multimedia, needed for media ALT and TITLE checks below.
    // Also VEF will convert its video_embed_field into a fake image item here.
    self::multimedia($settings);

    // Must be here for tests to pass file cache checks.
    // File cache tags cannot be read by tests from #pre_render.
    // Accounts for VEF conversion from video_embed_field into faked image item.
    $blazies = $settings['blazies'];
    $item    = $blazies->get('image.item', $item);

    if ($item && $file = ($item->entity ?? NULL)) {
      $tags = $file->getCacheTags();
      $blazies->set('cache.metadata.tags', $tags, TRUE);

      // Trusted here is more to separate unknown from known sources of URIs.
      if (!$blazies->get('image.trusted')) {
        $blazies->set('image.trusted', BlazyImage::isImage($item));
      }
    }

    // Bail out late if already called/ processed.
    // @fixme tests/src/Kernel/BlazyFormatterTest.php:88.
    if ($called) {
      return;
    }

    // The first is for 2.6+ approach. The last to account for custom works
    // with old approach/ or direct call to theme_blazy() via settings.uri.
    // This issue do not happen at D7, since it consistently uses API.
    $uri     = $blazies->get('image.uri') ?: BlazyFile::uri($item, $settings);
    $delta   = $blazies->get('delta') ?: ($settings['delta'] ?? 0);
    $initial = $delta == $blazies->get('initial', -1);

    // This means re-definition since URI can be fed from any sources uptream.
    // URI might be NULL when no associated image to work with, no problem.
    $blazies->set('delta', $delta)
      ->set('is.initial', $initial)
      ->set('was.essentials', TRUE);

    // Checks images which cannot have image styles without extra legs.
    if ($uri) {
      $blazies->set('image.uri', $uri)
        ->set('image.valid', BlazyFile::isValidUri($uri));

      self::unstyled($settings, $uri);
    }

    // Must be placed after self::multimedia() to get different ALT/ TITLE.
    // And after image extensions setup to check for ugly filename image title.
    Attributes::altTitle($blazies, $item);
  }

  /**
   * A simple wrapper for stripos().
   */
  public static function has($content, $needle) {
    if ($content && $needle = trim($needle ?: '')) {
      // stripos() won't work with diacritical signs.
      $needle = strtolower($needle);
      return strpos($content, $needle) !== FALSE;
    }
    return FALSE;
  }

  /**
   * Checks lazy insanity given various features/ media types + loading option.
   *
   * @requires self::multimedia()
   *
   * Some duplicate rules are to address non-blazy formatters like embedded
   * Image formatter within Blazy ecosystem, but not using Blazy formatter, etc.
   * The lazy insanity:
   * - Respects `No JavaScript: lazy` aka decoupled lazy loader.
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
    $use_loader = $blazies->use('loader') ?: $settings['use_loading'] ?? FALSE;
    $use_loader = $unlazy ? FALSE : $use_loader;
    $is_unblur  = $blazies->is('sandboxed')
      || $blazies->is('unstyled') || $blazies->is('iframe');
    $is_blazy   = $blazies->get('lazy.id') == 'blazy' && $blazies->is('blazy');
    $is_blur    = !$is_unblur && ($blazies->is('blur') && $is_blazy);

    // Supports core Image formatter embedded within Blazy ecosystem.
    $is_fluid = $blazies->is('fluid') ?: $ratio == 'fluid';

    // @todo better logic to support loader as required, must decouple loader.
    // @todo $lazy = $blazies->get('image.loading') == 'lazy';
    // @todo $lazy = $blazies->is('blazy') && ($blazies->get('libs.compat') || $lazy);
    // Redefines some since this can be fed by anyone, including custom works.
    $blazies->set('is.fluid', $is_fluid)
      ->set('is.blur', $is_blur)
      ->set('is.unlazy', $unlazy)
      ->set('use.loader', $use_loader)
      ->set('was.prepare', TRUE);

    // Also disable blur effect attributes.
    if (!$is_blur && $blazies->get('fx') == 'blur') {
      $blazies->set('fx', NULL);
    }

    // Overrides sub-modules which know not iframe, Picture, Video, BG, Blur.
    if ($is_blazy || $is_blur) {
      $blazies->set('lazy.attribute', 'src')
        ->set('lazy.class', 'b-lazy')
        ->set('lazy.id', 'blazy')
        ->set('is.blazy', TRUE);
    }
  }

  /**
   * Disable image style if so configured.
   *
   * Extensions without image styles: SVG, etc.
   * APNG, animated GIF are reasonable for thumbnails conversions, though.
   *
   * @requires CheckItem::essentials()
   */
  public static function unstyled(array &$settings, $uri): bool {
    $blazies    = $settings['blazies'];
    $ext        = pathinfo($uri, PATHINFO_EXTENSION) ?: 'x';
    $ext        = strtolower($ext);
    $external   = UrlHelper::isExternal($uri);
    $extensions = ['svg'];
    $data_uri   = $blazies->is('data_uri', Blazy::isDataUri($uri));

    // If we have added extensions.
    if ($unstyles = $blazies->ui('unstyled_extensions')) {
      $checks = array_map('trim', explode(' ', strtolower($unstyles)));
      $checks = array_merge($checks, $extensions);
      $extensions = array_unique($checks);
    }

    $unstyled = $ext && in_array($ext, $extensions);
    if (!$unstyled) {
      // @todo recheck if anything against this at all.
      $unstyled = $external || $data_uri;
    }

    // Re-define, if the provided API by-passed, or different/ altered per item.
    $blazies->set('is.external', $external)
      ->set('is.svg', $ext == 'svg')
      ->set('is.unstyled', $unstyled)
      ->set('is.data_uri', $data_uri)
      ->set('image.extension', $ext)
      ->set('was.unstyled', TRUE);

    return $unstyled;
  }

  /**
   * Determines which lazyload to use for Slick and Splide.
   *
   * Moved it here to avoid similar issues like `is_preview` complication,
   * and other improvements: `Loading` priority, `No JavaScript: lazy`, etc.
   *
   * @todo refine this based on the new options.
   * @todo remove non configurable settings after sub-modules.
   */
  public static function which(array &$settings, $lazy, $class, $attribute): void {
    // Don't bother if empty.
    if (empty($lazy)) {
      return;
    }

    $blazies = Internals::verify($settings);

    // Bail out if lazy load is disabled, or in sandbox mode.
    if ($blazies->is('nojs') || $blazies->is('sandboxed')) {
      // @todo remove $settings after slick:2.10.
      $settings['lazy'] = $lazy;
      return;
    }

    // Slick only knows plain old image.
    // Splide does know plain (Responsive) image, but not Picture.
    // Blazy knows more: BG, local video, remote video or iframe, (Responsive
    // |Picture) image.
    // Must be re-defined at item level to respect mixed media.
    // @todo local video, iframe, etc. are not covered at container level.
    $use_blazy = $lazy == 'blazy'
      || !empty($settings['blazy'])
      || !empty($settings['responsive_image_style'])
      || $blazies->is('bg', !empty($settings['background']))
      || $blazies->is('blazy')
      || $blazies->is('blur');

    // Allows Blazy to take over for advanced features above.
    $lazy = $use_blazy ? 'blazy' : $lazy;

    // Still a check in case the above does not cover, like video, iframe, etc.
    if ($use_blazy) {
      $blazies->set('is.blazy', TRUE);
    }
    else {
      $blazies->set('lazy.attribute', $attribute)
        ->set('lazy.class', $class);
    }

    // @todo remove $settings.
    $settings['blazy'] = $use_blazy;
    $settings['lazy'] = $lazy;

    $blazies->set('lazy.id', $lazy);
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
   * @todo recheck BlazyFilter multimedia after moving some into BlazyMedia.
   * @todo remove $settings['type'], only after BVEF synced/ updated, or at 3.x.
   */
  private static function multimedia(array &$settings): void {
    $blazies   = $settings['blazies'];
    $switch    = $settings['media_switch'] ?? NULL;
    $switch    = $blazies->get('switch', $switch);
    $type      = $blazies->get('media.type') ?: $settings['type'] ?? 'image';
    $embed_url = $settings['embed_url'] ?? '';
    $embed_url = $blazies->get('media.embed_url') ?: $embed_url;
    $is_vef    = $type == 'video';
    $is_remote = $embed_url && ($blazies->is('remote_video') || $is_vef);
    $is_iframe = $is_remote && empty($switch);
    $is_player = $is_remote && $switch == 'media';

    // Only video has poster, audio can only have a multi content.
    if ($blazies->is('local_audio') && !empty($settings['image'])) {
      $blazies->set('is.multicontent', TRUE);
    }

    // BVEF compat without core OEmbed security feature.
    if ($embed_url && strpos($embed_url, 'media/oembed') === FALSE) {
      $type = 'video';
      if ($oembed = Internals::service('blazy.oembed')) {
        // VEF has no TITLE, nor ALT, for images, provide them.
        $oembed->getThumbnail($settings);
      }

      // If you bang your head around why suddenly Instagram failed, this is it.
      // Only relevant for VEF, not core, if $oembed::toEmbedUrl() is by-passed:
      if (strpos($embed_url, '//instagram') !== FALSE) {
        $embed_url = str_replace('//instagram', '//www.instagram', $embed_url);
      }

      if ($is_player) {
        $embed_url = Blazy::autoplay($embed_url);
      }

      // @todo remove once BVEF adopted Blazy:2.17+ BlazyVideoFormatter.
      // The media is defined for core Media, not VEF, so set it here.
      $bundle = $blazies->get('media.bundle', 'remote_video');
      $input = $blazies->get('media.input_url', $settings['input_url'] ?? NULL);
      $blazies->set('media.input_url', $input)
        ->set('media.bundle', $bundle)
        ->set('media.source', 'video_embed_field');

    }

    // Addresses mixed media unique per item, aside from convenience.
    // Also compat with BVEF till they are updated to adopt 2.10 changes.
    $multimedia = $blazies->is('multimedia', $is_remote);
    $blazies->set('is.iframe', $is_iframe)
      ->set('is.multimedia', $multimedia || $blazies->is('playable'))
      ->set('is.player', $is_player)
      ->set('is.remote_video', $is_remote)
      ->set('media.embed_url', $embed_url)
      ->set('media.type', $type);
  }

}
