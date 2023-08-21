<?php

namespace Drupal\blazy\Theme;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Media\BlazyResponsiveImage;
use Drupal\blazy\Media\Placeholder;
use Drupal\blazy\Media\Ratio;
use Drupal\blazy\internals\Internals;
use Drupal\blazy\Utility\Arrays;
use Drupal\blazy\Utility\Check;

/**
 * Provides non-reusable blazy attribute static methods.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class Attributes {

  /**
   * Provides attachments when not using the provided API.
   */
  public static function attach(array &$variables, array $settings = []): void {
    if ($blazy = Internals::service('blazy.manager')) {
      $attachments = $blazy->attach($settings) ?: [];
      $variables['#attached'] = Arrays::merge($attachments, $variables, '#attached');
    }
  }

  /**
   * Provides container attributes for .blazy container: .field, .view, etc.
   *
   * Relevant for JS lookups, lightbox galleries, also to accommodate
   * block__no_wrapper, views__no_wrapper, etc. with helpful CSS classes, useful
   * for DOM diets.
   */
  public static function container(array &$attributes, array $settings): void {
    $blazies  = Internals::verify($settings);
    $classes  = (array) ($attributes['class'] ?? []);
    $data     = $blazies->get('data.blazy');
    $switcher = $blazies->get('lightbox.name') ?: $settings['media_switch'] ?? NULL;

    // Might be by-passed due to minimal settings, or outside the workflow.
    // See \Drupal\blazy\Theme\BlazyViews::preprocessViewsView().
    if ($switcher && !$blazies->was('lightbox')) {
      Check::lightboxes($settings);
    }

    $lightbox  = $blazies->get('lightbox.name', $switcher);
    $namespace = $blazies->get('namespace', $settings['namespace'] ?? 'blazy');
    $nested    = $blazies->is('grid_nested');

    // Provides data-LIGHTBOX-gallery to not conflict with original modules.
    // Prevents nested grids from having similar lightbox attributes.
    // Nested grids are seen at Slick|Splide nested/ chunked grids carousels.
    if (!$nested) {
      $options = [
        'namespace' => $namespace,
        'lightbox'  => $lightbox,
        'switcher'  => $switcher,
      ];

      // Provides contextual classes relevant to containers: .field, or .view.
      // Sniffs for Views to allow block__no_wrapper, views__no_wrapper, etc.
      if ($extras = self::firstClasses($attributes, $blazies, $options)) {
        $classes = array_merge($classes, $extras);
      }
    }

    // Needed for nested grids as well: blazy blazy--grid b-nativegrid, etc.
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
    $hacks   = Ratio::hack($settings);
    $hack    = $hacks['hack'];
    $ratio   = $hacks['ratio'];
    $padding = $blazies->get('image.ratio');

    // @todo recheck for 0 padding SVG.
    $settings['ratio'] = $ratio && $padding ? str_replace(':', '', $ratio) : '';

    // Fixed aspect ratio is taken care of by pure CSS. Fluid means dynamic.
    // Unless the computed result above is supported by current CSS rules.
    if ($hack && $padding) {
      // If "lucky", Blazy/ Slick Views galleries may already set this once.
      // Lucky when you don't flatten out the Views output earlier.
      self::inlineStyle($attributes, 'padding-bottom: ' . $padding . '%;');

      // Views rewrite results or Twig inline_template may strip out `style`
      // attributes, provide hint to JS.
      // @todo replace with data-b-ratio by 3.x to avoid potential conflicts.
      $attributes['data-ratio'] = $padding;
    }

    // Lazy load HTML content.
    if ($blazies->get('lazy.html')) {
      $unlazy = self::isUnlazy($blazies);
      if (!$unlazy && $html = $blazies->get('media.encoded.content')) {
        if (!$blazies->get('bgs')) {
          $attributes['data-src'] = '';
        }
        $attributes['data-b-html'] = Internals::DATA_TEXT . $html;
        $attributes['class'][] = 'b-lazy';
        $attributes['class'][] = 'b-html';
      }
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
    $blazies  = $settings['blazies'];

    // Only provide iframe if not for lightboxes, identified by URL.
    if (empty($variables['url'])) {
      // Also empty the image to not get in the way, unless player enabled.
      $variables['image'] = empty($settings['media_switch']) ? [] : $variables['image'];

      // Pass iframe attributes to template.
      if (!$blazies->use('scripted_iframe')) {
        $variables['iframe'] = [
          '#type' => 'html_tag',
          '#tag' => 'iframe',
          '#attributes' => self::iframe($settings),
        ];
      }

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

    // 1. Prepares thumbnail and optional placeholder based on thumbnail.
    // Do not place this any lower, else breaking some logic below.
    Placeholder::prepare($attributes, $settings);

    // 2. (Responsive) image is optional for Video, or image as CSS background.
    if ($blazies->get('resimage.id')) {
      self::buildResponsiveImage($variables);
    }
    else {
      self::buildImage($variables);
    }

    // 3. The bgs is output specific for CSS background purposes with BC.
    // This is applied to both Responsive and plain old images.
    if ($bgs = $blazies->get('bgs')) {
      self::background($attributes, $blazies, $bgs);
    }

    // 4. Prepare iframe, and allow a tiny video preview without iframe.
    if ($blazies->is('iframe') && !$blazies->is('noiframe')) {
      self::buildIframe($variables);
    }

    // 5. (Responsive) image is optional for Video, or image as CSS background.
    if ($variables['image'] || $bgs) {
      if ($variables['image']) {
        self::image($variables);
      }

      // 6. Only blur if it has an image, or BG, including the media player.
      if ($blazies->is('blur')) {
        Placeholder::blur($variables, $settings);
      }
    }

    // 7. Multi-breakpoint aspect ratio only applies if lazyloaded.
    // These may be set once at formatter level, or per breakpoint above.
    // Only relevant if Fluid is selected for Aspect ratio, else a leak.
    // @todo rename it to data-b-ratios at/by 3.x.
    if ($blazies->is('fluid') && !$blazies->is('undata')) {
      if ($ratios = $blazies->get('ratios', [])) {
        // @todo replace with data-b-ratios by 3.x to avoid potential conflicts.
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
    $attributes['allowfullscreen'] = TRUE;

    // Already escaped upstream for core, except for contribs.
    $embed_url = $blazies->get('media.embed_url');
    if (!$blazies->get('media.escaped')) {
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
      $attributes['class'][] = 'b-lazy';
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
   * @param bool $bg
   *   If a background image.
   */
  public static function lazy(array &$attributes, $blazies, $bg = FALSE): void {
    // Slick has its own class and methods: ondemand, anticipative, progressive.
    // The data-[SRC|SCRSET|LAZY] is if `nojs` disabled, background, or video.
    $trusted = $blazies->get('image.trusted');
    if ($url = $blazies->get('image.url')) {
      $url = $trusted ? $url : UrlHelper::stripDangerousProtocols($url);
      $unlazy = self::isUnlazy($blazies);

      // Native, or unlazy, has .blazy--nojs at container to fix issues, if any.
      if (!$unlazy) {
        // @todo put it back up above if any issues.
        $attributes['class'][] = $blazies->get('lazy.class', 'b-lazy');
        $attribute = $blazies->get('lazy.attribute', 'src');
        $attributes['data-' . $attribute] = $url;
      }

      if ($bg && $unlazy) {
        self::inlineStyle($attributes, 'background-image: url(' . $url . ');');
      }
    }
  }

  /**
   * Return the image alt and title, also accounts for multimedia and UGC.
   */
  public static function altTitle($blazies, $item = NULL): array {
    [
      'alt' => $alt,
      'title' => $title,
    ] = self::altTitleRaw($blazies, $item);

    // Ensures no double escapes since it might called anywhere.
    if ($blazies->get('image.escaped')) {
      return ['alt' => $alt ?: '', 'title' => $title];
    }

    // Updates $title whether for audio/ video, or just image.
    if ($title) {
      $title = Html::escape($title);
      // Twig will escape Can't to Can&#039;t, else doubles: Can&amp;#039;t.
      // @todo recheck if the world is ended with this, and so remove this.
      $title = str_replace('&#039;', "'", $title);
    }

    if ($alt) {
      $alt = Html::escape($alt);
      // Twig will escape Can't to Can&#039;t, else doubles: Can&amp;#039;t.
      // @todo recheck if the world is ended with this, and so remove this.
      $alt = str_replace('&#039;', "'", $alt);
    }

    // Overrides title if to be used as a placeholder for lazyloaded video.
    if ($blazies->is('multimedia') && $title) {
      $_title = $title;
      $bundle = $blazies->get('media.bundle', 'remote_video');
      $bundle = str_replace('remote_', '', $bundle);
      $bundle = str_replace('_', ' ', $bundle);

      // Prioritize editable user inputs rather than external sites'.
      $blazies->set('media.label', $title);

      $translation = ['@bundle' => $bundle, '@label' => $title];
      $title = self::mediaTitle($translation);

      if ($alt) {
        if ($alt == $_title) {
          $alt = $title;
        }
        else {
          $translation['@alt'] = $alt;
          $alt = new TranslatableMarkup('Preview image for the @bundle "@label" - @alt.', $translation);
        }
      }
      else {
        $alt = $title;
      }
    }

    // Redefine for good reasons.
    $blazies->set('image.alt', $alt)
      ->set('image.title', $title)
      ->set('image.escaped', TRUE);

    return ['alt' => $alt ?: '', 'title' => $title];
  }

  /**
   * Return the raw image alt and title, normally for captions, not attributes.
   */
  private static function altTitleRaw($blazies, $item = NULL): array {
    $title = $blazies->get('image.raw.title');
    $alt   = $blazies->get('image.raw.alt');

    // Ensures no double processes.
    if ($blazies->get('image.raw.processed')) {
      return ['alt' => $alt ?: '', 'title' => $title];
    }

    $title = $blazies->get('image.title') ?: $blazies->get('media.label');
    $alt   = $blazies->get('image.alt');

    // @todo remove this item check at 3.x, once they are all in $blazies.
    if ($item) {
      // Title from fake item might be just file name, except from BlazyFilter.
      // Needed by thumbnails if any image item, fake or real, no biggies.
      // @todo recheck, alt from fake image factory might be just file name.
      $alt = empty($item->alt) ? $alt : trim($item->alt);
      $desc = $item->description ?? NULL;

      // File SVG with description_field enabled.
      if (!$alt && $desc = $blazies->get('image.description', $desc)) {
        $alt = $desc;
      }

      // Do not output an empty 'title' attribute.
      if (isset($item->title) && (mb_strlen($item->title) != 0)) {
        $title = trim($item->title);
      }
    }

    // Might be abused to use HTML, fine for captions, but not attributes.
    // This should make both parties happier ever after, sort of.
    if ($title) {
      $title = strip_tags($title);
    }

    $alt = strip_tags($alt ?: '');

    // Ensures called once, else filled up even when it should be empty.
    $blazies->set('image.raw.alt', $alt)
      ->set('image.raw.title', $title)
      ->set('image.raw.processed', TRUE);

    return ['alt' => $alt, 'title' => $title];
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

    // If using BG, store title in the permanent container.
    if ($blazies->is('multimedia') && $title = self::altTitle($blazies)['title']) {
      $attributes['title'] = $title;
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

    // Provides image alt and title, and also accounts for multimedia.
    $attributes['alt'] = $blazies->get('image.alt', '');

    if ($title = $blazies->get('image.title')) {
      $attributes['title'] = $title;
    }

    // https://developer.mozilla.org/en-US/docs/Web/API/HTMLImageElement/decode.
    $attributes['decoding'] = 'async';

    // Preserves UUID for sub-module lookups, relevant for BlazyFilter.
    if ($uuid = $blazies->get('entity.uuid')) {
      $attributes['data-entity-uuid'] = $uuid;
    }

    // Only output dimensions for non-svg. Respects hand-coded image attributes.
    // Do not pass it to $attributes to also respect both (Responsive) image.
    // Also supports svg dimensions, if any.
    if (!isset($attributes['width']) && $width = $blazies->get('image.width')) {
      $image['#height'] = $blazies->get('image.height');
      $image['#width']  = $width;
    }

    // Apply common shared attributes.
    self::common($attributes, $blazies);
    $image['#attributes'] = Arrays::merge($attributes, $image, '#attributes');

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
      self::lazy($attributes, $blazies, TRUE);
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
    $blazies  = $settings['blazies'];
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
   * Returns the classes applicable only to the first, not nested containers.
   */
  private static function firstClasses(array &$attributes, $blazies, array $options): array {
    [
      'namespace' => $namespace,
      'lightbox'  => $lightbox,
      'switcher'  => $switcher,
    ] = $options;

    $classes   = [];
    $add_class = !$blazies->ui('wrapper_class');

    // For CSS fixes.
    if ($blazies->is('unlazy')) {
      $classes[] = 'blazy--nojs';
    }

    // Specific for media switcher, lightbox or not.
    if ($switcher) {
      $switch = str_replace('_', '-', $switcher);
      $attributes['data-' . $switch . '-gallery'] = TRUE;

      $classes[] = 'blazy--' . $switch;

      if ($blazies->is('lightbox')) {
        $classes[] = 'blazy--lightbox';
        $classes[] = 'blazy--' . $switch . '-gallery';

        // Allows lightboxes to inject their optionset, if any.
        // More accessible and contextual than in the <HEAD> or <SCRIPT> tags.
        if ($extras = $blazies->data($lightbox)) {
          $attributes['data-' . $switch] = Json::encode($extras);
        }
      }
    }

    foreach (['field', 'view'] as $key) {
      if ($name = $blazies->get($key . '.name')) {
        $classes[] = $namespace . '--' . $key;

        if ($add_class) {
          $name = str_replace('_', '-', $name);
          $name = $key == 'view' ? 'view--' . $name : $name;
          $classes[] = $namespace . '--' . $name;

          if ($view_mode = $blazies->get($key . '.view_mode')) {
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
    return $classes;
  }

  /**
   * Return the image title.
   */
  private static function mediaTitle($translation): TranslatableMarkup {
    return new TranslatableMarkup('Preview image for the @bundle "@label".', $translation);
  }

  /**
   * Removes loading attributes if so configured.
   */
  private static function unloading(array &$attributes, $blazies): void {
    $flag = $blazies->is('unloading');
    $flag = $flag || self::isUnlazy($blazies);

    if ($flag) {
      $attributes['data-b-unloading'] = TRUE;
    }
  }

  /**
   * Disable lazyload as required.
   *
   * The following will disable lazyload:
   * - if loading:slider is chosen for the initial slide, normally delta 0.
   * - If unlazy: globally disabled via `No JavaScript` option.
   * - If static: CK Editor/ preview mode, AMP, and sandboxed mode.
   */
  private static function isUnlazy($blazies): bool {
    return $blazies->is('unlazy')
      || $blazies->is('static')
      || $blazies->is('slider') && $blazies->is('initial');
  }

}
