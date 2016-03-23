<?php

/**
 * @file
 * Contains \Drupal\blazy\Form\BlazyAdminFormatter.
 */

namespace Drupal\blazy\Form;

use Drupal\Core\Url;

/**
 * Provides admin form specific to Blazy admin formatter.
 */
class BlazyAdminFormatter extends BlazyAdminFormatterBase {

  /**
   * A state that represents the responsive image style is disabled.
   */
  const STATE_RESPONSIVE_IMAGE_STYLE_DISABLED = 0;

  /**
   * A state that represents the media switch lightbox is enabled.
   */
  const STATE_LIGHTBOX_ENABLED = 1;

  /**
   * Defines re-usable form elements.
   */
  public function buildSettingsForm(array &$form, $definition = []) {
    $settings      = $definition['settings'];
    $is_colorbox   = function_exists('colorbox_theme');
    $is_photobox   = function_exists('photobox_theme');
    $is_responsive = function_exists('responsive_image_get_image_dimensions');
    $image_styles  = image_style_options(FALSE);
    $photobox      = \Drupal::root() . '/libraries/photobox/photobox/jquery.photobox.js';
    $responsives   = $this->getResponsiveImageOptions();

    if (is_file($photobox)) {
      $is_photobox = TRUE;
    }

    $form['image_style'] = [
      '#title'         => t('Image style'),
      '#type'          => 'select',
      '#options'       => $image_styles,
      '#description'   => t('The content image style.'),
    ];

    $form['responsive_image_style'] = [
      '#type'        => 'select',
      '#title'       => t('Responsive image'),
      '#options'     => $responsives,
      '#description' => t('Only expects multi-serving images with srcset attribute. Not compatible with below breakpoints, aspect ratio, and retina, yet. However you can still lazyload it by checking <strong>Responsive image</strong> via Blazy UI. Leave empty to disable.'),
      '#access'      => $is_responsive && $responsives,
    ];

    if ($this->blazyManager()->getModuleHandler()->moduleExists('responsive_image')) {
      $form['responsive_image_style']['#description'] .= ' ' . t('<a href=":url" target="_blank">Manage responsive image styles</a>.', [':url' => Url::fromRoute('entity.responsive_image_style.collection')->toString()]);
    }
    if ($this->blazyManager()->getModuleHandler()->moduleExists('blazy_ui')) {
      $form['responsive_image_style']['#description'] .= ' ' . t('<a href=":url" target="_blank">Enable lazyloading Responsive image</a>.', [':url' => Url::fromRoute('blazy.settings')->toString()]);
    }

    $ratio = ['1:1', '3:2', '4:3', '8:5', '16:9', 'fluid'];
    $form['ratio'] = [
      '#type'          => 'select',
      '#title'         => t('Ratio'),
      '#options'       => array_combine($ratio, $ratio),
      '#description'   => t('Aspect ratio to get consistently responsive images and iframes and fix layout reflow. Choose <strong>fluid</strong> if unsure. Be sure to add width via custom CSS to the container (e.g.: <strong>.field</strong>) accordingly, if it is a floating element, and collapsed. <a href="@link" target="_blank">Learn more</a>, or leave empty if you prefer to DIY. If using lazyloaded Responsive image, this is not supported.', [
        '@link' => '//www.smashingmagazine.com/2014/02/27/making-embedded-content-work-in-responsive-design/',
      ]),
      '#states'        => $this->getState(static::STATE_RESPONSIVE_IMAGE_STYLE_DISABLED),
    ];

    $form['retina'] = [
      '#type'        => 'select',
      '#title'       => t('Retina'),
      '#options'     => $image_styles,
      '#description' => t('Optionally provide retina display. Only supports the main image style. Ignored if core Responsive image is provided.'),
      '#access'      => isset($definition['retina']),
      '#states'      => $this->getState(static::STATE_RESPONSIVE_IMAGE_STYLE_DISABLED),
    ];

    $form['media_switch'] = [
      '#type'        => 'select',
      '#title'       => t('Media switch'),
      '#options'     => [
        'content' => t('Image linked to content'),
      ],
      '#description' => t('May depend on the enabled supported modules: colorbox, photobox. Be sure to add Thumbnail style if using Photobox.'),
    ];

    // Optional lightbox integration.
    if ($is_colorbox || $is_photobox) {
      if ($is_colorbox) {
        $form['media_switch']['#options']['colorbox'] = t('Image to colorbox');
      }

      if ($is_photobox) {
        $form['media_switch']['#options']['photobox'] = t('Image to photobox');
      }

      // Re-use the same image style for lightboxes.
      $form['box_style'] = [
        '#type'    => 'select',
        '#title'   => t('Lightbox image style'),
        '#options' => $image_styles,
        '#states' => $this->getState(static::STATE_LIGHTBOX_ENABLED),
      ];

      if (isset($definition['multimedia']) && isset($definition['fieldable_form'])) {
        $form['dimension'] = [
          '#type'        => 'textfield',
          '#title'       => t('Lightbox media dimension'),
          '#description' => t('Use WIDTHxHEIGHT, e.g.: 640x360. This allows video dimensions for the lightbox to be different from the lightbox image style.'),
          '#states'      => $this->getState(static::STATE_LIGHTBOX_ENABLED),
        ];
      }
    }

    $form['thumbnail_style'] = [
      '#type'        => 'select',
      '#title'       => t('Thumbnail style'),
      '#options'     => $image_styles,
      '#description' => t('Usages: Photobox thumbnail, or custom work with thumbnails. Leave empty to not use thumbnails.'),
    ];

    $form['caption'] = [
      '#title'         => t('Captions'),
      '#type'          => 'checkboxes',
      '#default_value' => $settings['caption'],
      '#options'       => ['title' => t('Title'), 'alt' => t('Alt')],
      '#description'   => t('The caption to display below image. Leave empty to not use captions.'),
    ];

    if (!empty($definition['breakpoints'])) {
      $this->breakpointsForm($form, $definition);
    }
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
  protected function getState($state) {
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
    ];
    return $states[$state];
  }

}
