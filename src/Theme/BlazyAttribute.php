<?php

namespace Drupal\blazy\Theme;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
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
   * Modifies container attributes with aspect ratio for iframe, image, etc.
   */
  public static function finalize(array &$variables): void {
    $attributes = &$variables['attributes'];
    $settings = &$variables['settings'];
    $blazies = $settings['blazies'];

    // Aspect ratio to fix layout reflow with lazyloaded images responsively.
    // This is outside 'lazy' to allow non-lazyloaded iframe/content use it too.
    // Prevents double padding hacks with AMP which also uses similar technique.
    $disabled = empty($settings['height']) || $blazies->is('amp');
    $ratio = $disabled ? '' : $settings['ratio'];
    $settings['ratio'] = str_replace(':', '', $ratio);

    // Fixed aspect ratio is taken care of by pure CSS. Fluid means dynamic.
    if ($settings['ratio'] && $settings['height'] && $blazies->is('fluid')) {

      // If "lucky", Blazy/ Slick Views galleries may already set this once.
      // Lucky when you don't flatten out the Views output earlier.
      $padding = BlazyImage::ratio($settings);
      $padding = $blazies->get('item.padding_bottom', $padding);

      self::inlineStyle($attributes, 'padding-bottom: ' . $padding . '%;');

      // Views rewrite results or Twig inline_template may strip out `style`
      // attributes, provide hint to JS.
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
   * player: If no ightboxes, it is an image to iframe switcher.
   * data- : Gets consistent with ightboxes to share JS manipulation.
   *
   * @param array $variables
   *   The variables being modified.
   */
  public static function buildIframe(array &$variables): void {
    $settings = &$variables['settings'];

    // Only provide iframe if not for lightboxes, identified by URL.
    if (empty($variables['url'])) {
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

      // Iframe is removed on lazyloaded, puts data at non-removable storage.
      $variables['attributes']['data-media'] = Json::encode(['type' => $settings['type']]);
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
    if ($bgs = $blazies->get('bgs')) {
      $attributes['class'][] = 'b-bg';
      $attributes['data-b-bg'] = Json::encode($bgs);
      $url = $blazies->get('image.url');

      if ($blazies->is('static') && $url) {
        self::inlineStyle($attributes, 'background-image: url(' . $url . ');');
      }
    }

    // Prepare iframe, and allow a tiny video preview without iframe.
    $disabled = $settings['_noiframe'] ?? '';
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
    if (!$blazies->is('undata') && $ratios = $blazies->get('ratios', [])) {
      $attributes['data-ratios'] = Json::encode($ratios);
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
    $attributes['class'] = ['b-lazy', 'media__iframe'];
    $attributes['allowfullscreen'] = TRUE;
    $embed_url = $blazies->get('media.embed_url');

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
    else {
      $attributes['data-src'] = $embed_url;
      $attributes['src'] = 'about:blank';
    }

    self::common($attributes, $settings);
    return $attributes;
  }

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
    $namespace = $blazies->get('namespace', 'blazy');

    // Provides data-LIGHTBOX-gallery to not conflict with original modules.
    if ($lightbox = $blazies->get('lightbox.name')) {
      $switch = str_replace('_', '-', $lightbox);
      $attributes['data-' . $switch . '-gallery'] = TRUE;
      $classes[] = 'blazy--' . $switch;
    }

    // For CSS fixes.
    if ($blazies->is('unlazy')) {
      $classes[] = 'blazy--nojs';
    }

    // Provides contextual classes relevant to the container: .field, or .view.
    // Sniffs for Views to allow block__no_wrapper, views__no_wrapper, etc.
    $view_mode = $settings['current_view_mode'] ?? '';
    foreach (['field', 'view'] as $key) {
      $name = $settings[$key . '_name'] ?? '';
      $name = $blazies->get($key . '.name', $name);
      if ($name) {
        $name = str_replace('_', '-', $name);
        $name = $key == 'view' ? 'view--' . $name : $name;
        $classes[] = $namespace . '--' . $key;
        $classes[] = $namespace . '--' . $name;

        $view_mode = $blazies->get($key . '.view_mode', $view_mode);
        if ($view_mode) {
          $view_mode = str_replace('_', '-', $view_mode);
          $classes[] = $namespace . '--' . $name . '--' . $view_mode;
        }
      }
    }

    $attributes['class'] = array_merge(['blazy'], $classes);
    $attributes['data-blazy'] = $data && is_array($data) ? Json::encode($data) : '';
  }

  /**
   * Defines attributes, builtin, or supported lazyload such as Slick.
   *
   * These attributes can be applied to either IMG or DIV as CSS background.
   * The [data-(src|lazy)] attributes are applicable for (Responsive) image.
   * While [data-src] is reserved by Blazy, [data-lazy] by Slick.
   *
   * @param array $attributes
   *   The attributes being modified.
   * @param array $settings
   *   The given settings.
   *
   * @todo remove settings.
   */
  public static function lazy(array &$attributes, array $settings): void {
    $blazies = $settings['blazies'];

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
   * Modifies inline style to not nullify others.
   */
  public static function inlineStyle(array &$attributes, $css): void {
    $attributes['style'] = ($attributes['style'] ?? '') . $css;
  }

  /**
   * Returns the sanitized attributes for user-defined (UGC Blazy Filter).
   *
   * When IMG and IFRAME are allowed for untrusted users, trojan horses are
   * welcome. Hence sanitize attributes relevant for BlazyFilter. The rest
   * should be taken care of by HTML filters after Blazy.
   *
   * @param array $attributes
   *   The given attributes to sanitize.
   * @param bool $escaped
   *   Sets to FALSE to avoid double escapes, for further processing.
   *
   * @return array
   *   The sanitized $attributes suitable for UGC, such as Blazy filter.
   */
  public static function sanitize(array $attributes, $escaped = TRUE): array {
    $output = [];
    $tags = ['href', 'poster', 'src', 'about', 'data', 'action', 'formaction'];

    foreach ($attributes as $key => $value) {
      if (is_array($value)) {
        // Respects array item containing space delimited classes: aaa bbb ccc.
        $value = implode(' ', $value);
        $output[$key] = array_map('\Drupal\Component\Utility\Html::cleanCssIdentifier', explode(' ', $value));
      }
      else {
        // Since Blazy is lazyloading known URLs, sanitize attributes which
        // make no sense to stick around within IMG or IFRAME tags.
        $kid = mb_substr($key, 0, 2) === 'on' || in_array($key, $tags);
        $key = $kid ? 'data-' . $key : $key;
        $escaped_value = $escaped ? Html::escape($value) : $value;
        $output[$key] = $kid ? Html::cleanCssIdentifier($value) : $escaped_value;
      }
    }
    return $output;
  }

  /**
   * Provide common attributes for IMG, IFRAME, VIDEO, DIV, etc. elements.
   */
  private static function common(array &$attributes, array $settings): void {
    $attributes['class'][] = 'media__element';

    // @todo at 2022/2 core has no loading Responsive.
    $excludes = in_array($settings['loading'], ['slider', 'unlazy']);
    if (!empty($settings['width']) && !$excludes) {
      $attributes['loading'] = $settings['loading'] ?: 'lazy';
    }
  }

  /**
   * Modifies $variables to provide optional (Responsive) image attributes.
   */
  private static function image(array &$variables): void {
    $item       = $variables['item'];
    $settings   = &$variables['settings'];
    $image      = &$variables['image'];
    $attributes = &$variables['item_attributes'];
    $blazies    = $settings['blazies'];
    $embed_url  = $blazies->get('media.embed_url');

    // Respects hand-coded image attributes.
    if ($item) {
      if (!isset($attributes['alt'])) {
        $attributes['alt'] = empty($item->alt) ? NULL : trim($item->alt);
      }

      // Do not output an empty 'title' attribute.
      if (isset($item->title) && (mb_strlen($item->title) != 0)) {
        $attributes['title'] = trim($item->title);
      }
    }

    // Only output dimensions for non-svg. Respects hand-coded image attributes.
    // Do not pass it to $attributes to also respect both (Responsive) image.
    if (!isset($attributes['width']) && !$blazies->is('unstyled')) {
      $image['#height'] = $settings['height'];
      $image['#width'] = $settings['width'];
    }

    // Overrides title if to be used as a placeholder for lazyloaded video.
    if ($embed_url && $title = $blazies->get('media.label')) {
      $translation_replacements = ['@label' => $title];
      $attributes['title'] = t('Preview image for the video "@label".', $translation_replacements);

      if (!empty($attributes['alt'])) {
        $translation_replacements['@alt'] = $attributes['alt'];
        $attributes['alt'] = t('Preview image for the video "@label" - @alt.', $translation_replacements);
      }
      else {
        $attributes['alt'] = $attributes['title'];
      }
    }

    $attributes['class'][] = 'media__image';
    // https://developer.mozilla.org/en-US/docs/Web/API/HTMLImageElement/decode.
    $attributes['decoding'] = 'async';

    // Preserves UUID for sub-module lookups, relevant for BlazyFilter.
    if ($uuid = $blazies->get('entity.uuid')) {
      $attributes['data-entity-uuid'] = $uuid;
    }

    self::common($attributes, $variables['settings']);
    $image['#attributes'] = empty($image['#attributes'])
      ? $attributes : NestedArray::mergeDeep($image['#attributes'], $attributes);

    // Provides a noscript if so configured, before any lazy defined.
    // Not needed at preview mode, or when native lazyload takes over.
    if ($blazies->get('ui.noscript') && !$blazies->is('unlazy')) {
      self::buildNoscriptImage($variables);
    }

    // Provides [data-(src|lazy)] for (Responsive) image, after noscript.
    self::lazy($image['#attributes'], $settings);
    self::unloading($image['#attributes'], $settings);
  }

  /**
   * Modifies variables for blazy (non-)lazyloaded image.
   */
  private static function buildImage(array &$variables): void {
    $attributes = &$variables['attributes'];
    $settings = &$variables['settings'];
    $blazies = $settings['blazies'];

    // Supports either lazy loaded image, or not.
    if (empty($settings['background'])) {
      $url = $blazies->get('image.url');
      $variables['image'] += [
        '#theme' => 'image',
        '#uri' => $blazies->is('unlazy') ? $url : $blazies->get('placeholder.url'),
      ];
    }
    else {
      // Attach BG data attributes to a DIV container.
      $blazies->set('bgs.' . $settings['width'], BlazyImage::background($settings));

      $unlazy = $blazies->is('undata');
      $url = $unlazy ? $url : $blazies->get('placeholder.url');

      $blazies->set('image.url', $url)
        ->set('is.unlazy', $unlazy);

      self::lazy($attributes, $settings);
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
      ? $blazies->get('uri')
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
    $settings = &$variables['settings'];
    $blazies = $settings['blazies'];

    if (empty($settings['background'])) {
      $natives = ['decoding' => 'async'];
      $attributes = ($blazies->is('unlazy')
        ? $natives
        : [
          'data-b-lazy' => $blazies->get('ui.one_pixel'),
          'data-b-ui' => $blazies->get('ui.placeholder'),
          'data-b-placeholder' => $blazies->get('placeholder.url'),
        ]);

      $variables['image'] += [
        '#theme' => 'responsive_image',
        '#responsive_image_style_id' => $blazies->get('resimage.id'),
        '#uri' => $blazies->get('uri'),
        '#attributes' => $attributes,
      ];
    }
    else {
      // Attach BG data attributes to a DIV container.
      $attributes = &$variables['attributes'];
      BlazyResponsiveImage::background($attributes, $settings);
    }
  }

  /**
   * Removes loading attributes if so configured.
   */
  private static function unloading(array &$attributes, array &$settings): void {
    $blazies = $settings['blazies'];
    $flag = $blazies->is('unloading');
    $flag = $flag || $blazies->is('slider') && $blazies->is('initial');

    if ($flag) {
      $attributes['data-b-unloading'] = TRUE;
    }
  }

}
