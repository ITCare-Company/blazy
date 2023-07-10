<?php

namespace Drupal\blazy\Utility;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityInterface;
use Drupal\blazy\Blazy;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;

/**
 * Provides feature check methods at item level.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 *
 * @todo remove most $settings once migrated and after sub-modules and tests.
 */
class CheckItem {

  /**
   * Returns a message if access to view the entity is denied.
   */
  public static function denied($entity): array {
    if (!$entity instanceof EntityInterface) {
      return [];
    }

    if (!$entity->access('view')) {
      $parameters = [
        '@label' => $entity->getEntityType()->getSingularLabel(),
        '@id' => $entity->id(),
        '@langcode' => $entity->language()->getId(),
        '@title' => $entity->label(),
      ];
      $restricted_access_label = $entity->access('view label')
       ? new FormattableMarkup('@label @id (@title)', $parameters)
       : new FormattableMarkup('@label @id', $parameters);
      return ['#markup' => $restricted_access_label];
    }
    return [];
  }

  /**
   * Returns a message if access to view the entity is denied.
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
      'bundle' => $entity->bundle(),
      'id' => $entity->id(),
      'label' => $entity->label(),
      'path' => $internal_path,
      'rid' => $entity->getRevisionID(),
      'type_id' => $entity->getEntityTypeId(),
      'url' => $absolute_path,
    ];

    return ['data' => $data, 'entity' => $entity];
  }

  /**
   * Checks for essential settings: URI, delta and initial delta.
   *
   * The initial delta related to option `Loading: slider`, the initial is not
   * lazyloaded, the rest are. Sometimes the initial delta is not always 0 as
   * normally seen at slider option name: `initial slide` or `start`.
   *
   * Image URI might be NULL given rich media like Facebook, etc., no problem.
   * That is why this is called twice. Once to check, another to re-check.
   */
  public static function essentials(array &$settings, $item = NULL): void {
    $blazies = $settings['blazies'];

    // Bail out early if already processed.
    if ($blazies->was('essentials')) {
      return;
    }

    // The first is for 2.6+ approach. The last to account for custom works
    // with old approach/ or direct call to theme_blazy() via settings.uri.
    // This issue do not happen at D7, since it consistently uses API.
    $uri     = $blazies->get('image.uri') ?: BlazyFile::uri($item, $settings);
    $delta   = $blazies->get('delta') ?: ($settings['delta'] ?? 0);
    $initial = $delta == $blazies->get('initial', -1);

    // Must be here:
    if ($item) {
      // File cache tags, cannot be read by tests from #pre_render.
      if ($file = ($item->entity ?? NULL)) {
        $tags = $file->getCacheTags();
        $blazies->set('cache.metadata.tags', $tags, TRUE);
      }

      // Need by thumbnails if any image item, fake or real, no biggies.
      // Extracts alt from $item.
      $alt = empty($item->alt) ? "" : trim($item->alt);
      $blazies->set('image.alt', $alt);

      // Do not output an empty 'title' attribute.
      if (isset($item->title) && (mb_strlen($item->title) != 0)) {
        $blazies->set('image.title', trim($item->title));
      }
    }

    // This means re-definition since URI can be fed from any sources uptream.
    $blazies->set('delta', $delta)
      ->set('is.initial', $initial)
      ->set('image.uri', $uri)
      ->set('was.essentials', TRUE);

    // Checks images which cannot have image styles without extra legs.
    if ($uri) {
      BlazyImage::isUnstyled($settings, $uri, TRUE);
    }

    // @todo remove after sub-modules.
    // $settings['delta'] = $delta;
    // $settings['uri'] = $uri;
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
   */
  public static function multimedia(array &$settings): void {
    $blazies   = $settings['blazies'];
    $switch    = $settings['media_switch'] ?? NULL;
    $switch    = $switch ?: $blazies->get('switch');
    $type      = $blazies->get('media.type') ?: $settings['type'] ?? 'image';
    $embed_url = $settings['embed_url'] ?? '';
    $embed_url = $blazies->get('media.embed_url') ?: $embed_url;
    $is_vef    = $type == 'video' || $blazies->is('playable');
    $is_remote = $embed_url && ($blazies->is('remote_video') || $is_vef);
    $is_iframe = $is_remote && empty($switch);
    $is_player = $is_remote && $switch == 'media';

    // BVEF compat without core OEmbed security feature.
    // @todo remove once BVEF adopted Blazy:2.10 BlazyVideoFormatter.
    if ($is_remote && strpos($embed_url, 'media/oembed') === FALSE) {
      // The multimedia is defined for core Media, not VEF, so set it here.
      $blazies->set('is.multimedia', TRUE);
      if ($is_player) {
        $embed_url = Blazy::autoplay($embed_url);
      }
    }

    // Addresses mixed media unique per item, aside from convenience.
    // Also compat with BVEF till they are updated to adopt 2.10 changes.
    $blazies->set('is.iframe', $is_iframe)
      ->set('is.remote_video', $is_remote)
      ->set('is.player', $is_player)
      ->set('media.embed_url', $embed_url)
      ->set('media.type', $type)
      ->set('switch', $switch);
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
    // @todo $lazy = $settings['loading'] == 'lazy';
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

    Blazy::verify($settings);
    $blazies = $settings['blazies'];

    // Bail out if lazy load is disabled, or in sandbox mode.
    if ($blazies->is('nojs') || $blazies->is('sandboxed')) {
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
      || !empty($settings['background'])
      || !empty($settings['responsive_image_style'])
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

}
