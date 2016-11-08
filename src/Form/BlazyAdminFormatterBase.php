<?php

namespace Drupal\blazy\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormState;
use Drupal\Component\Utility\Unicode;

/**
 * A base for field formatter admin to have re-usable methods in one place.
 */
abstract class BlazyAdminFormatterBase extends BlazyAdminBase {

  /**
   * Returns re-usable image formatter form elements.
   */
  public function imageStyleForm(array &$form, $definition = []) {
    $image_styles  = image_style_options(FALSE);
    $is_responsive = function_exists('responsive_image_get_image_dimensions');

    $form['image_style'] = $this->baseForm($definition)['image_style'];

    if (!empty($definition['thumbnail_style'])) {
      $form['thumbnail_style'] = $this->baseForm($definition)['thumbnail_style'];
    }

    if ($is_responsive && !empty($definition['responsive_image'])) {
      $form['responsive_image_style'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Responsive image'),
        '#options'     => $this->getResponsiveImageOptions(),
        '#description' => $this->t('Responsive image style for the main stage image is more reasonable for large images. Only expecting multi-serving IMG, but not PICTURE element. Not compatible with breakpoints and aspect ratio, yet. Leave empty to disable.'),
        '#access'      => $this->getResponsiveImageOptions(),
        '#weight'      => -100,
      ];
    }

    if (isset($definition['thumbnail_effect'])) {
      $form['thumbnail_effect'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Thumbnail effect'),
        '#options'     => isset($definition['thumbnail_effect']) ? $definition['thumbnail_effect'] : [],
        '#weight'      => -100,
        // '#states'      => $this->getState(static::STATE_THUMBNAIL_STYLE_ENABLED, $definition),
      ];
    }

    if ($is_responsive && isset($form['responsive_image_style'])) {
      $url = Url::fromRoute('entity.responsive_image_style.collection')->toString();
      $form['responsive_image_style']['#description'] .= ' ' . $this->t('<a href=":url" target="_blank">Manage responsive image styles</a>.', [':url' => $url]);
    }

    if (isset($form['background'])) {
      $form['background']['#states'] = $this->getState(static::STATE_RESPONSIVE_IMAGE_STYLE_DISABLED, $definition);
    }
  }

