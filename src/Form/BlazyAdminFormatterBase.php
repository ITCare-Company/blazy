<?php

/**
 * @file
 * Contains \Drupal\blazy\Form\BlazyAdminFormatterBase.
 */

namespace Drupal\blazy\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormState;
use Drupal\Component\Utility\Html;

/**
 * A base for field formatter admin to have re-usable methods in one place.
 */
abstract class BlazyAdminFormatterBase extends BlazyAdminBase {

  /**
   * A state that represents the responsive image style is disabled.
   */
  const STATE_RESPONSIVE_IMAGE_STYLE_DISABLED = 0;

  /**
   * A state that represents the media switch lightbox is enabled.
   */
  const STATE_LIGHTBOX_ENABLED = 1;

  /**
   * A state that represents the media switch iframe is enabled.
   */
  const STATE_IFRAME_ENABLED = 2;

  /**
   * A state that represents the thumbnail style is enabled.
   */
  const STATE_THUMBNAIL_STYLE_ENABLED = 3;
  
  /**
   * Returns re-usable image formatter form elements.
   */
  public function imageStyleForm(array &$form, $definition = []) {
    $image_styles  = image_style_options(FALSE);
    $is_responsive = function_exists('responsive_image_get_image_dimensions');

    $form['image_style'] = [
      '#type'        => 'select',
      '#title'       => t('Image style'),
      '#options'     => $image_styles,
      '#description' => t('The content image style.'),
      '#weight'      => -100,
    ];

    $form['responsive_image_style'] = [
      '#type'        => 'select',
      '#title'       => t('Responsive image'),
      '#options'     => $this->getResponsiveImageOptions(),
      '#description' => t('Responsive image style for the main stage image is only reasonable for large images. Not compatible with aspect ratio, yet. Leave empty to disable.'),
      '#access'      => $is_responsive && $this->getResponsiveImageOptions(),
      '#weight'      => -100,
    ];

    $form['thumbnail_style'] = [
      '#type'        => 'select',
      '#title'       => t('Thumbnail style'),
      '#options'     => $image_styles,
      '#description' => t('Usages: Photobox thumbnail, or custom work with thumbnails. Leave empty to not use thumbnails.'),
      '#access'      => isset($definition['thumbnail_styles']),
      '#weight'      => -100,
    ];

    $form['thumbnail_effect'] = [
      '#type'        => 'select',
      '#title'       => t('Thumbnail effect'),
      '#options'     => isset($definition['thumbnail_effects']) ? $definition['thumbnail_effects'] : [],
      '#access'      => isset($definition['thumbnail_effects']),
      '#weight'      => -100,
      // '#states'      => $this->getState(static::STATE_THUMBNAIL_STYLE_ENABLED, $definition),
    ];

    $form['retina'] = [
      '#type'        => 'select',
      '#title'       => t('Retina'),
      '#options'     => $image_styles,
      '#description' => t('Optionally provide retina display. Only supports the main image style. Ignored if core Responsive image is provided.'),
      '#access'      => isset($definition['retina']),
      '#states'      => $this->getState(static::STATE_RESPONSIVE_IMAGE_STYLE_DISABLED, $definition),
      '#weight'      => -100,
    ];

    if ($is_responsive) {
      $url = Url::fromRoute('entity.responsive_image_style.collection')->toString();
      $form['responsive_image_style']['#description'] .= ' ' . t('<a href=":url" target="_blank">Manage responsive image styles</a>.', [':url' => $url]);
    }
  }

