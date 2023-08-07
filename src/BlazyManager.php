<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\blazy\Theme\Lightbox;
use Drupal\blazy\Utility\CheckItem;

/**
 * Implements a public facing blazy manager.
 *
 * A few modules re-use this: GridStack, Mason, Slick...
 */
class BlazyManager extends BlazyManagerBase implements BlazyManagerInterface, TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  protected static $namespace = 'blazy';

  /**
   * {@inheritdoc}
   */
  protected static $itemId = 'content';

  /**
   * {@inheritdoc}
   */
  protected static $itemPrefix = 'blazy';

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderBlazy', 'preRenderBuild'];
  }

  /**
   * {@inheritdoc}
   */
  public function getBlazy(array $build): array {
    $hashtags = array_keys(BlazyDefault::hashedProperties());

    foreach (BlazyDefault::themeProperties() as $key => $default) {
      $k = in_array($key, $hashtags) ? "#$key" : $key;
      $build[$k] = $this->toHashtag($build, $key, $default);
    }

    $item     = $this->toHashtag($build, 'item', NULL);
    $blazies  = $this->preBlazy($build, $item);
    $settings = $build['#settings'];

    // Respects content not handled by theme_blazy(), but passed through.
    // Yet allows rich contents which might still be processed by theme_blazy().
    $content = !$blazies->get('image.uri') ? $build['content'] : [
      '#theme'       => 'blazy',
      '#delta'       => $blazies->get('delta'),
      '#item'        => $item,
      '#image_style' => $settings['image_style'],
      '#build'       => $build,
      '#pre_render'  => [[$this, 'preRenderBlazy']],
    ];

    $this->moduleHandler->alter('blazy', $content, $settings);
    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function preRenderBlazy(array $element): array {
    $build = $element['#build'];
    unset($element['#build']);

    // Prepare the main image.
    $this->prepareBlazy($element, $build);

    // Fetch the newly modified settings with hashed key.
    $settings = &$element['#settings'];
    $blazies = $settings['blazies'];
    $url = $blazies->get('entity.url');

    // Requires a string to strip, image_formatter has a Url object.
    if ($blazies->get('switch') == 'content' && $url && is_string($url)) {
      $element['#url'] = UrlHelper::stripDangerousProtocols($url);
    }
    elseif ($blazies->is('lightbox')) {
      Lightbox::build($element);
    }

    unset($build);
    return $element;
  }

  /**
   * Returns the contents using theme_field(), or theme_item_list().
   *
   * Blazy outputs can be formatted using either flat list via theme_field(), or
   * a grid of Field items or Views rows via theme_item_list().
   *
   * @param array $build
   *   The array containing: settings, children elements, or optional items.
   *
   * @return array
   *   The alterable and renderable array of contents.
   */
  public function build(array $build): array {
    $settings = $this->getBlazySettings($build);
    $blazies  = $settings['blazies'];

    // This #pre_render doesn't work if called from Views results, hence the
    // output is split either as theme_field() or theme_item_list().
    if ($blazies->is('grid')) {
      // Take over theme_field() with a theme_item_list(), if so configured.
      // The reason: this is not only fed by field items, but also Views rows.
      $build['#settings'] = $settings;
      $content = [
        '#build'      => $build,
        '#pre_render' => [[$this, 'preRenderBuild']],
      ];

      // Yet allows theme_field(), if so required, such as for linked_field.
      $build = $blazies->use('theme_field') ? [$content] : $content;
    }
    else {
      // If not a grid, pass items as regular index children to theme_field().
      // Runs after settings.
      $build = $this->toElementChildren($build);

      // @todo refactor and move non-children out of here at 3.x.
      // We don't use #settings here to avoid conflicts with others because
      // theme_field() is not managed by blazy.
      $build['#blazy'] = $settings;
      $this->setAttachments($build, $settings);
    }

    $this->moduleHandler->alter('blazy_build', $build, $settings);
    return $build;
  }

  /**
   * Builds the Blazy outputs as a structured array ready for ::renderer().
   */
  public function preRenderBuild(array $element): array {
    $build = $element['#build'];
    unset($element['#build']);

    // Checks if we got some signaled attributes.
    $attributes = $element['#theme_wrappers']['container']['#attributes']
      ?? $element['#attributes'] ?? [];

    // Checks if we got some signaled attachments.
    $attachments = $this->toHashtag($build, 'attached');
    if ($attachments) {
      unset($build['#attached'], $build['attached']);
    }

    $settings = $build['#settings'];

    // Runs after settings.
    $items = $this->toElementChildren($build);

    // Take over elements for a grid display as this is all we need, learned
    // from the issues such as: #2945524, or product variations.
    // We'll selectively pass or work out $attributes not so far below.
    $element = $this->toGrid($items, $settings);

    if ($attributes) {
      // Signals other modules if they want to use it.
      // Cannot merge it into Grid (wrapper_)attributes, done as grid.
      // Use case: Product variations, best served by ElevateZoom Plus.
      if (isset($element['#ajax_replace_class'])) {
        $element['#container_attributes'] = Blazy::sanitize($attributes);
      }
      else {
        // Use case: VIS, can be blended with UL element safely down here.
        // The $attributes is merged with self::toGrid() ones here.
        $attrs = $this->merge($attributes, $element, '#attributes');
        $element['#attributes'] = Blazy::sanitize($attrs);
      }
    }

    // Sets attachments/ libraries, and container caches.
    $this->setAttachments($element, $settings, $attachments);
    unset($build);
    return $element;
  }

  /**
   * Build captions for both old image, or media entity.
   */
  protected function buildCaption(array $captions, $blazies, $prefix, $id = 'blazy'): array {
    $inline = $categories = $descriptions = $overlays = [];
    $_desc  = $prefix . 'description';
    $keys   = array_keys($captions);
    $keys   = array_combine($keys, $keys);
    $keys   = array_filter($keys, fn($k) => strpos($k, 'title') === FALSE, ARRAY_FILTER_USE_KEY);
    $single = count($keys) == 1;

    // Supports multiple description fields.
    foreach ($captions as $key => $caption) {
      $css = $prefix . $key;
      if (strpos($key, 'title') !== FALSE) {
        $inline[$key] = $this->toHtml($caption, 'h2', $prefix . 'title');
      }
      elseif ($key == 'overlay') {
        $overlays[$key] = $this->toHtml($caption, 'div', $css);
      }
      elseif ($key == 'category') {
        $categories[$key] = $this->toHtml($caption, 'div', $css);
      }
      else {
        $key = str_replace('field_', '', $key);
        $css = str_replace('_', '-', $key);
        $css = $prefix . $css;

        // Merge alt, data, description in one description container.
        $nowrap = $single && isset($caption['#markup']);
        if (in_array($key, ['alt', 'data', 'description'])) {
          // Preserve old behaviors, but prevents similar classes.
          $key = $key == 'description' ? 'item' : $key;
          $css = $id == 'blazy' ? $_desc . '-' . $key : $_desc . '--' . $key;

          // @todo remove, might all be just NULL here.
          $css = $nowrap || $key == 'data' ? NULL : $css;

          $descriptions[$key] = $this->toHtml($caption, 'div', $css);
        }
        else {
          // Might be link, etc. here on.
          $inline[$key] = $this->toHtml($caption, 'div', $css);
        }
      }
    }

    // Merge multiple decsriptions to avoid too many siblings.
    if ($descriptions) {
      $inline['description'] = $this->toHtml($descriptions, 'div', $_desc);
    }

    $output = [];
    if ($inline = array_filter($inline)) {
      // Link is normally at the end of the day.
      if ($item = $inline['link'] ?? []) {
        unset($inline['link']);
        $inline['link'] = $item;
      }

      // Figcaption is more relevant for core filter captions under Figure.
      $tag     = $blazies->is('figcaption') ? 'figcaption' : 'div';
      $output  = ['inline' => $inline, 'tag' => $tag];
      $output += $categories;
    }
    return $output + $overlays;
  }

  /**
   * Build out (rich media) content.
   */
  private function buildContent(array &$element, array &$build): void {
    $settings = &$build['#settings'];
    $blazies  = $settings['blazies'];

    if (empty($build['content'])) {
      return;
    }

    // Prevents complication for now, such as lightbox for Facebook, etc.
    // Either makes no sense, or not currently supported without extra legs.
    // Original formatter settings can still be accessed via content variable.
    $blazies->set('placeholder', [])
      ->set('is.bg', FALSE)
      ->set('is.unlazy', TRUE)
      ->set('use.loader', FALSE);

    // Supports HTML content for lightboxes as long as having image trigger.
    // Local media to not conflict with Image rendered by its formatter option.
    // Only possible if having hires image via `Main stage` aka cross image.
    $hires     = $blazies->is('hires', !empty($settings['image']));
    $litebox   = $blazies->is('lightbox');
    $richbox   = $blazies->is('richbox');
    $supported = $blazies->is('local_media') && $litebox && $richbox;
    $blazy     = $build['content'][0]['#settings'] ?? NULL;

    if ($supported && $hires && $blazy instanceof BlazySettings) {
      // Overrides the overriden settings with original formatter settings.
      // Might be confusing, but $blazy ($settings as object) was what we wanted
      // since 2 RCs, only never succedded even at 3.x to anything other than
      // these media entities. That is why re-dumped/ normalized as an array.
      // And we only concerned about configurable settings, not objects here on.
      $settings = $this->merge($blazy->storage(), $settings);
      $element['#lightbox_html'] = $build['content'];

      // This allows theme_blazy() to process it as workable media elements.
      $build['content'] = [];
    }
  }

  /**
   * Build out (Responsive) image.
   *
   * Since 2.9, many were moved into BlazyTheme to support custom work better.
   */
  private function buildMedia(array &$element, array &$build): void {
    $item     = $build['#item'];
    $settings = $build['#settings'];
    $blazies  = $settings['blazies'];
    $attrs    = $this->toHashtag($build, 'item_attributes');

    // Extract field item attributes for the theme function, and unset them
    // from the $item so that the field template does not re-render them.
    // (Responsive) image with item attributes, might be RDF.
    if ($item && isset($item->_attributes)) {
      $attrs += $item->_attributes;
      unset($item->_attributes);
    }

    // Provides all media cache.
    // See https://www.drupal.org/project/drupal/issues/2469277.
    if (!$blazies->is('cache_deferred')) {
      if ($caches = $blazies->get('cache.metadata', [])) {
        $element['#cache'] = $caches;
      }
    }

    // Pass item_attributes to theme_blazy(), see if any issues:
    // https://www.drupal.org/project/blazy/issues/3374519.
    $element['#item_attributes'] = Blazy::sanitize($attrs);
  }

  /**
   * Prepares Blazy settings.
   *
   * Supports galeries if provided, updates $settings.
   * Cases: Blazy within Views gallery, or references without direct image.
   * Views may flatten out the array, bail out.
   * What we do here is extract the formatter settings from the first found
   * image and pass its settings to this container so that Blazy Grid which
   * lacks of settings may know if it should load/ display a lightbox, etc.
   * Lightbox should work without `Use field template` checked.
   */
  private function getBlazySettings(array $build) {
    $settings = $this->toHashtag($build);
    $this->verify($settings);

    $blazies = $settings['blazies'];
    if ($data = $blazies->get('first.data')) {
      if (is_array($data)) {
        $this->isBlazy($settings, $data);
      }
    }
    return $settings;
  }

  /**
   * Checks for essential blazy features.
   *
   * @param array $build
   *   The build array being modified.
   * @param object $item
   *   The optional image item.
   *
   * @return \Drupal\blazy\BlazySettings
   *   The BlazySettings object.
   */
  private function preBlazy(array &$build, $item = NULL): BlazySettings {
    $this->hashtag($build);
    $settings = &$build['#settings'];

    $this->verify($settings);

    $blazies = $settings['blazies'];
    $delta   = $blazies->get('delta', $build['#delta'] ?? 0);

    // Prevents double checks.
    // BlazySettings is a self containing object, initialized at container level
    // and must be renewed at item level to get correct delta, see #3278525.
    $blazies = $settings['blazies']->reset($settings);
    $blazies->set('is.api', TRUE)
      ->set('delta', $delta);

    $attributes = &$build['#item_attributes'];
    CheckItem::essentials($attributes, $settings, $item);

    return $blazies;
  }

  /**
   * Prepares the Blazy output as a structured array ready for ::renderer().
   *
   * @param array $element
   *   The renderable array being modified.
   * @param array $build
   *   The array of information containing the required Image or File item
   *   object, settings, optional container attributes.
   */
  private function prepareBlazy(array &$element, array $build) {
    $item       = $build['#item'];
    $settings   = &$build['#settings'];
    $blazies    = $settings['blazies'];
    $attributes = &$build['#attributes'];

    // Only add figure for grid if using Blazy Filter [caption] shortcode mixed
    // with core [data-caption]. The rest should just have figure tags, either
    // standalone images, or sliders.
    if ($blazies->is('figcaption') && $blazies->is('grid')) {
      $blazies->set('item.wrapper_tag', 'figure')
        ->set('item.wrapper_attributes.class', ['blazy__content']);
    }

    // Blazy has 3 attributes: attributes, item_attributes, url_attributes, yet
    // provides optional ones. No defaults are provided for all these.
    $theme_attributes = BlazyDefault::themeAttributes();
    foreach ($theme_attributes as $key) {
      $key            = $key . '_attributes';
      $defaults       = $this->toHashtag($build, $key);
      $programs       = $blazies->get('item.' . $key, []);
      $build["#$key"] = $this->merge($programs, $defaults);
    }

    // Initial feature checks, URI, delta, media features, etc.
    $item_attributes = &$build['#item_attributes'];
    BlazyInternal::prepare($item_attributes, $settings, $item);

    // Build thumbnail and optional placeholder based on thumbnail.
    // Prepare image URL and its dimensions, including for rich-media content,
    // such as for local video poster image if a poster URI is provided.
    BlazyInternal::prepared($attributes, $item_attributes, $settings, $item);

    // Allows altering the settings for individual items.
    // Such as disabling lightbox for inline media player.
    $this->moduleHandler->alter('blazy_item', $settings, $attributes, $item_attributes);

    // Only process (Responsive) image/ video if no rich-media are provided.
    $this->buildContent($element, $build);
    if (empty($build['content'])) {
      $this->buildMedia($element, $build);
    }

    // Provides extra attributes as needed.
    // Was planned to replace sub-module item markups if similarity is found for
    // theme_gridstack_box(), theme_slick_slide(), etc. Likely for Blazy 3.x+.
    // Since 2.17, it is optional at Blazy UI under `Use theme_blazy()` option.
    foreach ($theme_attributes as $key) {
      $key   = $key . '_attributes';
      $attrs = $this->toHashtag($build, $key);
      // Sanitize potential user-defined attributes such as from BlazyFilter.
      $element["#$key"] = $attrs ? Blazy::sanitize($attrs) : [];
    }

    // Provides captions, if so configured.
    $captions = $this->toHashtag($build, 'captions');
    if ($captions = array_filter($captions)) {
      $this->toCaption($element, $captions, $blazies);
    }

    // Preparing Blazy to replace other blazy-related content/ item markups.
    // Composing or layering is crucial for mixed media (icon over CTA or text
    // or lightbox links or iframe over image or CSS background over noscript
    // which cannot be simply dumped as array without elaborate arrangements).
    foreach (BlazyDefault::themeContents() as $key => $default) {
      $defaults         = $this->toHashtag($build, $key, $default);
      $programs         = $blazies->get('html.' . $key, $default);
      $values           = $this->merge($programs, $defaults);
      $element["#$key"] = $this->merge($values, $element, "#$key");
    }

    // Pass common elements to theme_blazy().
    $element['#attributes'] = Blazy::sanitize($attributes);
    $element['#item'] = $build['#item'];
    $element['#settings'] = $settings;
  }

  /**
   * Provides captions, if any.
   */
  private function toCaption(array &$element, $captions, $blazies): void {
    $id     = $blazies->get('item.id', 'blazy');
    $id     = $id == 'content' ? 'blazy' : $id;
    $self   = $id == 'blazy';
    $prefix = $self ? $id . '__caption--' : $id . '__';

    if ($output = $this->buildCaption($captions, $blazies, $prefix, $id)) {
      $element['#captions'] = $output;

      // @todo remove debug:
      if (!$self && !$blazies->ui('deprecated_class')) {
        $element['#caption_attributes']['class'][] = 'blazy__caption';
      }

      $element['#caption_attributes']['class'][] = $id . '__caption';

      // Overlays are media players/ nested sliders over images seen at Slick/
      // Splide Paragraphs and their Views styles, only treated as a caption.
      // Established since Slick:7.2. as the first client requirements.
      if (!empty($output['overlay'])) {
        // A wrapper for descriptions, titles, etc. when overlay exists
        // so they can be grouped, split, moved, overlayed over overlay, etc.
        // Perhaps ID__caption--content is better, but leave the ancient alone.
        $element['#caption_content_attributes']['class'][] = $prefix . 'data';
      }
    }
  }

  /**
   * Prepares Blazy outputs, extract items as indices.
   *
   * If children are grouped within items property, reset to indexed keys.
   * Blazy comes late to the party after sub-modules decided what they want
   * where items may be stored as direct indices, or put into items property.
   * Actually the same issue happens at core where contents may be indexed or
   * grouped. Meaning not a problem at all, only a problem for consistency.
   *
   * @todo call directly items after migrations at/by 3.x.
   */
  private function toElementChildren(array $build): array {
    $build = $build['items']
      ?? array_filter($build, fn($k) => is_int($k), ARRAY_FILTER_USE_KEY);

    unset(
      $build['#entity'],
      $build['#settings'],
      $build['items'],
      $build['settings']
    );

    return $build;
  }

}