  /**
   * Returns re-usable media switch form elements.
   */
  public function mediaSwitchForm(array &$form, $definition = []) {
    $is_colorbox  = function_exists('colorbox_theme');
    $is_photobox  = function_exists('photobox_theme');
    $is_token     = function_exists('token_theme');
    $image_styles = image_style_options(FALSE);
    $photobox     = \Drupal::root() . '/libraries/photobox/photobox/jquery.photobox.js';
    $settings     = isset($definition['settings']) ? $definition['settings'] : [];

    if (is_file($photobox)) {
      $is_photobox = TRUE;
    }

    if (isset($definition['media_switch_form'])) {
      $form['media_switch'] = $this->baseForm($definition)['media_switch'];
      $form['media_switch']['#prefix'] = '<h3 class="form__title">' . $this->t('Media switcher') . '</h3>';
      $form['ratio'] = $this->baseForm($definition)['ratio'];
    }

    if (isset($definition['multimedia'])) {
      $form['iframe_lazy'] = [
        '#type'        => 'checkbox',
        '#title'       => $this->t('Lazy iframe'),
        '#description' => $this->t('Check to make the video/audio iframes truly lazyloaded, and speed up loading time. Depends on JS enabled at client side. <a href=":more" target="_blank">Read more</a> to <a href=":url" target="_blank">decide</a>.', [':more' => '//goo.gl/FQLFQ6', ':url' => '//goo.gl/f78pMl']),
        '#weight'      => -96,
        '#states'      => $this->getState(static::STATE_IFRAME_ENABLED, $definition),
      ];
    }

    if (!empty($definition['target_type']) && isset($definition['view_mode'])) {
      $form['view_mode'] = $this->baseForm($definition)['view_mode'];
    }

    // Optional lightbox integration.
    if ($is_colorbox || $is_photobox || isset($definition['lightbox'])) {
      // Re-use the same image style for both lightboxes.
      $form['box_style'] = [
        '#type'    => 'select',
        '#title'   => $this->t('Lightbox image style'),
        '#options' => $image_styles,
        '#weight'  => -99,
      ];

      if (!isset($definition['lightbox'])) {
        $form['box_style']['#states'] = $this->getState(static::STATE_LIGHTBOX_ENABLED, $definition);
      }

      $box_captions = [
        'auto'         => $this->t('Automatic'),
        'alt'          => $this->t('Alt text'),
        'title'        => $this->t('Title text'),
        'alt_title'    => $this->t('Alt and Title'),
        'title_alt'    => $this->t('Title and Alt'),
        'entity_title' => $this->t('Content title'),
        'custom'       => $this->t('Custom'),
      ];

      if (isset($definition['box_captions'])) {
        $form['box_caption'] = [
          '#type'        => 'select',
          '#title'       => $this->t('Lightbox caption'),
          '#options'     => $box_captions,
          '#weight'      => -99,
          '#states'      => $this->getState(static::STATE_LIGHTBOX_ENABLED, $definition),
          '#description' => $this->t('Automatic will search for Alt text first, then Title text. Try selecting <strong>- None -</strong> first when changing if trouble with form states.'),
        ];

        $form['box_caption_custom'] = [
          '#title'       => $this->t('Lightbox custom caption'),
          '#type'        => 'textfield',
          '#weight'      => -99,
          '#states'      => $this->getState(static::STATE_LIGHTBOX_CUSTOM, $definition),
          '#description' => $this->t('Multi-value rich text field will be mapped to each image by its delta.'),
        ];

        if ($is_token) {
          $types = isset($definition['entity_type']) ? [$definition['entity_type']] : [];
          $types = isset($definition['target_type']) ? array_merge($types, [$definition['target_type']]) : $types;
          $form['box_caption_custom']['#field_suffix'] = [
            '#theme'       => 'token_tree_link',
            '#text'        => $this->t('Tokens'),
            '#token_types' => $types,
          ];
        }
        else {
          $form['box_caption_custom']['#description'] .= ' ' . $this->t('Install Token module to browse available tokens.');
        }
      }

      if (isset($definition['multimedia'])) {
        $form['dimension'] = [
          '#type'        => 'textfield',
          '#title'       => $this->t('Lightbox media dimension'),
          '#description' => $this->t('Use WIDTHxHEIGHT, e.g.: 640x360. This allows video dimensions for the lightbox to be different from the lightbox image style.'),
          '#weight'      => -99,
          '#states'      => $this->getState(static::STATE_LIGHTBOX_ENABLED, $definition),
        ];
      }
    }
  }

