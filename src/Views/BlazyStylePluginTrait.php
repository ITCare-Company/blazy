<?php

namespace Drupal\blazy\Views;

use Drupal\Core\Url;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\blazy\BlazyInternal;

/**
 * A Trait common for optional views style plugins.
 *
 * @todo move some into base classes unless clear like BlazyStyleOptionsTrait.
 * No sub-modules call this, safe to move it into BlazyStylePluginBase.
 */
trait BlazyStylePluginTrait {

  /**
   * Returns the modified renderable image_formatter to support lazyload.
   */
  protected function getImageRenderable(array &$settings, $row, $index): array {
    $blazies = $settings['blazies'];
    $image = $this->getImageArray($row, $index, $settings['image']);
    $rendered = $image['rendered'] ?? [];

    // Supports 'group_rows' option.
    // @todo recheck if any side issues for not having raw key.
    if (!$rendered) {
      return $image;
    }

    // If the image has #item property, lazyload may work, otherwise skip.
    // This hustle is to lazyload tons of images -- grids, large galleries,
    // gridstack, mason, with multimedia/ lightboxes for free.
    /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
    /* @phpstan-ignore-next-line */
    if ($item = $this->getImageItem($image)) {
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
          // Pass Blazy field formatter settings into Views style plugin.
          // This allows richer contents such as multimedia/ lightbox for free.
          // Yet, ensures the Views style plugin wins over Blazy formatter,
          // such as with GridStack which may have its own breakpoints.
          $newbies = $this->blazyManager->toHashtag($rendered['#build']);
          $blazy_settings = array_filter($newbies);
          $settings = array_merge($blazy_settings, array_filter($settings));

          // Reserves crucial blazy specific settings.
          BlazyInternal::preserve($settings, $blazy_settings);

          // Each blazy delta is always 0 within a view, this makes it gallery.
          $settings['blazies'] = $blazy_settings['blazies'];
          $settings['blazies']->set('delta', $index)
            ->set('is.gallery', !empty($settings['media_switch']));
        }
        elseif ($theme == 'image_formatter') {
          // Deals with "link to content/image" by formatters.
          $url = $rendered['#url'] ?? '';

          // Checks if an object.
          if ($url instanceof Url) {
            $url = $url->setAbsolute()->toString();
          }

          $blazies->set('entity.url', $url);

          // Prevent images from having absurd height when being lazyloaded.
          // Allows to disable it by _noratio such as enforced CSS background.
          $noratio = $settings['_noratio'] ?? '';
          $settings['ratio'] = $blazies->get('is.noratio', $noratio) ? '' : 'fluid';
          if (empty($settings['media_switch']) && $url) {
            $settings['media_switch'] = 'content';
          }

          $blazies->set('delta', $index);

          // Rebuilds the image for the brand new richer Blazy.
          // With the working Views cache, nothing to worry much.
          $build = ['#item' => $item, '#settings' => $settings];
          $image['rendered'] = $this->blazyManager->getBlazy($build);
        }
      }
    }

    return $image;
  }

  /**
   * Checks if we can work with this formatter, otherwise no go if flattened.
   */
  protected function getImageArray($row, $index, $field_image): array {
    if (!empty($field_image)
      && $image = $this->getFieldRenderable($row, $index, $field_image)) {

      // Known image formatters: Blazy, Image, etc. which provides ImageItem.
      // Else dump Video embed thumbnail/video/colorbox as is.
      if ($this->getImageItem($image) || isset($image['rendered'])) {
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
  protected function getImageItem($image): ?ImageItem {
    $item = NULL;

    if ($rendered = ($image['rendered'] ?? [])) {
      // Image formatter.
      $item = $rendered['#item'] ?? NULL;

      // Blazy formatter, also supports multiple, `group_rows`.
      if ($build = ($rendered['#build'] ?? [])) {
        $item = $this->blazyManager->toHashtag($build, 'item') ?: $item;
        $item = $build[0]['#item'] ?? $item;
      }
    }

    // Don't know other reasonable formatters to work with.
    return $item instanceof ImageItem ? $item : NULL;
  }

  /**
   * Returns the caption elements.
   */
  protected function getCaption($index, array $settings): array {
    $view     = $this->view;
    $items    = [];
    $keys     = array_keys($view->field);
    $keys     = array_combine($keys, $keys);
    $link     = $settings['link'] ?? NULL;
    $title    = $settings['title'] ?? NULL;
    $overlay  = $settings['overlay'] ?? NULL;
    $captions = $settings['caption'] ?? [];

    // Caption items: link, title, overlay, and data, anything else selected.
    $items['link']    = $this->getFieldRendered($index, $link);
    $items['title']   = $this->getFieldRendered($index, $title, TRUE);
    $items['overlay'] = $this->getFieldRendered($index, $overlay);

    // Exclude non-caption fields so that theme_views_view_fields() kicks in
    // and only render expected caption fields. As long as not-hidden, each
    // caption field should be wrapped with Views markups.
    if ($captions) {
      $excludes = array_diff_assoc($keys, $captions);
      foreach ($excludes as $field) {
        $view->field[$field]->options['exclude'] = TRUE;
      }

      $items['data'] = $view->rowPlugin->render($view->result[$index]);
    }

    return $items;
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

}
