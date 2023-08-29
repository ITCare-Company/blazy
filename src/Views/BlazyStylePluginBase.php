<?php

namespace Drupal\blazy\Views;

use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\views\Views;
use Drupal\blazy\internals\Internals;

/**
 * A base for blazy views integration to support fieldable entities.
 *
 * @see \Drupal\mason\Plugin\views\style\MasonViews
 * @see \Drupal\gridstack\Plugin\views\style\GridStackViews
 * @see \Drupal\slick_views\Plugin\views\style\SlickViews
 * @see \Drupal\splide\Plugin\views\style\SplideViews
 */
abstract class BlazyStylePluginBase extends BlazyStyleBase implements BlazyStylePluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * The Views as options.
   *
   * @var array
   */
  protected $viewsOptions;

  /**
   * {@inheritdoc}
   */
  protected function buildElement(array &$element, $row, $delta) {
    $this->manager->hashtag($element);

    $settings = &$element['#settings'];
    $blazies  = $this->reset($settings);
    $_image   = $settings['image'] ?? NULL;

    $blazies->set('delta', $delta);
    $captions = $this->getCaption($delta, $settings, $row);

    if ($extras = $element[static::$captionId] ?? []) {
      $captions = array_merge($captions, $extras);
      unset($element[static::$captionId]);
    }

    // Add layout field, may be a list field, or builtin layout options.
    if (!empty($settings['layout'])) {
      $this->getLayout($settings, $delta);
    }

    // Add main image fields if so configured.
    // Supports individual grid/box image style either inline IMG, or CSS.
    $element['#delta'] = $delta;
    if ($_image || $captions) {
      $image = $this->getImageRenderable($settings, $row, $delta);
      $rendered = $image['rendered'] ?? [];
      $element['#item'] = $image['raw'] ?? NULL;

      if ($image['applicable']) {
        $element['content'] = $rendered['#build']['content'] ?? [];
      }
      else {
        $element['content'] = $rendered;
      }

      // Provides the relevant elements based on the configuration.
      // @todo refine for other formatters here.
      $this->toElement($blazies, $element, $captions);
    }
  }

  /**
   * Returns the modified renderable image_formatter to support lazyload.
   */
  protected function getImageRenderable(array &$settings, $row, $index): array {
    $image    = $this->getImageArray($row, $index, $settings['image']);
    $rendered = $image['rendered'] ?? [];
    $item     = $image['raw'] ?? NULL;

    // Supports 'group_rows' option.
    // @todo recheck if any side issues for not having raw key.
    $image['applicable'] = FALSE;
    if (!$rendered) {
      return $image;
    }

    // If the image has #item property, lazyload may work, otherwise skip.
    // This hustle is to lazyload tons of images -- grids, large galleries,
    // gridstack, mason, with multimedia/ lightboxes for free.
    /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
    /* @phpstan-ignore-next-line */
    if ($this->isValidImageItem($item)) {
      $image['raw'] = $item;

      // Supports multiple image styles within a single view such as GridStack,
      // else fallbacks to the defined image style if available.
      if (empty($settings['image_style'])) {
        $settings['image_style'] = $rendered['#image_style'] ?? '';
      }

      // Converts image formatter for blazy to reduce complexity with CSS
      // background option, and other options, and still lazyload it.
      $theme = $rendered['#theme']
        ?? $rendered['#build'][0]['#theme']
        ?? '';

      if ($theme && in_array($theme, ['blazy', 'image_formatter'])) {
        if ($theme == 'blazy') {
          $this->withBlazyFormatter($settings, $rendered, $index);
        }
        elseif ($theme == 'image_formatter') {
          $this->withImageFormatter($settings, $rendered, $index);
        }

        $image['applicable'] = TRUE;
      }
    }

    return $image;
  }

  /**
   * Checks if we can work with this formatter, otherwise no go if flattened.
   */
  protected function getImageArray($row, $index, $field_image): array {
    if ($field_image
      && $image = $this->getFieldRenderable($row, $index, $field_image)) {

      // Just to be sure, replace raw with the found image item.
      if ($item = $this->getImageItem($image)) {
        $image['raw'] = $item;
      }

      // Known image formatters: Blazy, Image, etc. which provides ImageItem.
      // Else dump Video embed thumbnail/video/colorbox as is.
      if ($item || isset($image['rendered'])) {
        return $image;
      }
    }
    return [];
  }

  /**
   * Get the image item to work with out of this formatter.
   *
   * All this mess is because Views may render/flatten images earlier.
   */
  protected function getImageItem($image): ?object {
    $item = NULL;

    if ($rendered = ($image['rendered'] ?? [])) {
      // Image formatter.
      $item = $rendered['#item'] ?? NULL;

      // Blazy formatter, also supports multiple, `group_rows`.
      if ($build = ($rendered['#build'] ?? [])) {
        $item = $this->manager->toHashtag($build, 'item') ?: $item;
        $item = $build[0]['#item'] ?? $item;
      }
    }

    // Don't know other reasonable formatters to work with.
    return $this->isValidImageItem($item) ? $item : NULL;
  }

  /**
   * Returns the caption elements.
   */
  protected function getCaption($index, array $settings, $row = NULL): array {
    $view     = $this->view;
    $captions = [];
    $keys     = array_keys($view->field);
    $keys     = array_combine($keys, $keys);
    $_link    = $settings['link'] ?? NULL;
    $_title   = $settings['title'] ?? NULL;
    $_overlay = $settings['overlay'] ?? NULL;
    $_caption = $settings['caption'] ?? [];

    // Caption items: link, title, overlay, and data, anything else selected.
    $captions['title']   = $this->getFieldRendered($index, $_title, TRUE, $row);
    $captions['link']    = $this->getFieldRendered($index, $_link, TRUE, $row);
    $captions['overlay'] = $this->getFieldRendered($index, $_overlay);

    // Exclude non-caption fields so that theme_views_view_fields() kicks in
    // and only render expected caption fields. As long as not-hidden, each
    // caption field should be wrapped with Views markups.
    if ($_caption) {
      $excludes = array_diff_assoc($keys, $_caption);
      foreach ($excludes as $field) {
        $view->field[$field]->options['exclude'] = TRUE;
      }

      if ($output = $view->rowPlugin->render($view->result[$index])) {
        $captions['data'][$index] = $output;
      }
    }

    return array_filter($captions);
  }

  /**
   * Returns the rendered layout fields, normally just string.
   */
  protected function getLayout(array &$settings, $index): void {
    $layout = $settings['layout'] ?? '';
    // Replacing useless field_NAME with its useful value.
    if (strpos($layout, 'field_') !== FALSE) {
      if ($value = $this->getField($index, $layout)) {
        $settings['layout'] = strip_tags($value);
      }
    }
  }

  /**
   * Returns TRUE if a valid image item, else FALSE.
   */
  protected function isValidImageItem($item): bool {
    return is_object($item) && (isset($item->uri) || isset($item->target_id));
  }

  /**
   * Returns the relevant elements based on the configuration.
   *
   * @todo call self::themeBlazy() directly at 3.x after sub-modules.
   * @todo remove for BlazyElementTrait if similar to field formatters.
   */
  protected function toElement($blazies, array &$data, array $captions): void {
    $delta    = $data['#delta'] ?? 0;
    $captions = array_filter($captions);

    // Call manager not formatter due to sub-module deviations.
    $this->manager->verifyItem($data, $delta);

    // Provides inline SVG if applicable.
    // @todo recheck $this->viewSvg($data);
    if ($blazies->use('theme_blazy')) {
      $this->themeBlazy($data, $captions, $delta);
    }
    else {
      // @todo remove at 3.x.
      $this->themeItem($data, $captions, $delta);
    }
  }

  /**
   * Returns available fields for select options.
   */
  protected function getDefinedFieldOptions(array $defined_options = []): array {
    $field_names = $this->displayHandler->getFieldLabels();
    $definition = [];
    $stages = [
      'blazy_media',
      'block_field',
      'colorbox',
      'entity_reference_entity_view',
      'gridstack_file',
      'gridstack_media',
      'photobox',
      'video_embed_field_video',
      'youtube_video',
    ];

    // Formatter based fields.
    $options = [];
    foreach ($this->displayHandler->getOption('fields') as $field => $handler) {
      // This is formatter based type, not actual field type.
      if ($formatter = ($handler['type'] ?? NULL)) {
        switch ($formatter) {
          // @todo recheck other reasonable image-related formatters.
          case 'blazy':
          case 'image':
          case 'media':
          case 'media_thumbnail':
          case 'intense':
          case 'responsive_image':
          case 'svg_image_field_formatter':
          case 'video_embed_field_thumbnail':
          case 'video_embed_field_colorbox':
          case 'youtube_thumbnail':
            $options['images'][$field] = $field_names[$field];
            $options['overlays'][$field] = $field_names[$field];
            $options['thumbnails'][$field] = $field_names[$field];
            break;

          case 'list_key':
            $options['layouts'][$field] = $field_names[$field];
            break;

          case 'entity_reference_label':
          case 'text':
          case 'string':
          case 'link':
            $options['links'][$field] = $field_names[$field];
            $options['titles'][$field] = $field_names[$field];
            if ($formatter != 'link') {
              $options['thumb_captions'][$field] = $field_names[$field];
            }
            break;
        }

        $classes = ['list_key', 'entity_reference_label', 'text', 'string'];
        if (in_array($formatter, $classes)) {
          $options['classes'][$field] = $field_names[$field];
        }

        // Allows nested sliders.
        $sliders = strpos($formatter, 'slick') !== FALSE
          || strpos($formatter, 'splide') !== FALSE;
        if ($sliders || in_array($formatter, $stages)) {
          $options['overlays'][$field] = $field_names[$field];
        }

        // Allows advanced formatters/video as the main image replacement.
        // They are not reasonable for thumbnails, but main images.
        // Note: Certain Responsive image has no ID at Views, possibly a bug.
        if (in_array($formatter, $stages)) {
          $options['images'][$field] = $field_names[$field];
        }
      }

      // Content: title is not really a field, unless title.module installed.
      if (isset($handler['field'])) {
        if ($handler['field'] == 'title') {
          $options['classes'][$field] = $field_names[$field];
          $options['titles'][$field] = $field_names[$field];
          $options['thumb_captions'][$field] = $field_names[$field];
        }

        if ($handler['field'] == 'rendered_entity') {
          $options['images'][$field] = $field_names[$field];
          $options['overlays'][$field] = $field_names[$field];
        }

        if (in_array($handler['field'], ['nid', 'nothing', 'view_node'])) {
          $options['links'][$field] = $field_names[$field];
          $options['titles'][$field] = $field_names[$field];
        }

        if (in_array($handler['field'], ['created'])) {
          $options['classes'][$field] = $field_names[$field];
        }

        $blazies = strpos($handler['field'], 'blazy_') !== FALSE;
        if ($blazies) {
          $options['images'][$field] = $field_names[$field];
          $options['overlays'][$field] = $field_names[$field];
          $options['thumbnails'][$field] = $field_names[$field];
        }
      }

      // Captions can be anything to get custom works going.
      $options['captions'][$field] = $field_names[$field];
    }

    $definition['plugin_id'] = $this->getPluginId();
    $definition['settings'] = $this->options;
    $definition['_views'] = TRUE;

    // Provides the requested fields based on available $options.
    foreach ($defined_options as $key) {
      $definition[$key] = $options[$key] ?? [];
    }

    $contexts = [
      'handler' => $this->displayHandler,
      'view' => $this->view,
    ];
    $this->manager->moduleHandler()->alter('blazy_views_field_options', $definition, $contexts);

    return $definition;
  }

  /**
   * Returns an array of views for option list.
   *
   * Cannot use Views::getViewsAsOptions() as we need to limit to something.
   */
  protected function getViewsAsOptions($plugin = 'html_list'): array {
    if (!isset($this->viewsOptions[$plugin])) {
      $options = [];

      // Convert list of objects to options for the form.
      foreach (Views::getEnabledViews() as $name => $view) {
        foreach ($view->get('display') as $id => $display) {
          $valid = ($display['display_options']['style']['type'] ?? NULL) == $plugin;
          if ($valid) {
            $label = $view->label() . ' (' . $display['display_title'] . ')';
            $options[$name . ':' . $id] = Html::escape($label);
          }
        }
      }
      $this->viewsOptions[$plugin] = $options;
    }
    return $this->viewsOptions[$plugin];
  }

  /**
   * Builds the item using theme_blazy(), if so-configured.
   *
   * This is the future implementation after mergers at/by 3.x.
   *
   * @todo remove for BlazyElementTrait if similar to field formatters.
   */
  private function themeBlazy(array &$element, array $captions, $delta): void {
    $internal = $element;

    // Allows sub-modules to use theme_blazy() as their theme_ITEM() contents.
    if ($texts = $this->toBlazy($internal, $captions, $delta)) {
      $internal['captions'] = $texts;
    }

    if ($blazy = $this->formatter->getBlazy($internal)) {
      $element[static::$itemId] = $blazy;
      $this->formatter->postBlazy($element, $blazy);
    }
    unset($element['content']);
  }

  /**
   * This is the current implementation before mergers at 3.x.
   *
   * Looks simpler, yet it has lots of dup efforts downstream.
   *
   * @todo remove for BlazyElementTrait if similar to field formatters.
   * @todo remove regardless at 3.x.
   */
  private function themeItem(array &$element, array $captions, $delta): void {
    $internal = $element;

    if ($blazy = $this->formatter->getBlazy($internal)) {
      $element[static::$itemId] = $blazy;
      $this->formatter->postBlazy($element, $blazy);
    }

    $element[static::$captionId] = $captions;
    unset($element['content']);
  }

  /**
   * Provides relevant attributes to feed into theme_blazy().
   *
   * @todo remove for BlazyElementTrait if similar to field formatters.
   */
  private function toBlazy(array &$data, array &$captions, $delta): array {
    // Call manager not formatter due to sub-module deviations.
    $this->manager->toBlazy($data, $captions, $delta);
    return $captions;
  }

  /**
   * Extract image style and url from blazy image formatter.
   */
  protected function withBlazyFormatter(array &$settings, array $rendered, $index): void {
    // Pass Blazy field formatter settings into Views style plugin.
    // This allows richer contents such as multimedia/ lightbox for free.
    // Yet, ensures the Views style plugin wins over Blazy formatter,
    // such as with GridStack which may have its own breakpoints.
    $newbies   = $this->manager->toHashtag($rendered['#build']);
    $formatter = array_filter($newbies);
    $settings  = array_merge($formatter, array_filter($settings));

    // Reserves crucial blazy specific settings.
    Internals::preserve($settings, $formatter);

    // Each blazy delta is always 0 within a view, this makes it gallery.
    $blazies = $settings['blazies'];
    $blazies->merge($formatter['blazies']->storage());
    $blazies->set('delta', $index)
      ->set('is.gallery', !empty($settings['media_switch']));

    $tn  = $blazies->get('thumbnail.uri', 'x');
    $uri = $blazies->get('image.uri');

    // Views Media thumbnail may not have expected thumbnail URI, override.
    if ($uri && strpos($tn, 'media-icons') !== FALSE) {
      if ($tn_style = $settings['thumbnail_style']) {
        $uri = $this->manager->load($tn_style, 'image_style')->buildUri($uri);
        $blazies->set('thumbnail.id', $tn_style);
      }

      $blazies->set('thumbnail.uri', $uri)
        ->set('thumbnail.item', $rendered['#item']);
    }
  }

  /**
   * Extract image style and url from core image formatter.
   */
  protected function withImageFormatter(array &$settings, array $rendered, $index): void {
    $blazies = $settings['blazies'];
    // Deals with "link to content/image" by formatters.
    $url = $rendered['#url'] ?? '';

    // Checks if an object.
    if ($url instanceof Url) {
      $url = $url->setAbsolute()->toString();
    }

    // Prevent images from having absurd height when being lazyloaded.
    // Allows to disable it by _noratio such as enforced CSS background.
    $noratio = $settings['_noratio'] ?? '';
    $settings['ratio'] = $blazies->get('is.noratio', $noratio) ? '' : 'fluid';

    if (empty($settings['media_switch']) && $url) {
      $settings['media_switch'] = 'content';
      $blazies->set('switch', 'content');
    }

    $blazies->set('delta', $index)
      ->set('entity.url', $url);
  }

}
