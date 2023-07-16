<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Template\Attribute;
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
  public static function trustedCallbacks() {
    return ['preRenderBlazy', 'preRenderBuild'];
  }

  /**
   * {@inheritdoc}
   */
  public function getBlazy(array $build, $delta = -1): array {
    foreach (BlazyDefault::themeProperties() as $key => $default) {
      $build[$key] = $this->toHashtag($build, $key, $default);
    }

    // Temporary checks till final migration at/by 3.x.
    $item      = $this->toHashtag($build, 'item');
    $settings  = &$build['settings'];
    $settings += Blazy::init();

    // Prevents double checks.
    // BlazySettings is a self containing object, initialized at container level
    // and must be renewed at item level to get correct delta, see #3278525.
    $blazies = $settings['blazies']->reset($settings);
    $blazies->set('is.api', TRUE);

    CheckItem::essentials($settings, $item);

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
    $settings = $element['#settings'];
    $blazies = $settings['blazies'];
    $url = $blazies->get('entity.url');

    // Requires a string to strip, image_formatter has a Url object.
    if ($blazies->get('switch') == 'content' && $url && is_string($url)) {
      $element['#url'] = UrlHelper::stripDangerousProtocols($url);
    }
    elseif ($blazies->is('lightbox')) {
      Lightbox::build($element);
    }

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
    $blazies = $settings['blazies'];

    // This #pre_render doesn't work if called from Views results, hence the
    // output is split either as theme_field() or theme_item_list().
    if ($blazies->is('grid')) {
      // Take over theme_field() with a theme_item_list(), if so configured.
      // The reason: this is not only fed by field items, but also Views rows.
      $build['settings'] = $settings;
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

    $settings = $this->toHashtag($build);

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
   *
   * Was planned above years ago to replace sub-modules if any similarity.
   * The only blocking is blazy has no dedicated CSS classes for link and
   * overlay, etc. other than the field_NAME without field, almost close.
   */
  protected function buildCaption(array $captions, array $settings, $id = 'blazy') {
    $blazies  = $settings['blazies'];
    $content  = $title = $descriptions = [];
    $is_blazy = $id == 'blazy';
    $prefix   = $is_blazy ? $id . '__caption--' : $id . '__';
    $_title   = $prefix . 'title';
    $_desc    = $prefix . 'description';

    // Supports multiple description fields.
    foreach ($captions as $key => $caption) {
      if ($caption) {
        $is_title = strpos($key, 'title') !== FALSE;
        if ($is_title) {
          $content[$key]['content'] = $caption;
          $content[$key]['tag'] = 'h2';

          $attrs = new Attribute();
          $attrs->addClass($_title);
          $content[$key]['attributes'] = $attrs;
        }
        else {
          // Preserve old behaviors, but prevents similar classes.
          $key = str_replace('field_', '', $key);
          if ($key == 'description') {
            $key = 'item';
          }
          $subattrs['class'] = [$id . '__caption--' . $key];
          $descriptions[$key] = isset($caption['#markup'])
            ? $caption : [
              '#theme'      => 'container',
              '#children'   => $caption,
              '#attributes' => $subattrs,
            ];
        }
      }
    }

    // Allows multiple fields with link, etc. without too many siblings.
    if ($descriptions) {
      $key = 'description';
      $content[$key]['content'] = $descriptions;
      $content[$key]['tag'] = 'div';
      $attrs = new Attribute();
      $attrs->addClass($_desc);
      $content[$key]['attributes'] = $attrs;
    }

    // Figcaption is more relevant for core filter captions under Figure.
    $tag = $blazies->is('figcaption') ? 'figcaption' : 'div';

    return $content ? ['inline' => $content, 'tag' => $tag] : [];
  }

  /**
   * Build out (rich media) content.
   */
  private function buildContent(array &$element, array &$build) {
    $settings = &$build['settings'];
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
    $supported = $blazies->is('richbox') ?: $settings['_richbox'] ?? FALSE;
    $supported = $blazies->is('local_media') && $litebox && $supported;
    $blazy     = ($build['content'][0]['#settings'] ?? NULL);

    if ($supported && $hires && $blazy instanceof BlazySettings) {
      // Overrides the overriden settings with original formatter settings.
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
    $item = $this->toHashtag($build, 'item', NULL);
    $settings = $this->toHashtag($build);
    $blazies = $settings['blazies'];
    $item_attributes = $this->toHashtag($build, 'item_attributes');

    // Extract field item attributes for the theme function, and unset them
    // from the $item so that the field template does not re-render them.
    // (Responsive) image with item attributes, might be RDF.
    if ($item && isset($item->_attributes)) {
      $item_attributes += $item->_attributes;
      unset($item->_attributes);
    }

    // Provides all media cache.
    // See https://www.drupal.org/project/drupal/issues/2469277.
    if (!$blazies->is('cache_deferred')) {
      if ($caches = $blazies->get('cache.metadata', [])) {
        $element['#cache'] = $caches;
      }
    }

    // Pass non-rich-media elements to theme_blazy().
    $element['#item_attributes'] = Blazy::sanitize($item_attributes);
    unset($build['item_attributes']);
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
    Blazy::verify($settings);

    $blazies = $settings['blazies'];
    if ($data = $blazies->get('first.data')) {
      if (is_array($data)) {
        $this->isBlazy($settings, $data);
      }
    }
    return $settings;
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
    $item       = $this->toHashtag($build, 'item', NULL);
    $settings   = $this->toHashtag($build);
    $blazies    = $settings['blazies'];
    $attributes = &$build['attributes'];

    // Blazy has these 3 attributes, yet provides optional ones far below.
    // The supported: 'caption', 'media', 'url', 'wrapper'.
    $theme_attributes = BlazyDefault::themeAttributes();
    foreach ($theme_attributes as $key) {
      $key = $key . '_attributes';
      // @todo prefix it with # post migration at/ by 3.x.
      $build[$key] = $this->toHashtag($build, $key);
    }

    // Initial feature checks, URI, delta, media features, etc.
    BlazyInternal::prepare($settings, $item);

    // Build thumbnail and optional placeholder based on thumbnail.
    // Prepare image URL and its dimensions, including for rich-media content,
    // such as for local video poster image if a poster URI is provided.
    BlazyInternal::prepared($attributes, $settings, $item);

    // Only process (Responsive) image/ video if no rich-media are provided.
    $this->buildContent($element, $build);
    if (empty($build['content'])) {
      $this->buildMedia($element, $build);
    }

    // Provides extra attributes as needed.
    // Was planned to replace sub-module item markups if similarity is found for
    // theme_gridstack_box(), theme_slick_slide(), etc. Likely for Blazy 3.x+.
    // The supported: 'caption', 'media', 'url', 'wrapper'.
    foreach ($theme_attributes as $key) {
      $key = $key . '_attributes';
      $attrs = $this->toHashtag($build, $key);
      // Sanitize potential user-defined attributes such as from BlazyFilter.
      $element["#$key"] = Blazy::sanitize($attrs);
    }

    // Provides captions, if so configured.
    $id = $blazies->get('item.id', 'blazy');
    $content = $this->toHashtag($build, 'captions');
    if ($content && ($captions = $this->buildCaption($content, $settings, $id))) {
      $element['#captions'] = $captions;
      $element['#caption_attributes']['class'][] = $id . '__caption';
    }

    // Pass common elements to theme_blazy().
    $element['#attributes'] = Blazy::sanitize($attributes);
    $element['#settings'] = $settings;

    // Preparing Blazy to replace other blazy-related content/ item markups.
    // Composing or layering is crucial for mixed media (icon over CTA or text
    // or lightbox links or iframe over image or CSS background over noscript
    // which cannot be simply dumped as array without elaborate arrangements).
    foreach (['content', 'icon', 'overlay', 'preface', 'postscript'] as $key) {
      $values = $this->toHashtag($build, $key);
      $element["#$key"] = $this->merge($values, $element, "#$key");
      if (isset($build[$key])) {
        unset($build[$key]);
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
    $build = $build['items'] ?? $build;
    unset(
      $build['#entity'],
      $build['#settings'],
      $build['items'],
      $build['settings']
    );
    return $build;
  }

}
