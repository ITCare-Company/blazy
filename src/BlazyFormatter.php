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
  private $isDimensionSet;

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

    $settings                   += $this->getCommonSettings();
    $settings['bundle']          = $bundle;
    $settings['cache_metadata']  = ['keys' => [$id, $count]];
    $settings['content_url']     = $settings['absolute_path'] = $absolute_path;
    $settings['count']           = $count;
    $settings['entity_id']       = $entity_id;
    $settings['entity_type_id']  = $entity_type_id;
    $settings['field_type']      = $field_type;
    $settings['field_name']      = $field_name;
    $settings['gallery_id']      = str_replace('_', '-', $gallery_id . '-' . $switch);
    $settings['id']              = $id;
    $settings['internal_path']   = $internal_path;
    $settings['lightbox']        = ($switch && in_array($switch, $this->getLightboxes())) ? $switch : FALSE;
    $settings['resimage']        = function_exists('responsive_image_get_image_dimensions') && !empty($settings['responsive_image']) && !empty($settings['responsive_image_style']);
    $settings['target_type']     = $target_type;
    $settings['resimage_entity'] = $settings['resimage'] ? $this->entityLoad($settings['responsive_image_style'], 'responsive_image_style') : NULL;

    unset($entity, $field);

    if (!empty($settings['vanilla'])) {
      $settings = array_filter($settings);
      return;
    }

    // Don't bother if using Responsive image.
    $settings['breakpoints'] = isset($settings['breakpoints']) && empty($settings['responsive_image_style']) ? $settings['breakpoints'] : [];
    $settings['caption']     = empty($settings['caption']) ? [] : array_filter($settings['caption']);
    $settings['background']  = empty($settings['responsive_image_style']) && !empty($settings['background']);
    $settings['blazy']       = $settings['resimage'] || !empty($settings['blazy']);

    // Let Blazy handle CSS background as Slick's background is deprecated.
    if ($settings['background']) {
      $settings['blazy'] = TRUE;
    }

    if ($settings['blazy']) {
      $settings['lazy'] = 'blazy';
    }

    // @todo remove enforced (BC), since now works for Responsive image too.
    if (isset($settings['ratio']) && $settings['ratio'] == 'enforced') {
      $settings['ratio'] = 'fluid';
    }

    // Add the entity to formatter cache tags.
    $settings['cache_tags'][] = $settings['entity_type_id'] . ':' . $settings['entity_id'];
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
    BlazyBreakpoint::cleanUpBreakpoints($settings);
    if (!empty($settings['first_uri'])) {
      if (empty($settings['resimage'])) {
        $this->setDimensionsOnce($settings, $this->firstItem);
      }
      elseif (!empty($settings['resimage_entity']) && $settings['ratio'] == 'fluid') {
        $this->setResponsiveImageDimensions($settings);
      }
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
  }

  /**
   * {@inheritdoc}
   */
  public function setDimensionsOnce(array &$settings = [], $item = NULL) {
    if (!isset($this->isDimensionSet[md5($settings['first_uri'])])) {
      $dimensions['width']  = $settings['original_width'] = $item && isset($item->width) ? $item->width : NULL;
      $dimensions['height'] = $settings['original_height'] = $item && isset($item->height) ? $item->height : NULL;

      // If image style contains crop, sets dimension once, and let all inherit.
      if (!empty($settings['image_style']) && ($style = BlazyBreakpoint::isCrop($settings['image_style']))) {
        $style->transformDimensions($dimensions, $settings['first_uri']);

        $settings['height'] = (int) $dimensions['height'];
        $settings['width']  = (int) $dimensions['width'];

        // Informs individual images that dimensions are already set once.
        $settings['_dimensions'] = TRUE;
      }

      // Also sets breakpoint dimensions once, if cropped.
      if (!empty($settings['breakpoints'])) {
        BlazyBreakpoint::buildDataBlazy($settings, $item);
      }

      $this->isDimensionSet[md5($settings['first_uri'])] = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setResponsiveImageDimensions(array &$settings = []) {
    if (!isset($this->isResponsiveImageDimensionSet[md5($settings['first_uri'])])) {
      $item = $this->firstItem;
      $styles = $this->getResponsiveImageStyles($settings['resimage_entity'], TRUE);

      $srcset = [];
      $width = empty($item) ? NULL : $item->width;
      $height = empty($item) ? NULL : $item->height;
      foreach ($styles as $name => $style) {
        $dimensions = ['width' => $width, 'height' => $height];
        $style->transformDimensions($dimensions, $settings['first_uri']);

        // Sometimes they are string, cast them integer to reduce JS logic.
        $dimensions['width'] = intval($dimensions['width']);
        $dimensions['height'] = intval($dimensions['height']);

        // In order to avoid layout reflows, we get dimensions beforehand.
        $padding = round((($dimensions['height'] / $dimensions['width']) * 100), 2);
        $srcset[intval($dimensions['width'])] = $padding;
      }

      // Sort the srcset from small to large image width or multiplier.
      ksort($srcset);
      $settings['blazy_data']['dimensions'] = $srcset;

      $this->isResponsiveImageDimensionSet[md5($settings['first_uri'])] = TRUE;
    }
  }

}
