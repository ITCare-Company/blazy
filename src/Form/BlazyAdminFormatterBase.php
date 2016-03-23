<?php

/**
 * @file
 * Contains \Drupal\blazy\Form\BlazyAdminFormatterBase.
 */

namespace Drupal\blazy\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormState;

/**
 * A base for field formatter admin to have re-usable methods in one place.
 */
abstract class BlazyAdminFormatterBase extends BlazyAdminBase {

  /**
   * Return the field formatter settings summary.
   */
  public function settingsSummary($plugin, array &$summary = []) {
    $form         = [];
    $form_state   = new FormState();
    $settings     = $plugin->getSettings();
    $elements     = $plugin->settingsForm($form, $form_state);
    $definition   = $this->typedConfig->getDefinition('field.formatter.settings.' . $plugin->getPluginId());
    $image_styles = image_style_options(TRUE);

    unset($image_styles['']);

    foreach ($settings as $key => $setting) {
      $access  = isset($elements[$key]['#access'])  ? $elements[$key]['#access']  : TRUE;
      $title   = isset($elements[$key]['#title'])   ? $elements[$key]['#title']   : '';
      $options = isset($elements[$key]['#options']) ? $elements[$key]['#options'] : [];
      $vanilla = !empty($settings['vanilla']) && !isset($elements[$key]['#enforced']);

      if (is_array($setting) || empty($title) || $vanilla || !$access) {
        continue;
      }

      if ($definition['mapping'][$key]['type'] == 'boolean') {
        if (empty($setting)) {
          continue;
        }
        $setting = t('Yes');
      }
      elseif ($definition['mapping'][$key]['type'] == 'string' && empty($setting)) {
        continue;
      }
      if ($key == 'cache') {
        $setting = $this->getCacheOptions()[$setting];
      }

      if (isset($options[$settings[$key]])) {
        $setting = is_object($options[$settings[$key]]) ? $options[$settings[$key]]->render() : $options[$settings[$key]];
      }

      if (isset($settings[$key])) {
        $summary[] = t('@title: <strong>@setting</strong>', array(
          '@title'   => $title,
          '@setting' => $setting,
        ));
      }
    }
    return $summary;
  }

  /**
   * Returns available fields for select options.
   */
  public function getFieldOptions($target_bundles = [], $allowed_field_types = [], $entity_type_id = 'media') {
    $options = [];
    $storage = $this->blazyManager->getEntityTypeManager()->getStorage('field_config');

    foreach ($target_bundles as $bundle) {
      if ($fields = $storage->loadByProperties(['entity_type' => $entity_type_id, 'bundle' => $bundle])) {
        foreach ((array) $fields as $field_name => $field) {
          if (empty($allowed_field_types)) {
            $options[$field->getName()] = $field->getLabel();
          }
          elseif (in_array($field->getType(), $allowed_field_types)) {
            $options[$field->getName()] = $field->getLabel();
          }
        }
      }
    }

    return $options;
  }

  /**
   * Returns Responsive image for select options.
   */
  public function getResponsiveImageOptions() {
    $options = [];
    if ($this->blazyManager->getModuleHandler()->moduleExists('responsive_image')) {
      $image_styles = $this->blazyManager->entityLoadMultiple('responsive_image_style');
      if (!empty($image_styles)) {
        foreach ($image_styles as $machine_name => $image_style) {
          if ($image_style->hasImageStyleMappings()) {
            $options[$machine_name] = Html::escape($image_style->label());
          }
        }
      }
    }
    return $options;
  }

  /**
   * Returns available view modes for select options.
   */
  public function getViewModeOptions($target_type) {
    return $this->entityDisplayRepository->getViewModeOptions($target_type);
  }

}
