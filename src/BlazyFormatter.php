<?php

namespace Drupal\blazy;

/**
 * Implements BlazyFormatterInterface.
 */
class BlazyFormatter extends BlazyManager implements BlazyFormatterInterface {

  /**
   * The first image item found.
   *
   * @var object
   */
  protected $firstItem = NULL;

  /**
   * Checks if image dimensions are set.
   *
   * @var array
   */
  private $isImageDimensionSet;

  /**
   * Checks if Responsive image dimensions are set.
   *
   * @var array
   */
  private $isResponsiveImageDimensionSet;

  /**
   * {@inheritdoc}
   */
  public function buildSettings(array &$build, $items) {
    $settings       = &$build['settings'];
    $count          = $items->count();
    $field          = $items->getFieldDefinition();
    $entity         = $items->getEntity();
    $entity_type_id = $entity->getEntityTypeId();
    $entity_id      = $entity->id();
    $bundle         = $entity->bundle();
    $field_name     = $field->getName();
    $field_type     = $field->getType();
    $field_clean    = str_replace("field_", '', $field_name);
    $target_type    = $field->getFieldStorageDefinition()->getSetting('target_type');
    $view_mode      = empty($settings['current_view_mode']) ? '_custom' : $settings['current_view_mode'];
    $namespace      = $settings['namespace'] = empty($settings['namespace']) ? 'blazy' : $settings['namespace'];
    $id             = isset($settings['id']) ? $settings['id'] : '';
    $gallery_id     = "{$namespace}-{$entity_type_id}-{$bundle}-{$field_clean}-{$view_mode}";
    $id             = Blazy::getHtmlId("{$gallery_id}-{$entity_id}", $id);
    $switch         = empty($settings['media_switch']) ? '' : $settings['media_switch'];
    $internal_path  = $absolute_path = NULL;

    // Deals with UndefinedLinkTemplateException such as paragraphs type.
    // @see #2596385, or fetch the host entity.
    if (!$entity->isNew() && method_exists($entity, 'hasLinkTemplate')) {
      if ($entity->hasLinkTemplate('canonical')) {
        $url = $entity->toUrl();
        $internal_path = $url->getInternalPath();
        $absolute_path = $url->setAbsolute()->toString();
      }
    }

    $settings                  += $this->getCommonSettings();
    $settings['bundle']         = $bundle;
    $settings['cache_metadata'] = ['keys' => [$id, $count]];
    $settings['content_url']    = $settings['absolute_path'] = $absolute_path;
    $settings['count']          = $count;
    $settings['entity_id']      = $entity_id;
    $settings['entity_type_id'] = $entity_type_id;
    $settings['field_type']     = $field_type;
    $settings['field_name']     = $field_name;
    $settings['gallery_id']     = str_replace('_', '-', $gallery_id . '-' . $switch);
    $settings['id']             = $id;
    $settings['internal_path']  = $internal_path;
    $settings['target_type']    = $target_type;
    $settings['lightbox']       = ($switch && in_array($switch, $this->getLightboxes())) ? $switch : FALSE;
    $settings['resimage']       = function_exists('responsive_image_get_image_dimensions') && !empty($settings['responsive_image']) && !empty($settings['responsive_image_style']);
    $settings['resimage']       = $settings['resimage'] ? $this->entityLoad($settings['responsive_image_style'], 'responsive_image_style') : FALSE;
    $settings['cache_tags'][]   = $settings['entity_type_id'] . ':' . $settings['entity_id'];
    $settings['caption']        = empty($settings['caption']) ? [] : array_filter($settings['caption']);
    $settings['is_preview']     = Blazy::isPreview();

    unset($entity, $field);

    if (!empty($settings['vanilla'])) {
      $settings = array_filter($settings);
      return;
    }

    // Don't bother if using Responsive image.
    // @todo TBD; for keeping or removal at blazy:8.x-2.0.
    $settings['breakpoints'] = isset($settings['breakpoints']) && empty($settings['responsive_image_style']) ? $settings['breakpoints'] : [];
    BlazyBreakpoint::cleanUpBreakpoints($settings);

    // Lazy load types: blazy, and slick: ondemand, anticipated, progressive.
    $settings['background'] = empty($settings['responsive_image_style']) && !empty($settings['background']);
    $settings['blazy']      = !empty($settings['blazy']) || $settings['background'] || !empty($settings['resimage']) || !empty($settings['breakpoints']);
    $settings['lazy']       = $settings['blazy'] ? 'blazy' : (isset($settings['lazy']) ? $settings['lazy'] : '');
    $settings['lazy']       = empty($settings['is_preview']) ? $settings['lazy'] : '';

    // @todo remove enforced (BC), since now works for Responsive image too.
    if (isset($settings['ratio']) && $settings['ratio'] == 'enforced') {
      $settings['ratio'] = 'fluid';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preBuildElements(array &$build, $items, array $entities = []) {
    $this->buildSettings($build, $items);
    $settings = &$build['settings'];

    // Pass first item to optimize sizes this time.
    if (isset($items[0]) && $item = $items[0]) {
      $this->extractFirstItem($settings, $item, reset($entities));
    }

    // Sets dimensions once, if cropped, to reduce costs with ton of images.
    // This is less expensive than re-defining dimensions per image.
    if (!empty($settings['first_uri'])) {
      if (empty($settings['resimage'])) {
        $this->setImageDimensions($settings);
      }
      elseif (!empty($settings['resimage']) && $settings['ratio'] == 'fluid') {
        $this->setResponsiveImageDimensions($settings);
      }
    }

    if (!empty($settings['use_ajax'])) {
      $settings['blazy_data']['useAjax'] = TRUE;
    }

    // Allows altering the settings.
    $this->getModuleHandler()->alter('blazy_settings', $build, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function postBuildElements(array &$build, $items, array $entities = []) {
    // Rebuild the first item to build colorbox/zoom-like gallery.
    $build['settings']['first_item'] = $this->firstItem;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFirstItem(array &$settings, $item, $entity = NULL) {
    if ($settings['field_type'] == 'image') {
      $this->firstItem = $item;
      $settings['first_uri'] = ($file = $item->entity) && empty($item->uri) ? $file->getFileUri() : $item->uri;
    }
    elseif ($entity && $entity->hasField('thumbnail') && $image = $entity->get('thumbnail')->first()) {
      $this->firstItem = $image;
      $settings['first_uri'] = $image->entity->getFileUri();
    }

    // The first image dimensions to differ from individual item dimensions.
    Blazy::imageDimensions($settings, $this->firstItem, TRUE);
  }

  /**
   * Sets dimensions once to reduce method calls, if image style contains crop.
   *
   * @param array $settings
   *   The settings being modified.
   */
  protected function setImageDimensions(array &$settings = []) {
    if (!isset($this->isImageDimensionSet[md5($settings['id'])])) {
      // If image style contains crop, sets dimension once, and let all inherit.
      if (!empty($settings['image_style']) && ($style = $this->isCrop($settings['image_style']))) {
        $settings = array_merge($settings, Blazy::transformDimensions($style, $settings, TRUE));

        // Informs individual images that dimensions are already set once.
        $settings['_dimensions'] = TRUE;
      }

      // Also sets breakpoint dimensions once, if cropped.
      // @todo TBD; for keeping or removal at blazy:8.x-2.0.
      if (!empty($settings['breakpoints'])) {
        BlazyBreakpoint::buildDataBlazy($settings, $this->firstItem);
      }

      $this->isImageDimensionSet[md5($settings['id'])] = TRUE;
    }
  }

  /**
   * Sets dimensions once to reduce method calls for Responsive image.
   *
   * @param array $settings
   *   The settings being modified.
   */
  protected function setResponsiveImageDimensions(array &$settings = []) {
    if (!isset($this->isResponsiveImageDimensionSet[md5($settings['id'])])) {
      $srcset = [];
      foreach ($this->getResponsiveImageStyles($settings['resimage'], TRUE) as $style) {
        $settings = array_merge($settings, Blazy::transformDimensions($style, $settings, TRUE));

        // In order to avoid layout reflow, we get dimensions beforehand.
        $srcset[intval($settings['width'])] = round((($settings['height'] / $settings['width']) * 100), 2);
      }

      // Sort the srcset from small to large image width or multiplier.
      ksort($srcset);
      $settings['blazy_data']['dimensions'] = $srcset;

      $this->isResponsiveImageDimensionSet[md5($settings['id'])] = TRUE;
    }
  }

  /**
   * Deprecated method.
   *
   * @deprecated in blazy:8.x-2.0 and is removed from blazy:8.x-3.0. Use
   *   self::setImageDimensions() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function setDimensionsOnce(array &$settings = []) {
    @trigger_error('setDimensionsOnce is deprecated in blazy:8.x-2.0 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\BlazyFormatter::setImageDimensions() instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);
    $this->setImageDimensions($settings);
  }

}
