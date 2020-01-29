<?php

namespace Drupal\blazy\Dejavu;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\blazy\BlazyDefault;

/**
 * Base class for Media entity reference formatters with field details.
 *
 * @see \Drupal\blazy\Dejavu\BlazyEntityReferenceBase
 */
abstract class BlazyEntityMediaBase extends BlazyEntityBase {

  use BlazyDependenciesTrait;

  /**
   * Returns the slick service.
   */
  public function blazyEntity() {
    return $this->blazyEntity;
  }

  /**
   * Returns the slick service.
   */
  public function blazyOembed() {
    return $this->blazyOembed;
  }

  /**
   * Returns media contents.
   */
  public function buildElements(array &$build, $entities, $langcode) {
    parent::buildElements($build, $entities, $langcode);
    $settings = &$build['settings'];
    $item_id = $settings['item_id'];

    // Some formatter has a toggle Vanilla.
    if (empty($settings['vanilla'])) {
      $settings['check_blazy'] = TRUE;

      // Supports Blazy formatter multi-breakpoint images if available.
      if (isset($build['items'][0]) && $item = $build['items'][0]) {
        $fallback = isset($item[$item_id]['#build']) ? $item[$item_id]['#build'] : [];
        $settings['first_image'] = isset($item['#build']) ? $item['#build'] : $fallback;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildElement(array &$build, $entity, $langcode) {
    $settings  = &$build['settings'];
    $item_id   = $settings['item_id'];
    $view_mode = $settings['view_mode'] = empty($settings['view_mode']) ? 'full' : $settings['view_mode'];

    if (!empty($settings['vanilla'])) {
      return parent::buildElement($build, $entity, $langcode);
    }

    $delta = $settings['delta'];
    $element = ['settings' => $settings];

    // Built early before stage to allow custom highres video thumbnail later.
    $this->blazyOembed()->getMediaItem($element, $entity);

    // Build the main stage with image options from highres video thumbnail.
    if (!empty($settings['image'])) {
      // If Image rendered is picked, render image as is.
      if (!empty($settings['media_switch']) && $settings['media_switch'] == 'rendered') {
        $element['content'][] = $this->blazyEntity()->getFieldRenderable($entity, $settings['image'], $view_mode);
      }
      // @todo recheck against file entity (non-media), if still needed.
      // @todo remove if no longer file entity is needed for pure Media, likely
      // post blazy:8.3+ since BlazyFileFormatter is already deprecated.
      elseif (empty($element['item']) && empty($settings['uri'])) {
        $this->buildStage($element, $entity, $langcode);
      }
    }

    // Captions if so configured, including Blazy formatters.
    $this->getCaption($element, $entity, $langcode);

    // @todo refactor to avoid this condition in the first place.
    // Optional image with responsive image, lazyLoad, and lightbox supports.
    $blazy = empty($element['item']) ? [] : $this->formatter()->getBlazy($element);

    // If the caller is Blazy, provides simple index elements.
    if ($settings['namespace'] == 'blazy') {
      $build['items'][$delta] = $blazy;
    }
    else {
      // Otherwise Slick, GridStack, Mason, etc. may need more elements.
      $element[$item_id] = $blazy;

      // Provides extra elements.
      $this->buildElementExtra($element, $entity, $langcode);

      // Build the main item.
      $build['items'][$delta] = $element;

      // Build the thumbnail item.
      if (!empty($settings['nav'])) {
        $this->buildElementThumbnail($build, $element, $entity, $delta);
      }
    }
  }

  /**
   * Build extra elements.
   */
  public function buildElementExtra(array &$element, $entity, $langcode) {
    // Do nothing, let extenders do their jobs.
  }

  /**
   * Build thumbnail navigation such as for Slick asnavfor.
   */
  public function buildElementThumbnail(array &$build, $element, $entity, $delta) {
    // Do nothing, let extenders do their jobs.
  }

  /**
   * Builds captions with possible multi-value fields.
   */
  public function getCaption(array &$element, $entity, $langcode) {
    $settings = $element['settings'];
    $view_mode = $settings['view_mode'];

    // The caption fields common to all entity formatters, if so configured.
    if (!empty($settings['caption'])) {
      $caption_items = $weights = [];
      foreach ($settings['caption'] as $name => $field_caption) {
        /** @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
        if ($item = $element['item']) {
          // Provides basic captions based on image attributes.
          foreach (['title', 'alt'] as $key => $attribute) {
            if ($name == $attribute && $caption = trim($item->get($attribute)->getString())) {
              $caption_items[$name] = ['#markup' => Xss::filter($caption, BlazyDefault::TAGS)];
              $weights[] = $key;
            }
          }
        }

        if ($caption = $this->blazyEntity()->getFieldRenderable($entity, $field_caption, $view_mode)) {
          if (isset($caption['#weight'])) {
            $weights[] = $caption['#weight'];
          }

          $caption_items[$name] = $caption;
        }
      }

      if ($caption_items) {
        if ($weights) {
          array_multisort($weights, SORT_ASC, $caption_items);
        }
        // Differenciate Blazy from Slick, GridStack, etc. to avoid collisions.
        if ($settings['namespace'] == 'blazy') {
          $element['captions'] = $caption_items;
        }
        else {
          $element['caption']['data'] = $caption_items;
        }
      }
    }
  }

  /**
   * Build the main background/stage, image or video.
   *
   * Main image can be separate image item from video thumbnail for highres.
   * Fallback to default thumbnail if any, which has no file API.
   *
   * @todo check if still needed for non-media post BlazyOEmbed::getMediaItem()
   *   since BlazyFileFormatter is already deprecated.
   * @todo deprecated in blazy:8.x-2.0 and is removed from blazy:8.x-3.0. Use
   *   \Drupal\blazy\BlazyOEmbed::getMediaItem() instead.
   */
  public function buildStage(array &$element, $entity, $langcode) {
    $settings = &$element['settings'];
    $stage = $settings['image'];

    // The actual video thumbnail has already been downloaded earlier.
    // This fetches the highres image if provided and available.
    // With a mix of image and video, image is not always there.
    /** @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $file */
    if (isset($entity->{$stage}) && $file = $entity->get($stage)) {
      $value = $file->getValue();

      // Do not proceed if it is a Media entity video.
      if (isset($value[0]) && !empty($value[0]['target_id'])) {
        // If image, even if multi-value, we can only have one stage per slide.
        if (method_exists($file, 'referencedEntities') && isset($file->referencedEntities()[0])) {
          /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
          $element['item'] = $file->get(0);

          // Collects cache tags to be added for each item in the field.
          $settings['file_tags'] = $file->referencedEntities()[0]->getCacheTags();
          $settings['uri'] = $file->referencedEntities()[0]->getFileUri();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    if (isset($element['media_switch'])) {
      $element['media_switch']['#options']['rendered'] = $this->t('Image rendered by its formatter');
      $element['media_switch']['#description'] .= ' ' . $this->t('Be sure the enabled fields here are not hidden/disabled at its view mode.');
    }

    if (isset($element['caption'])) {
      $element['caption']['#description'] = $this->t('Check fields to be treated as captions, even if not caption texts.');
    }

    if (isset($element['image']['#description'])) {
      $element['image']['#description'] .= ' ' . $this->t('For (remote|local) video, this allows separate high-res or poster image, be sure this exact same field is also used for bundle <b>Image</b> to have a mix of videos and images. Leaving it empty will fallback to the video provider thumbnails, or no poster for local video. The formatter/renderer is managed by <strong>@plugin_id</strong> formatter. Meaning original formatter ignored.', ['@plugin_id' => $this->getPluginId()]);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getScopedFormElements() {
    $target_type = $this->getFieldSetting('target_type');
    $views_ui    = $this->getFieldSetting('handler') == 'default';
    $bundles     = $views_ui ? [] : $this->getFieldSetting('handler_settings')['target_bundles'];
    $captions    = $this->admin()->getFieldOptions($bundles, [], $target_type);
    $images      = [];

    if ($bundles) {
      // @todo figure out to not hardcode stock bundle image.
      if (in_array('image', $bundles)) {
        $captions['title'] = $this->t('Image Title');
        $captions['alt'] = $this->t('Image Alt');
      }
      $media = ['audio', 'remote_video', 'video', 'instagram', 'soundcloud'];
      if (count(array_intersect($bundles, $media)) > 0) {
        $images['images'] = $this->admin()->getFieldOptions($bundles, ['image'], $target_type);
      }
    }

    // @todo better way than hard-coding field name.
    unset($captions['field_image'], $captions['field_media_image']);

    return [
      'background'        => TRUE,
      'box_captions'      => TRUE,
      'breakpoints'       => BlazyDefault::getConstantBreakpoints(),
      'captions'          => $captions,
      'fieldable_form'    => TRUE,
      'image_style_form'  => TRUE,
      'media_switch_form' => TRUE,
      'multimedia'        => TRUE,
    ] + parent::getScopedFormElements() + $images;
  }

}
