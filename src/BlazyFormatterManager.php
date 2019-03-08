<?php

namespace Drupal\blazy;

/**
 * Provides common field formatter-related methods: Blazy, Slick.
 */
class BlazyFormatterManager extends BlazyManager {

  /**
   * Returns the field formatter settings inherited by child elements.
   *
   * @param array $build
   *   The array containing: settings, or potential optionset for extensions.
   * @param object $items
   *   The items to prepare settings for.
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

    $settings['breakpoints']    = isset($settings['breakpoints']) && empty($settings['responsive_image_style']) ? $settings['breakpoints'] : [];
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
    $settings['lightbox']       = ($switch && in_array($switch, $this->getLightboxes())) ? $switch : FALSE;
    $settings['resimage']       = function_exists('responsive_image_get_image_dimensions');
    $settings['target_type']    = $target_type;

    unset($entity, $field);

    if (!empty($settings['vanilla'])) {
      $settings = array_filter($settings);
      return;
    }

    // Don't bother if using Responsive image.
    if (!empty($settings['breakpoints'])) {
      $this->cleanUpBreakpoints($settings);
    }

    $settings['caption']    = empty($settings['caption']) ? [] : array_filter($settings['caption']);
    $settings['background'] = empty($settings['responsive_image_style']) && !empty($settings['background']);
    $resimage_lazy          = $this->configLoad('responsive_image') && !empty($settings['responsive_image_style']);
    $settings['blazy']      = $resimage_lazy || !empty($settings['blazy']);

    // Let Blazy handle CSS background as Slick's background is deprecated.
    if ($settings['background']) {
      $settings['blazy'] = TRUE;
    }

    if ($settings['blazy']) {
      $settings['lazy'] = 'blazy';
    }

    // Aspect ratio isn't working with Responsive image, yet.
    // However allows custom work to get going with an enforced.
    $ratio = FALSE;
    if (!empty($settings['ratio'])) {
      $ratio = empty($settings['responsive_image_style']);
      if ($settings['ratio'] == 'enforced' || $settings['background']) {
        $ratio = TRUE;
      }
    }

    $settings['ratio'] = $ratio ? $settings['ratio'] : FALSE;

    // Pass first item to optimize sizes and build colorbox/zoom-like gallery.
    if (empty($settings['first_item']) && $field_type == 'image' && $items[0]) {
      $settings['first_item'] = $items[0];
      $settings['first_uri'] = ($file = $items[0]->entity) && empty($items[0]->uri) ? $file->getFileUri() : $items[0]->uri;
    }

    // Sets dimensions once, if cropped, to reduce costs with ton of images.
    // This is less expensive than re-defining dimensions per image.
    if (!empty($settings['first_item']) && !empty($settings['image_style']) && !$resimage_lazy) {
      $this->setDimensionsOnce($settings);
    }

    // @todo remove once sub-modules changed to use first_ things.
    unset($settings['item'], $settings['uri']);

    // Add the entity to formatter cache tags.
    $settings['cache_tags'][] = $settings['entity_type_id'] . ':' . $settings['entity_id'];

    $this->getModuleHandler()->alter('blazy_settings', $build, $items);
  }

}
