<?php

namespace Drupal\blazy\Theme;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\blazy\Blazy;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Media\BlazyResponsiveImage;
use Drupal\blazy\Media\Placeholder;

/**
 * Provides non-reusable blazy attribute static methods.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class BlazyAttribute {

  /**
   * Provides container attributes for .blazy container: .field, .view, etc.
   *
   * Relevant for JS lookups, lightbox galleries, also to accommodate
   * block__no_wrapper, views__no_wrapper, etc. with helpful CSS classes, useful
   * for DOM diets.
   */
  public static function container(array &$attributes, array $settings): void {
    Blazy::verify($settings);

    $blazies   = $settings['blazies'];
    $classes   = (array) ($attributes['class'] ?? []);
    $data      = $blazies->get('data.blazy');
    $namespace = $blazies->get('namespace') ?: $settings['namespace'] ?? 'blazy';
    $lightbox  = $blazies->get('lightbox.name') ?: $settings['media_switch'] ?? NULL;

    // Provides data-LIGHTBOX-gallery to not conflict with original modules.
    if ($lightbox) {
      $switch = str_replace('_', '-', $lightbox);
      $attributes['data-' . $switch . '-gallery'] = TRUE;

      if ($blazies->is('lightbox')) {
        $classes[] = 'blazy--lightbox';
      }

      $classes[] = 'blazy--' . $switch;

      if ($extras = $blazies->data($lightbox)) {
        $attributes['data-' . $switch] = Json::encode($extras);
      }
    }

    // For CSS fixes.
    if ($blazies->is('unlazy')) {
      $classes[] = 'blazy--nojs';
    }

    // Provides contextual classes relevant to the container: .field, or .view.
    // Sniffs for Views to allow block__no_wrapper, views__no_wrapper, etc.
    $add_class = !$blazies->ui('wrapper_class');
    foreach (['field', 'view'] as $key) {
      if ($name = $blazies->get($key . '.name')) {
        $classes[] = $namespace . '--' . $key;

        if ($add_class) {
          $name = str_replace('_', '-', $name);
          $name = $key == 'view' ? 'view--' . $name : $name;
          $classes[] = $namespace . '--' . $name;

          $view_mode = $blazies->get($key . '.view_mode');
          if ($view_mode) {
            $view_mode = str_replace('_', '-', $view_mode);
            $classes[] = $namespace . '--' . $name . '--' . $view_mode;
          }

          // See BlazyAlter::blazySettingsAlter().
          if ($id = $blazies->get('view.instance_id')) {
            $classes[] = $namespace . '--view--' . $id;
          }
        }
      }
    }

    $attributes['class'] = array_merge(['blazy'], $classes);
    $attributes['data-blazy'] = $data && is_array($data) ? Json::encode($data) : '';
  }

  /**
   * Modifies container attributes with aspect ratio for iframe, image, etc.
   */
  public static function finalize(array &$variables): void {
    $attributes = &$variables['attributes'];
    $settings   = &$variables['settings'];
    $blazies    = $settings['blazies'];

    // Aspect ratio to fix layout reflow with lazyloaded images responsively.
    // This is outside 'lazy' to allow non-lazyloaded iframe/content use it too.
    // Prevents double padding hacks with AMP which also uses similar technique.
    $disabled = !$blazies->get('image.height') || $blazies->is('amp');
    $fluid    = $blazies->is('fluid');
    $ratio    = $disabled ? '' : $settings['ratio'];
    $computed = $ratio && $fluid;
    $resimage = $blazies->get('resimage.id');

    // Skip padding hacks if fluid is supported by plain CSS, to avoid JS.
    // Do not mess up with responsive image for now, or you'll be sorry.
    if (!$resimage && $computed && $check = $blazies->get('image.fluid')) {
      $ratio = $check;
      $computed = FALSE;
    }

    $settings['ratio'] = $ratio ? str_replace(':', '', $ratio) : '';

    // Fixed aspect ratio is taken care of by pure CSS. Fluid means dynamic.
    // Unless the computed result above is supported by current CSS rules.
    if ($computed && $padding = $blazies->get('image.ratio')) {
      // If "lucky", Blazy/ Slick Views galleries may already set this once.
      // Lucky when you don't flatten out the Views output earlier.
      self::inlineStyle($attributes, 'padding-bottom: ' . $padding . '%;');

      // Views rewrite results or Twig inline_template may strip out `style`
      // attributes, provide hint to JS.
      // @todo replace with data-b-ratio by 3.x to avoid potential conflicts.
      $attributes['data-ratio'] = $padding;
    }

    // Makes a little BEM order here due to Twig ignoring the preset priority.
    $classes = (array) ($attributes['class'] ?? []);
    $attributes['class'] = array_merge(['media', 'media--blazy'], $classes);
    $variables['blazies'] = $blazies->storage();
  }

  /**
   * Modifies variables for iframes, those only handled by theme_blazy().
   *
   * This iframe is not printed when `Image to iframe` is chosen.
   *
   * Prepares a media player, and allows a tiny video preview without iframe.
   * image : If iframe switch disabled, fallback to iframe, remove image.
   * player: If no lightboxes, it is an image to iframe switcher.
   * data- : Gets consistent with lightboxes to share JS manipulation.
   *
   * @param array $variables
   *   The variables being modified.
   */
  public static function buildIframe(array &$variables): void {
    $settings = &$variables['settings'];

    // Only provide iframe if not for lightboxes, identified by URL.
    if (empty($variables['url'])) {
      // Also empty the image to not get in the way, unless player enabled.
      $variables['image'] = empty($settings['media_switch']) ? [] : $variables['image'];

      // Pass iframe attributes to template.
      $variables['iframe'] = [
        '#type' => 'html_tag',
        '#tag' => 'iframe',
        '#attributes' => self::iframe($settings),
      ];

      // If not media player, iframe only, without image, disable blur.
      if (empty($variables['image']) && isset($variables['preface']['blur'])) {
        $variables['preface']['blur'] = [];
      }
    }
  }

  /**
   * Modifies variables for image and iframe.
   *
   * @param array $variables
   *   The variables being modified.
   */
  public static function buildMedia(array &$variables): void {
    $attributes = &$variables['attributes'];
    $settings   = &$variables['settings'];
    $blazies    = $settings['blazies'];

    // (Responsive) image is optional for Video, or image as CSS background.
    if ($blazies->get('resimage.id')) {
      self::buildResponsiveImage($variables);
    }
    else {
      self::buildImage($variables);
    }

    // The settings.bgs is output specific for CSS background purposes with BC.
    // This is applied to both Responsive and plain old images.
    if ($bgs = $blazies->get('bgs')) {
      self::background($attributes, $blazies, $bgs);
    }

    // Prepare iframe, and allow a tiny video preview without iframe.
    $disabled = $settings['_noiframe'] ?? FALSE;
    if ($blazies->is('iframe') && !$blazies->is('noiframe', $disabled)) {
      self::buildIframe($variables);
    }

    // (Responsive) image is optional for Video, or image as CSS background.
    if ($variables['image'] || $bgs) {
      if ($variables['image']) {
        self::image($variables);
      }

      // Only blur if it has an image, or BG, including the media player.
      if ($blazies->is('blur')) {
        Placeholder::blur($variables, $settings);
      }
    }

    // Multi-breakpoint aspect ratio only applies if lazyloaded.
    // These may be set once at formatter level, or per breakpoint above.
    // Only relevant if Fluid is selected for Aspect ratio, else a leak.
    // @todo rename it to data-b-ratios at/by 3.x.
    if ($blazies->is('fluid')) {
      if (!$blazies->is('undata') && $ratios = $blazies->get('ratios', [])) {
        // @todo replace with data-b-ratio by 3.x to avoid potential conflicts.
        $attributes['data-ratios'] = Json::encode($ratios);
      }
    }
  }

  /**
   * Returns common iframe attributes, including those not handled by blazy.
   *
   * @param array $settings
   *   The given settings.
   *
   * @return array
   *   The iframe attributes.
   */
  public static function iframe(array &$settings): array {
    $blazies = $settings['blazies'];
    $attributes['class'] = ['b-lazy'];
    $attributes['allowfullscreen'] = TRUE;
    $is_escaped = $blazies->get('media.escaped');

    // Already escaped upstream for core, except for contribs.
    $embed_url = $blazies->get('media.embed_url');

    // @todo recheck if any side effect/ double escape to cdn/ valid input.
    if (!$is_escaped) {
      $embed_url = UrlHelper::stripDangerousProtocols($embed_url);
    }

    // Inside CKEditor must disable interactive elements.
    if ($blazies->is('sandboxed')) {
      $attributes['sandbox'] = TRUE;
      $attributes['src'] = $embed_url;
    }
    // Native lazyload just loads the URL directly.
    // With many videos like carousels on the page may chaos, but we provide a
    // solution: use `Image to Iframe` for GDPR, swipe and best performance.
    elseif ($blazies->is('unlazy')) {
      $attributes['src'] = $embed_url;
    }
    // Non-native lazyload for oldies to avoid loading src, the most efficient.
    // No cookies are loaded from external sites till the play button clicked.
    else {
      $attributes['data-src'] = $embed_url;
      $attributes['src'] = 'about:blank';
    }

    self::common($attributes, $blazies);
    return $attributes;
  }

  /**
   * Modifies inline style to not nullify others.
   */
  public static function inlineStyle(array &$attributes, $css): void {
    $attributes['style'] = ($attributes['style'] ?? '') . $css;
  }

  /**
   * Defines attributes, builtin, or supported lazyload such as Slick/ Splide.
   *
   * These attributes can be applied to either IMG or DIV as CSS background.
   * The [data-(src|lazy)] attributes are applicable for (Responsive) image.
   * While [data-src] is reserved by Blazy, [data-lazy] by Slick.
   *
   * @param array $attributes
   *   The attributes being modified.
   * @param object $blazies
   *   The given $blazies.
   */
  public static function lazy(array &$attributes, $blazies): void {
    // For consistent CSS fix, and w/o Native.
    $attributes['class'][] = $blazies->get('lazy.class', 'b-lazy');

    // Slick has its own class and methods: ondemand, anticipative, progressive.
    // The data-[SRC|SCRSET|LAZY] is if `nojs` disabled, background, or video.
    if (!$blazies->is('unlazy')) {
      $attribute = $blazies->get('lazy.attribute');
      $attributes['data-' . $attribute] = $blazies->get('image.url');
    }
  }

  /**
   * Provide common attributes for IMG, IFRAME, VIDEO, etc. elements.
   */
  private static function common(array &$attributes, $blazies): void {
    $attributes['class'][] = 'media__element';
    $loading = $blazies->get('image.loading', 'lazy');

    // @todo at 2022/2 core has no loading Responsive.
    $excludes = in_array($loading, ['slider', 'unlazy']);
    if ($blazies->get('image.width') && !$excludes) {
      $attributes['loading'] = $loading;
    }
  }

  /**
   * Modifies $variables to provide background (Responsive) image attributes.
   */
  private static function background(array &$attributes, $blazies, $bgs): void {
    $attributes['class'][] = 'b-bg';
    $attributes['data-b-bg'] = Json::encode($bgs);
    $url = $blazies->get('image.url');

    // If using BG, store it in the permanent container.
    if ($blazies->is('multimedia')) {
      $title = $blazies->get('image.title') ?: $blazies->get('media.label');
      if (!$title) {
        $title = $blazies->get('image.alt');
      }

      if ($title) {
        $title = strip_tags($title);
        $translation_replacements = ['@label' => Html::escape($title)];
        $attributes['title'] = self::videoTitle($translation_replacements);
      }
    }

    if ($blazies->is('static') && $url) {
      $url = UrlHelper::stripDangerousProtocols($url);
      self::inlineStyle($attributes, 'background-image: url(' . $url . ');');
    }
  }

  /**
   * Modifies $variables to provide optional (Responsive) image attributes.
   */
  private static function image(array &$variables): void {
    $settings   = &$variables['settings'];
    $image      = &$variables['image'];
    $attributes = &$variables['item_attributes'];
    $blazies    = $settings['blazies'];
    $embed_url  = $blazies->get('media.embed_url');
    $width      = $blazies->get('image.width');
    $title      = $blazies->get('image.title') ?: $blazies->get('media.label');
    $title      = $attributes['title'] ?? $title;
    $alt        = $attributes['alt'] ?? NULL;
    $alt        = $alt ?: $blazies->get('image.alt');

    // $extra_attrs = $blazies->get('item.safe_attributes', []);
    // Updates $title whether for video, or just image, and accounts for UGC.
    if ($title) {
      // Might be abused to use HTML, fine for lightboxes, but not attributes.
      // This should make both parties happier ever after, sort of.
      $title = Html::escape(strip_tags($title));
      $attributes['title'] = $title;
      $blazies->set('image.title', $title);
    }

    // Respects hand-coded image attributes, and accounts for UGC.
    if ($alt) {
      // Might be abused to use HTML, fine for lightboxes, but not attributes.
      // This should make both parties happier ever after, sort of.
      $alt = Html::escape(strip_tags($alt));
    }

    $attributes['alt'] = $alt ?: '';
    $blazies->set('image.alt', $alt);

    // Only output dimensions for non-svg. Respects hand-coded image attributes.
    // Do not pass it to $attributes to also respect both (Responsive) image.
    if (!isset($attributes['width']) && !$blazies->is('unstyled')) {
      $image['#height'] = $blazies->get('image.height');
      $image['#width'] = $width;
    }

    // Overrides title if to be used as a placeholder for lazyloaded video.
    if ($embed_url && $title) {
      // Prioritize editable user inputs rather than external sites'.
      $blazies->set('media.label', $title);

      $translation_replacements = ['@label' => $title];
      $attributes['title'] = self::videoTitle($translation_replacements);

      if ($alt) {
        $translation_replacements['@alt'] = $alt;
        $attributes['alt'] = new TranslatableMarkup('Preview image for the video "@label" - @alt.', $translation_replacements);
      }
      else {
        $attributes['alt'] = $attributes['title'];
      }
    }

    // https://developer.mozilla.org/en-US/docs/Web/API/HTMLImageElement/decode.
    $attributes['decoding'] = 'async';

    // Preserves UUID for sub-module lookups, relevant for BlazyFilter.
    if ($uuid = $blazies->get('entity.uuid')) {
      $attributes['data-entity-uuid'] = $uuid;
    }

    // Apply common shared attributes.
    self::common($attributes, $blazies);
    $image['#attributes'] = Blazy::merge($attributes, $image, '#attributes');

    // @fixme, this causes SRC set discretely, even if none provided.
    // if ($extra_attrs) {
    // foreach ($extra_attrs as $key => $value) {
    // $image['#attributes'][$key] = $value;
    // }
    // }
    // Provides a noscript if so configured, before any lazy defined.
    // Not needed at preview mode, or when native lazyload takes over.
    if ($blazies->ui('noscript') && !$blazies->is('unlazy')) {
      self::buildNoscriptImage($variables);
    }

    // Provides [data-(src|lazy)] for (Responsive) image, after noscript.
    self::lazy($image['#attributes'], $blazies);
    self::unloading($image['#attributes'], $blazies);
  }

  /**
   * Modifies variables for blazy (non-)lazyloaded image.
   */
  private static function buildImage(array &$variables): void {
    $attributes  = &$variables['attributes'];
    $settings    = &$variables['settings'];
    $blazies     = $settings['blazies'];
    $url         = $blazies->get('image.url');
    $placeholder = $blazies->get('placeholder.url');
    $background  = $blazies->is('bg', !empty($settings['background']));

    // Supports either lazy loaded image, or not.
    if ($background) {
      // Attach BG data attributes to a DIV container.
      // Background is not supported by Native, cannot use unlazy, use undata:
      // - undata: no use of dataset (data-b-bg) like at AMP, or preview pages.
      // - unlazy: `No JavaScript: lazy` aka decoupled lazy loader + undata.
      $style = $blazies->get('image.style');
      $width = $blazies->get('image.width') ?: 101;
      // @fixme background is screwed up somehow, only when using core image as
      // source image upstream, fine when given blazy image formatter.
      // $unlazy = $blazies->is('undata');
      // $url = $unlazy ? $url : $placeholder;
      // $blazies->set('image.url', $url);
      // ->set('is.unlazy', $unlazy);
      $data = $settings;
      $data['width'] = $width;
      $data['height'] = $blazies->get('image.height');
      $blazies->set('bgs.' . $width, BlazyImage::background($data, $style));
      self::lazy($attributes, $blazies);
    }
    else {
      $variables['image'] += [
        '#theme' => 'image',
        '#uri' => $blazies->is('unlazy') ? $url : $placeholder,
      ];
    }
  }

  /**
   * Provides (Responsive) image noscript if so configured.
   */
  private static function buildNoscriptImage(array &$variables): void {
    $settings = $variables['settings'];
    $blazies = $settings['blazies'];
    $noscript = $variables['image'];
    $noscript['#uri'] = $blazies->get('resimage.id')
      ? $blazies->get('image.uri')
      : $blazies->get('image.url');

    $noscript['#attributes']['data-b-noscript'] = TRUE;

    $variables['noscript'] = [
      '#type' => 'inline_template',
      '#template' => '{{ prefix | raw }}{{ noscript }}{{ suffix | raw }}',
      '#context' => [
        'noscript' => $noscript,
        'prefix' => '<noscript>',
        'suffix' => '</noscript>',
      ],
    ];
  }

  /**
   * Modifies variables for responsive image.
   *
   * Responsive images with height and width save a lot of calls to
   * image.factory service for every image and breakpoint in
   * _responsive_image_build_source_attributes(). Very necessary for
   * external file system like Amazon S3.
   *
   * @param array $variables
   *   The variables being modified.
   */
  private static function buildResponsiveImage(array &$variables): void {
    $settings   = &$variables['settings'];
    $blazies    = $settings['blazies'];
    $background = $blazies->is('bg', !empty($settings['background']));

    if ($background) {
      // Attach BG data attributes to a DIV container.
      $attributes = &$variables['attributes'];
      BlazyResponsiveImage::background($attributes, $settings);
    }
    else {
      $natives = ['decoding' => 'async'];
      $attributes = ($blazies->is('unlazy')
        ? $natives
        : [
          'data-b-lazy' => $blazies->ui('one_pixel'),
          'data-b-ui' => $blazies->ui('placeholder'),
          'data-b-placeholder' => $blazies->get('placeholder.url'),
        ]);

      $variables['image'] += [
        '#theme' => 'responsive_image',
        '#responsive_image_style_id' => $blazies->get('resimage.id'),
        '#uri' => $blazies->get('image.uri'),
        '#attributes' => $attributes,
      ];
    }
  }

  /**
   * Return the image title.
   */
  private static function videoTitle($translation_replacements): TranslatableMarkup {
    return new TranslatableMarkup('Preview image for the video "@label".', $translation_replacements);
  }

  /**
   * Removes loading attributes if so configured.
   */
  private static function unloading(array &$attributes, $blazies): void {
    $flag = $blazies->is('unloading');
    $flag = $flag || $blazies->is('slider') && $blazies->is('initial');

    if ($flag) {
      $attributes['data-b-unloading'] = TRUE;
    }
  }

}