  /**
   * Returns re-usable media switch form elements.
   */
  public function mediaSwitchForm(array &$form, $definition = []) {
    $is_colorbox   = function_exists('colorbox_theme');
    $is_photobox   = function_exists('photobox_theme');
    $is_responsive = function_exists('responsive_image_get_image_dimensions');
    $image_styles  = image_style_options(FALSE);
    $photobox      = \Drupal::root() . '/libraries/photobox/photobox/jquery.photobox.js';

    if (is_file($photobox)) {
      $is_photobox = TRUE;
    }

    $form['media_switch'] = [
      '#type'        => 'select',
      '#title'       => t('Media switcher'),
      '#options'     => [
        'content' => t('Image linked to content'),
      ],
      '#description' => t('May depend on the enabled supported modules: colorbox, photobox. Be sure to add Thumbnail style if using Photobox.'),
      '#prefix'      => '<h3 class="form__title">' . t('Media switcher') . '</h3>',
      '#weight'      => -99,
      '#access'      => isset($definition['media_switch_form']),
    ];

    // http://en.wikipedia.org/wiki/List_of_common_resolutions
    $ratio = ['1:1', '3:2', '4:3', '8:5', '16:9', 'fluid', 'enforced'];
    $form['ratio'] = [
      '#type'        => 'select',
      '#title'       => t('Aspect ratio'),
      '#options'     => array_combine($ratio, $ratio),
      '#description' => t('Aspect ratio to get consistently responsive images and iframes. And to fix layout reflow and excessive height issues. <a href="@dimensions" target="_blank">Image styles and video dimensions</a> must <a href="@follow" target="_blank">follow the aspect ratio</a>. If not, images will be unexpectedly distorted. Choose <strong>fluid</strong> if unsure. Choose <strong>enforced</strong> if you can stick to one aspect ratio and want multi-serving, or Responsive images. <a href="@link" target="_blank">Learn more</a>, or leave empty if you care not for aspect ratio, or prefer to DIY. <br /><strong>Note!</strong> Not compatible with Responsive image and multi-serving images, unless they stick to one aspect ratio with an <strong>enforced</strong> ratio.', [
        '@dimensions' => '//size43.com/jqueryVideoTool.html',
        '@follow'     => '//en.wikipedia.org/wiki/Aspect_ratio_%28image%29',
        '@link'       => '//www.smashingmagazine.com/2014/02/27/making-embedded-content-work-in-responsive-design/',
      ]),
      '#access'       => isset($definition['media_switch_form']),
      '#weight'       => -99,
      '#states'       => $this->getState(static::STATE_RESPONSIVE_IMAGE_STYLE_DISABLED, $definition),
    ];

    if (isset($definition['fieldable_form'])) {
      $form['iframe_lazy'] = [
        '#type'        => 'checkbox',
        '#title'       => t('Lazy iframe'),
        '#description' => t('Check to make the video/audio iframes truly lazyloaded, and speed up loading time. Depends on JS enabled at client side.'),
        '#access'      => isset($definition['multimedia']),
        '#weight'      => -99,
        '#states'      => $this->getState(static::STATE_IFRAME_ENABLED, $definition),
      ];

      $form['view_mode'] = [
        '#type'        => 'select',
        '#options'     => isset($definition['target_type']) ? $this->getViewModeOptions($definition['target_type']) : [],
        '#title'       => t('View mode'),
        '#description' => t('Required to grab the fields. Be sure the selected "View mode" is enabled, and the enabled fields here are not hidden there. Manage view modes on the <a href=":view_modes">View modes page</a>.', [':view_modes' => Url::fromRoute('entity.entity_view_mode.collection')->toString()]),
        '#access'      => isset($definition['target_type']),
        '#weight'      => -99,
      ];
    }

    // Optional lightbox integration.
    if ($is_colorbox || $is_photobox) {
      if ($is_colorbox) {
        $form['media_switch']['#options']['colorbox'] = t('Image to colorbox');
      }

      if ($is_photobox) {
        $form['media_switch']['#options']['photobox'] = t('Image to photobox');
      }

      // Re-use the same image style for both lightboxes.
      $form['box_style'] = [
        '#type'    => 'select',
        '#title'   => t('Lightbox image style'),
        '#options' => $image_styles,
        '#weight'  => -99,
        '#states'  => $this->getState(static::STATE_LIGHTBOX_ENABLED, $definition),
      ];

      if (isset($definition['multimedia']) && isset($definition['fieldable_form'])) {
        $form['dimension'] = [
          '#type'        => 'textfield',
          '#title'       => t('Lightbox media dimension'),
          '#description' => t('Use WIDTHxHEIGHT, e.g.: 640x360. This allows video dimensions for the lightbox to be different from the lightbox image style.'),
          '#weight'      => -99,
          '#states'      => $this->getState(static::STATE_LIGHTBOX_ENABLED, $definition),
        ];
      }
    }
  }

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
    $storage = $this->blazyManager()->getEntityTypeManager()->getStorage('field_config');

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
    if ($this->blazyManager()->getModuleHandler()->moduleExists('responsive_image')) {
      $image_styles = $this->blazyManager()->entityLoadMultiple('responsive_image_style');
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
   * Get one of the pre-defined states used in this form.
   *
   * Thanks to SAM152 at colorbox.module for the little sweet idea.
   *
   * @param string $state
   *   The state to get that matches one of the state class constants.
   *
   * @return array
   *   A corresponding form API state.
   */
  protected function getState($state, $definition = []) {
    // $field_name = isset($definition['field_name']) ? $definition['field_name'] : '';
    // if (!empty($definition['_views'])) {
    // $vanilla = ':input[name="options[settings][vanilla]"]';
    // }

    // fields[field_media][settings_edit_form][settings][media_switch]
    $states = [
      static::STATE_RESPONSIVE_IMAGE_STYLE_DISABLED => [
        'visible' => [
          'select[name$="[responsive_image_style]"]' => ['value' => ''],
        ],
      ],
      static::STATE_LIGHTBOX_ENABLED => [
        'visible' => [
          'select[name*="[media_switch]"]' => [['value' => 'colorbox'], ['value' => 'photobox']],
        ],
      ],
      static::STATE_IFRAME_ENABLED => [
        'visible' => [
          'select[name*="[media_switch]"]' => ['value' => 'media'],
        ],
      ],
      static::STATE_THUMBNAIL_STYLE_ENABLED => [
        'visible' => [
          'select[name$="[thumbnail_style]"]' => ['!value' => ''],
        ],
      ],
    ];
    return $states[$state];
  }

}
