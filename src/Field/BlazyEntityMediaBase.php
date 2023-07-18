<?php

namespace Drupal\blazy\Field;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\blazy\BlazyDefault;

/**
 * Base class for Media entity reference formatters with field details.
 *
 * @see \Drupal\blazy\Field\BlazyEntityReferenceBase
 */
abstract class BlazyEntityMediaBase extends BlazyEntityVanillaBase {

  use BlazyDependenciesTrait;

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    if (isset($element['media_switch'])) {
      $element['media_switch']['#options']['rendered'] = $this->t('Image rendered by its formatter');
      $element['media_switch']['#description'] .= ' ' . $this->t('<b>Image rendered</b> requires <b>Image</b> option filled out and is useful if the formmater offers awesomeness that Blazy does not have but still wants Blazy for a Grid, etc. Be sure the enabled fields here are not hidden/ disabled at its view mode.');
    }

    if (isset($element['caption'])) {
      $element['caption']['#description'] = $this->t('Check fields to be treated as captions, even if not caption texts.');
    }

    if (isset($element['image']['#description'])) {
      $element['image']['#description'] .= ' ' . $this->t('For (remote|local) video, this allows separate high-res or poster image. Be sure this exact same field is also used for bundle <b>Image</b> to have a mix of videos and images if this entity is Media. Leaving it empty will fallback to the video provider thumbnails, or no poster for local video. The formatter/renderer is managed by <strong>@plugin_id</strong> formatter. Meaning original formatter ignored.', ['@plugin_id' => $this->getPluginId()]);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildElements(array &$build, $entities, $langcode) {
    parent::buildElements($build, $entities, $langcode);

    $settings = $this->formatter->toHashtag($build);
    $blazies  = $settings['blazies'];
    $item_id  = $blazies->get('item.id');

    // Some formatter has a toggle Vanilla.
    if (empty($settings['vanilla'])) {
      // Supports Blazy formatter multi-breakpoint images if available.
      if ($item = ($build['items'][0] ?? NULL)) {
        $fallback = $item[$item_id]['#build'] ?? [];
        $data = $item['#build'] ?? $fallback;
        if ($data) {
          $blazies->set('first.data', $data);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareElement(array &$build, $entity, $langcode, $delta): void {
    parent::prepareElement($build, $entity, $langcode, $delta);

    $settings  = $this->formatter->toHashtag($build);
    $blazies   = $settings['blazies'];
    $item_id   = $blazies->get('item.id');
    $view_mode = $settings['view_mode'] ?? 'full';
    $is_nav    = $blazies->is('nav') || !empty($settings['nav']);
    $switch    = $settings['media_switch'] ?? NULL;

    // Bail out if vanilla (rendered entity) is required.
    if (!empty($settings['vanilla'])) {
      return;
    }

    // Otherwise hard work which is meant to reduce custom code at theme level.
    $element = [
      '#entity'   => $entity,
      '#settings' => $settings,
      '#delta'    => $delta,
      '#item'      => NULL,
    ];

    // Build media item including custom highres video thumbnail.
    $this->blazyOembed->build($element);

    // Captions if so configured, including Blazy formatters.
    $this->getCaption($element, $entity, $langcode);

    // If `Image rendered` is picked, render image as is. Might not be Blazy's
    // formatter, yet has awesomeness that Blazy doesn't, but still wants to be
    // embedded in Blazy ecosytem mostly for Grid, Slider, Mason, GridStack etc.
    if (!empty($settings['image']) && $switch == 'rendered') {
      $element['content'][] = BlazyField::view($entity, $settings['image'], $view_mode);
    }

    // Optional image with responsive image, lazyLoad, and lightbox supports.
    // Including potential rich Media contents: local video, Facebook, etc.
    $blazy = $this->formatter->getBlazy($element);

    // If the caller is Blazy, provides simple index elements.
    if ($blazies->get('namespace') == 'blazy') {
      $build['items'][$delta] = $blazy;
    }
    else {
      // Otherwise Slick, GridStack, Mason, etc. may need more elements.
      $element[$item_id] = $blazy;

      // Update with blazy processed settings such as unstyled extensions.
      $item_build = $blazy['#build'] ?? [];
      if ($blazysets = $this->formatter->toHashtag($item_build)) {
        $element['#settings']['blazies']->merge($blazysets['blazies']->storage());
      }

      // Provides extra elements.
      $this->buildElementExtra($element, $entity, $langcode);

      // Build the main item.
      $build['items'][$delta] = $element;

      // Build the thumbnail item.
      if ($is_nav) {
        $this->buildElementThumbnail($build, $element, $entity, $delta);
      }
    }
  }

  /**
   * Build extra elements.
   */
  protected function buildElementExtra(array &$element, $entity, $langcode) {
    // Do nothing, let extenders do their jobs.
  }

  /**
   * Build thumbnail navigation such as for Slick asnavfor.
   */
  protected function buildElementThumbnail(array &$build, $element, $entity, $delta) {
    // Do nothing, let extenders do their jobs.
  }

  /**
   * Builds captions with possible multi-value fields.
   */
  protected function getCaption(array &$element, $entity, $langcode) {
    $settings  = $this->formatter->toHashtag($element);
    $item      = $this->formatter->toHashtag($element, 'item', NULL);
    $blazies   = $settings['blazies'];
    $view_mode = $settings['view_mode'] ?? 'full';
    $is_blazy  = $blazies->get('namespace') == 'blazy';
    $weights   = $caption_items = [];
    $_weight   = FALSE;

    // Title can be plain text, or link field.
    if ($_title = $settings['title'] ?? NULL) {
      $output = [];
      // If title is available as a field.
      if (isset($entity->{$_title})) {
        $output = BlazyField::getTextOrLink($entity, $_title, $view_mode, $langcode);
      }
      // Else fallback to image title property.
      elseif ($item && $_title == 'title') {
        // Respects both fake and real image item.
        if ($caption = ($item->title ?? NULL)) {
          $caption = Xss::filter($caption, BlazyDefault::TAGS);
          $output = ['#markup' => trim($caption)];
        }
      }

      if ($output) {
        // @todo recheck to make it similar to sub-modules.
        if ($is_blazy) {
          $_weight = TRUE;
          $weights[] = 0;
          $caption_items['title'] = $output;
        }
        else {
          $element['caption']['title'] = $output;
        }
      }
    }

    // The caption fields common to all entity formatters, if so configured.
    if ($field_captions = $settings['caption'] ?? []) {
      foreach ($field_captions as $name => $field_caption) {
        /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
        if ($item) {
          // Provides basic captions based on image attributes (Alt, Title).
          foreach (['title', 'alt'] as $key => $attribute) {
            $value = $item->{$attribute} ?? '';
            if ($name == $attribute && $caption = trim($value)) {
              $markup = Xss::filter($caption, BlazyDefault::TAGS);
              if ($name == 'alt') {
                $markup = '<p>' . $markup . '</p>';
              }
              $caption_items[$name] = ['#markup' => $markup];
              $weights[] = $_weight ? ($key + 1) : $key;
            }
          }
        }

        // Provides fieldable captions.
        if ($markup = BlazyField::view($entity, $field_caption, $view_mode)) {
          if (isset($markup['#weight'])) {
            $weights[] = $markup['#weight'];
          }

          $caption_items[$name] = $markup;
        }
      }
    }

    if ($caption_items) {
      // @fixme broken sometimes.
      if ($weights) {
        array_multisort($weights, SORT_ASC, $caption_items);
      }
      // @todo recheck to make it similar to sub-modules if any issues at 3.x.
      // The most obvious was seen at BlazyFileFormatterBase where sub-modules
      // don't want to pass captions to theme_blazy() for their own markups.
      if ($is_blazy) {
        $element['captions'] = $caption_items;
      }
      else {
        $element['caption']['data'] = $caption_items;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    $bundles  = $this->getAvailableBundles();
    $captions = $this->getFieldOptions();
    $_texts   = ['text', 'text_long', 'string', 'string_long', 'link'];
    $titles   = $this->getFieldOptions($_texts);
    $images   = [];

    if ($bundles) {
      // @todo figure out to not hard-code stock bundle image.
      if (in_array('image', array_keys($bundles))) {
        $captions['title'] = $titles['title'] = $this->t('Image Title');
        $captions['alt'] = $this->t('Image Alt');
      }

      // Only provides poster if media contains rich media.
      $media = BlazyDefault::imagePosters();
      if (count(array_intersect(array_keys($bundles), $media)) > 0) {
        $images['images'] = $this->getFieldOptions(['image']);
      }
    }

    // @todo better way than hard-coding field name.
    unset($captions['field_image'], $captions['field_media_image']);

    return [
      'background'        => TRUE,
      'captions'          => $captions,
      'fieldable_form'    => TRUE,
      'image_style_form'  => TRUE,
      'media_switch_form' => TRUE,
      'multimedia'        => TRUE,
      'no_layouts'        => FALSE,
      'no_image_style'    => FALSE,
      'responsive_image'  => TRUE,
      'thumbnail_style'   => TRUE,
      'titles'            => $titles,
    ] + $images
      + parent::getPluginScopes();
  }

}