  /**
   * Return the field formatter settings summary.
   */
  public function settingsSummary($plugin, $definition = []) {
    $form         = [];
    $summary      = [];
    $form_state   = new FormState();
    $settings     = isset($definition['settings']) ? $definition['settings'] : $plugin->getSettings();
    $elements     = $plugin->settingsForm($form, $form_state);
    $image_styles = image_style_options(TRUE);
    $breakpoints  = isset($settings['breakpoints']) ? array_filter($settings['breakpoints']) : [];
    $excludes     = empty($definition['excludes']) ? $definition : $definition['excludes'];

    unset($image_styles['']);

    foreach ($settings as $key => $setting) {
      $type = isset($elements[$key]['#type']) ? $elements[$key]['#type'] : '';

      if (!empty($excludes) && in_array($key, $excludes)) {
        continue;
      }

      if (in_array($type, ['button', 'container', 'details', 'fieldset', 'hidden', 'markup', 'item', 'submit', 'table']) || empty($type)) {
        continue;
      }

      $access   = isset($elements[$key]['#access']) ? $elements[$key]['#access'] : TRUE;
      $title    = !isset($elements[$key]) && isset($settings[$key]) ? Unicode::ucfirst(str_replace('_', ' ', $key)) : '';
      $title    = isset($elements[$key]['#title']) ? $elements[$key]['#title'] : $title;
      $options  = isset($elements[$key]['#options']) ? $elements[$key]['#options'] : [];
      $vanilla  = !empty($settings['vanilla']) && !isset($elements[$key]['#enforced']);
      $multiple = isset($elements[$key]['#multiple']) && $elements[$key]['#multiple'];

      if ($key == 'breakpoints') {
        $widths = [];
        if ($breakpoints) {
          foreach ($breakpoints as $id => $breakpoint) {
            if (!empty($breakpoint['width'])) {
              $widths[] = $breakpoint['width'];
            }
          }
        }

        $title   = $this->t('Breakpoints');
        $setting = $widths ? implode(', ', $widths) : $this->t('None');
      }
      else {
        if (empty($title) || $vanilla || !$access) {
          continue;
        }

        if ($key == 'override' && empty($setting)) {
          unset($settings['overridables']);
        }

        if (is_bool($setting) && $setting) {
          $setting = $this->t('Yes');
        }
        elseif (is_string($setting) && $key != 'cache') {
          // The value is based on select options.
          if (!$multiple && $type == 'select' && isset($options[$setting])) {
            $setting = is_object($options[$setting]) ? $options[$setting]->render() : $options[$setting];
          }
        }
        elseif (is_array($setting)) {
          $values = array_filter($setting);

          if (!empty($values)) {
            // Combine possible multi-value select, or checkboxes.
            $multiple_values = array_combine($values, $values);

            foreach ($multiple_values as $i => $value) {
              if (isset($options[$i])) {
                $multiple_values[$i] = is_object($options[$i]) ? $options[$i]->render() : $options[$i];
              }
            }

            $setting = implode(', ', $multiple_values);
          }

          if (is_array($setting)) {
            $setting = array_filter($setting);
            if (!empty($setting)) {
              $setting = implode(', ', $setting);
            }
          }
        }

        if ($key == 'cache') {
          $setting = $this->getCacheOptions()[$setting];
        }
      }

      if (empty($setting)) {
        continue;
      }

      if (isset($settings[$key])) {
        $summary[] = $this->t('@title: <strong>@setting</strong>', [
          '@title'   => $title,
          '@setting' => $setting,
        ]);
      }
    }
    return $summary;
  }

  /**
   * Returns available fields for select options.
   */
  public function getFieldOptions($target_bundles = [], $allowed_field_types = [], $entity_type = 'media', $target_type = '') {
    $options = [];
    $storage = $this->blazyManager()->getEntityTypeManager()->getStorage('field_config');

    // Fix for Views UI not recognizing Media bundles, unlike Formatters.
    if (empty($target_bundles)) {
      $bundle_service = \Drupal::service('entity_type.bundle.info');
      $target_bundles = $bundle_service->getBundleInfo($entity_type);
    }

    foreach ($target_bundles as $bundle => $label) {
      if ($fields = $storage->loadByProperties(['entity_type' => $entity_type, 'bundle' => $bundle])) {
        foreach ((array) $fields as $field_name => $field) {
          if (empty($allowed_field_types)) {
            $options[$field->getName()] = $field->getLabel();
          }
          elseif (in_array($field->getType(), $allowed_field_types)) {
            $options[$field->getName()] = $field->getLabel();
          }

          if (!empty($target_type) && ($field->getSetting('target_type') == $target_type)) {
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
    if ($this->blazyManager()->getModuleHandler()->moduleExists('responsive_image')) {
      $image_styles = $this->blazyManager()->entityLoadMultiple('responsive_image_style');
      if (!empty($image_styles)) {
        foreach ($image_styles as $name => $image_style) {
          if ($image_style->hasImageStyleMappings()) {
            $options[$name] = strip_tags($image_style->label());
          }
        }
      }
    }
    return $options;
  }

}
