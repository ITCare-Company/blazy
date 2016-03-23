<?php

/**
 * @file
 * Contains \Drupal\blazy\Dejavu\BlazyAdminExtended.
 */

namespace Drupal\blazy\Dejavu;

use Drupal\Core\Url;
use Drupal\Core\Render\Element;
use Drupal\blazy\Form\BlazyAdminBase;

/**
 * Provides re-usable admin functions, or form elements.
 *
 * @todo WIP.
 */
class BlazyAdminExtended extends BlazyAdminBase {

  /**
   * Returns shared form elements across field formatter and Views.
   */
  public function openingForm(array &$form, $definition = []) {
    $namespace = isset($definition['namespace']) ? $definition['namespace'] : 'blazy';
    $path      = drupal_get_path('module', $namespace);
    $readme    = Url::fromUri('base:' . $path . '/README.txt')->toString();

    $form['skin'] = [
      '#type'        => 'select',
      '#title'       => t('Skin main'),
      '#options'     => $this->getSkinOptions(),
      '#enforced'    => TRUE,
      '#description' => t('Skins allow various layouts with just CSS. Some options below depend on a skin. However a combination of skins and options may lead to unpredictable layouts, get yourself dirty. See <a href=":url" target="_blank">SKINS section at README.txt</a> for details on Skins. Leave empty to DIY. Or use the provided hook_info() and implement the skin interface to register ones.', [':url' => $readme]),
      '#weight'      => -107,
    ];

    $form['background'] = [
      '#type'        => 'checkbox',
      '#title'       => t('Use CSS background'),
      '#description' => t('If trouble with image sizes not filling the given box, check this to turn the image into CSS background instead.'),
      '#access'      => isset($definition['background']),
    ];

    $form['layout'] = [
      '#type'        => 'select',
      '#title'       => t('Layout'),
      '#options'     => isset($definition['layouts']) ? $this->getLayoutOptions() + $definition['layouts'] : $this->getLayoutOptions(),
      '#description' => t('Requires a skin. The builtin layouts affects the entire boxes uniformly. Leave empty to DIY.'),
      '#weight'      => 2,
    ];

    $form['caption'] = [
      '#type'        => 'checkboxes',
      '#title'       => t('Caption fields'),
      '#options'     => isset($definition['captions']) ? $definition['captions'] : [],
      '#description' => t('Enable any of the following fields as box caption. These fields are treated and wrapped as captions.'),
      '#access'      => isset($definition['captions']),
      '#weight'       => 80,
    ];

    $weight = -99;
    foreach (Element::children($form) as $key) {
      if (!isset($form[$key]['#weight'])) {
        $form[$key]['#weight'] = ++$weight;
      }
    }
  }

  /**
   * Returns shared entity-related form across field formatter and Views.
   */
  public function entityForm(array &$form, $definition = []) {
    if (!isset($definition['namespace'])) {
      return;
    }
    $namespace = $definition['namespace'];
    $path      = drupal_get_path('module', $namespace);
    $readme    = Url::fromUri('base:' . $path . '/README.txt')->toString();

    $form['vanilla'] = [
      '#type'        => 'checkbox',
      '#title'       => t('Vanilla'),
      '#description' => t('<strong>Check</strong>:<ul><li>To render item as is as without extra logic.</li><li>To disable 99% module features, and most of the mentioned options here, such as layouts, et al.</li><li>Things may be broken! You are on your own.</li></ul><strong>Uncheck</strong>:<ul><li>To get consistent markups and its advanced features -- relevant for the provided options as the module needs to know what to style/work with.</li></ul>'),
      '#weight'      => -109,
      '#enforced'    => TRUE,
      '#access'      => isset($definition['vanilla']),
      '#wrapper_attributes' => ['class' => ['form-item--full', 'form-item--tooltip-bottom']],
    ];

    $form['optionset'] = [
      '#type'        => 'select',
      '#title'       => t('Optionset main'),
      '#options'     => $this->getOptionsetOptions($namespace),
      '#enforced'    => TRUE,
      '#description' => t('Enable the optionset UI module to manage the optionsets.'),
      '#weight'      => -108,
    ];

    if ($this->blazyManager()->getModuleHandler()->moduleExists($namespace . '_ui')) {
      $form['optionset']['#description'] = t('Manage optionsets at <a href=":url" target="_blank">The optionset admin page</a>.', [':url' => Url::fromRoute('entity.' . $namespace . '.collection')->toString()]);
    }
  }

  /**
   * Returns re-usable image formatter form.
   */
  public function imageForm(array &$form, $definition = []) {
    $is_colorbox   = function_exists('colorbox_theme');
    $is_photobox   = function_exists('photobox_theme');
    $is_responsive = function_exists('responsive_image_get_image_dimensions');
    $image_styles  = function_exists('image_style_options') ? image_style_options(FALSE) : [];
    $photobox      = \Drupal::root() . '/libraries/photobox/photobox/jquery.photobox.js';

    if (is_file($photobox)) {
      $is_photobox = TRUE;
    }

    $form['image_style'] = [
      '#type'        => 'select',
      '#title'       => t('Image style'),
      '#options'     => $image_styles,
      '#description' => t('The main image style. If Slick media module installed, this also determines iframe sizes to have various iframe dimensions with just a single file entity view mode, relevant for a mix of image and multimedia to get a consistent display.'),
    ];

    $form['thumbnail_style'] = [
      '#type'        => 'select',
      '#title'       => t('Thumbnail style'),
      '#options'     => $image_styles,
      '#description' => t('Usages: <ol><li>If <em>Optionset thumbnail</em> provided, it is for asNavFor thumbnail navigation.</li><li>If <em>Dots with thumbnail</em> selected, displayed when hovering over dots.</li><li>Photobox thumbnail.</li><li>Custom work to build arrows with thumbnails via the provided data-thumb attributes.</li></ol>Leave empty to not use thumbnails.'),
    ];

    $form['thumbnail_hover'] = [
      '#type'        => 'checkbox',
      '#title'       => t('Dots with thumbnail'),
      '#description' => t('Dependent on a skin, dots option enabled, and Thumbnail style. If checked, dots pager are kept, and thumbnail will be hidden and only visible on mouseover, default to min-width 120px. Alternative to asNavFor aka separate thumbnails as slider.'),
      '#states' => [
        'visible' => [
          'select[name*="[thumbnail_style]"]' => ['!value' => ''],
        ],
      ],
    ];

    $form['media_switch'] = [
      '#type'        => 'select',
      '#title'       => t('Media switcher'),
      '#options'     => [
        'content' => t('Image linked to content'),
      ],
      '#description' => t('Depends on the enabled supported modules, or has known integration with Slick.<ol><li>Link to content: for aggregated small slicks.</li><li>Image to iframe: audio/video is hidden below image until toggled, otherwise iframe is always displayed, and draggable fails. Aspect ratio applies.</li><li>Colorbox.</li><li>Photobox. Be sure to select "Thumbnail style" for the overlay thumbnails.</li><li>Intense: image to fullscreen intense image.</li></ol>'),
      '#prefix' => '<h3 class="form__title">' . t('Fields') . '</h3>',
    ];

    $form['responsive_image_style'] = [
      '#type'        => 'select',
      '#title'       => t('Responsive image'),
      '#options'     => $this->getResponsiveImageOptions(),
      '#description' => t('Responsive image style for the main stage image is only reasonable for large images. Not compatible with aspect ratio, yet. Leave empty to disable.'),
      '#access'      => $is_responsive && $this->getResponsiveImageOptions(),
    ];

    if ($this->blazyManager()->getModuleHandler()->moduleExists('responsive_image')) {
      $form['responsive_image_style']['#description'] .= ' ' . t('<a href=":url" target="_blank">Manage responsive image styles</a>.', [':url' => Url::fromRoute('entity.responsive_image_style.collection')->toString()]);
    }

    // http://en.wikipedia.org/wiki/List_of_common_resolutions
    $ratio = ['1:1', '3:2', '4:3', '8:5', '16:9', 'fluid'];
    $form['ratio'] = [
      '#type'        => 'select',
      '#title'       => t('Aspect ratio'),
      '#options'     => array_combine($ratio, $ratio),
      '#description' => t('Aspect ratio to get consistently responsive images and iframes. Required if using media entity to switch between iframe and overlay image, otherwise DIY. This also fixes layout reflow and excessive height issues with lazyload ondemand. <a href="@dimensions" target="_blank">Image styles and video dimensions</a> must <a href="@follow" target="_blank">follow the aspect ratio</a>. If not, images will be unexpectedly resized. Choose <strong>fluid</strong> if unsure. <a href="@link" target="_blank">Learn more</a>, or leave empty if you care not for aspect ratio, or prefer to DIY.', [
        '@dimensions' => '//size43.com/jqueryVideoTool.html',
        '@follow'     => '//en.wikipedia.org/wiki/Aspect_ratio_%28image%29',
        '@link'       => '//www.smashingmagazine.com/2014/02/27/making-embedded-content-work-in-responsive-design/',
      ]),
      '#states' => [
        'visible' => [
          'select[name*="[responsive_image_style]"]' => ['value' => ''],
        ],
      ],
    ];

    if (isset($definition['fieldable_form'])) {
      $form['iframe_lazy'] = [
        '#type'        => 'checkbox',
        '#title'       => t('Lazy iframe'),
        '#description' => t('Check to make the video/audio iframes truly lazyloaded, and speed up loading time. Depends on JS enabled at client side.'),
        '#states' => [
          'visible' => [
            'select[name*="[media_switch]"]' => ['value' => 'media'],
          ],
        ],
      ];

      $form['view_mode'] = [
        '#type'        => 'select',
        '#options'     => isset($definition['target_type']) ? $this->getEntityDisplayRepository()->getViewModeOptions($definition['target_type']) : [],
        '#title'       => t('View mode'),
        '#description' => t('Required to grab the fields. Be sure the selected "View mode" is enabled, and the enabled fields here are not hidden there. Manage view modes on the <a href=":view_modes">View modes page</a>.', [':view_modes' => Url::fromRoute('entity.entity_view_mode.collection')->toString()]),
        '#access'      => isset($definition['target_type']),
      ];

      $this->fieldableForm($form, $definition);
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
        '#states'  => [
          'visible' => [
            'select[name*="[media_switch]"]' => [['value' => 'colorbox'], ['value' => 'photobox']],
          ],
        ],
      ];

      if (isset($definition['multimedia']) && isset($definition['fieldable_form'])) {
        $form['dimension'] = [
          '#type'        => 'textfield',
          '#title'       => t('Lightbox media dimension'),
          '#description' => t('Use WIDTHxHEIGHT, e.g.: 640x360. This allows video dimensions for the lightbox to be different from the lightbox image style.'),
          '#states'      => [
            'visible' => [
              'select[name*="[media_switch]"]' => [['value' => 'colorbox'], ['value' => 'photobox']],
            ],
          ],
        ];
      }
    }
  }

  /**
   * Returns re-usable fieldable formatter form.
   */
  public function fieldableForm(array &$form, $definition = []) {
    $is_colorbox   = function_exists('colorbox_theme');
    $is_photobox   = function_exists('photobox_theme');
    $is_responsive = function_exists('responsive_image_get_image_dimensions');
    $image_styles  = function_exists('image_style_options') ? image_style_options(FALSE) : [];
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
      '#description' => t('Depends on the enabled supported modules, or has known integration with this module.<ol><li>Link to content: for aggregated small displays.</li><li>Image to iframe: audio/video is hidden below image until toggled, otherwise iframe is always displayed, and draggable fails. Aspect ratio applies.</li><li>Colorbox.</li><li>Photobox. Be sure to select "Thumbnail style" for the overlay thumbnails.</li><li>Intense: image to fullscreen intense image.</li></ol>'),
      '#prefix' => '<h3 class="form__title">' . t('Fields') . '</h3>',
      '#access'      => isset($definition['switchers']),
    ];

    // Optional lightbox integration.
    if (isset($definition['switchers']) && ($is_colorbox || $is_photobox)) {
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
        '#states'  => [
          'visible' => [
            'select[name*="[media_switch]"]' => [['value' => 'colorbox'], ['value' => 'photobox']],
          ],
        ],
      ];
    }

    $form['image'] = [
      '#type'        => 'select',
      '#title'       => t('Main image'),
      '#options'     => isset($definition['images']) ? $definition['images'] : [],
      '#description' => t('Main background/stage image field.'),
      '#access'      => isset($definition['images']),
    ];

    $form['thumbnail'] = array(
      '#type'        => 'select',
      '#title'       => t('Thumbnail image'),
      '#options'     => isset($definition['thumbnails']) ? $definition['thumbnails'] : [],
      '#description' => t("Only needed if <em>Optionset thumbnail</em> is provided. Maybe the same field as the main image, only different instance. Leave empty to not use thumbnail pager."),
      '#access'      => isset($definition['thumbnails']),
    );

    $form['overlay'] = array(
      '#type'        => 'select',
      '#title'       => t('Overlay media/slicks'),
      '#options'     => isset($definition['overlays']) ? $definition['overlays'] : [],
      '#description' => t('For audio/video, be sure the display is not image. For nested slicks, use the Slick carousel formatter for this field. Zebra layout is reasonable for overlay and captions.'),
      '#access'      => isset($definition['overlays']),
    );

    $form['title'] = [
      '#type'        => 'select',
      '#title'       => t('Title'),
      '#options'     => isset($definition['titles']) ? $definition['titles'] : [],
      '#description' => t('If provided, it will bre wrapped with H2 and class .slide__title.'),
      '#access'      => isset($definition['titles']),
    ];

    $form['link'] = [
      '#type'        => 'select',
      '#title'       => t('Link'),
      '#options'     => isset($definition['links']) ? $definition['links'] : [],
      '#description' => t('Link to content: Read more, View Case Study, etc, wrapped with class .slide__link.'),
      '#access'      => isset($definition['links']),
    ];

    $form['class'] = [
      '#type'        => 'select',
      '#title'       => t('Slide class'),
      '#options'     => isset($definition['classes']) ? $definition['classes'] : [],
      '#description' => t('If provided, individual slide will have this class, e.g.: to have different background with transparent images and skin Split. Be sure its formatter is Key.'),
      '#access'      => isset($definition['classes']),
      '#weight'      => 6,
    ];

    $form['id'] = [
      '#type'         => 'textfield',
      '#title'        => t('Slick ID'),
      '#size'         => 40,
      '#maxlength'    => 255,
      '#field_prefix' => '#',
      '#enforced'     => TRUE,
      '#description'  => t("Manually define the Slick carousel container ID. <em>This ID is used for the cache identifier, so be sure it is unique</em>. Leave empty to have a guaranteed unique ID managed by the module."),
      '#access'       => isset($definition['id']),
      '#weight'       => 94,
    ];

    $form['caption']['#description'] = t('Enable any of the following fields as slide caption. These fields are treated and wrapped as captions. Be sure to make them visible at their relevant Manage display.');
  }

  /**
   * Returns re-usable grid elements across field formatter and Views.
   */
  public function gridForm(array &$form, $definition = []) {
    $range = range(1, 12);
    $grid_options = array_combine($range, $range);

    $header = t('Group individual items as block grid?<small>Only works if the total items &gt; <strong>Visible slides</strong>.</small>');
    $form['grid_header'] = [
      '#type'   => 'item',
      '#markup' => '<h3 class="form__title">' . $header . '</h3>',
    ];

    $form['grid'] = [
      '#type'        => 'select',
      '#title'       => t('Grid large'),
      '#options'     => $grid_options,
      '#description' => t('The amount of block grid columns for large monitors 64.063em - 90em. <br /><strong>Requires</strong>:<ol><li>Visible slides,</li><li>Skin Grid for starter,</li><li>A reasonable amount of contents,</li><li>Optionset with Rows and slidesPerRow = 1.</li></ol>This is module feature, older than core Rows, and offers more flexibility. Leave empty to DIY, or to not build grids.'),
      '#enforced'    => TRUE,
    ];

    $form['grid_medium'] = [
      '#type'        => 'select',
      '#title'       => t('Grid medium'),
      '#options'     => $grid_options,
      '#description' => t('The amount of block grid columns for medium devices 40.063em - 64em.'),
    ];

    $form['grid_small'] = [
      '#type'        => 'select',
      '#title'       => t('Grid small'),
      '#options'     => $grid_options,
      '#description' => t('The amount of block grid columns for small devices 0 - 40em.'),
    ];

    $form['visible_slides'] = [
      '#type'        => 'select',
      '#title'       => t('Visible slides'),
      '#options'     => array_combine(range(1, 32), range(1, 32)),
      '#description' => t('How many items per slide displayed at a time. Required if Grid provided. Grid will not work if Views rows count &lt; <strong>Visible slides</strong>.'),
    ];

    $form['preserve_keys'] = [
      '#title'       => t('Preserve keys'),
      '#type'        => 'checkbox',
      '#description' => t('If checked, keys will be preserved. Default is FALSE which will reindex the grid chunk numerically.'),
    ];

    $grids = [
      'grid_header',
      'grid_medium',
      'grid_small',
      'visible_slides',
      'preserve_keys',
    ];

    foreach ($grids as $key) {
      $form[$key]['#enforced'] = TRUE;
      $form[$key]['#states'] = [
        'visible' => [
          'select[name$="[grid]"]' => ['!value' => ''],
        ],
      ];
    }
  }

  /**
   * Returns shared ending form elements across field formatter and Views.
   */
  public function closingForm(array &$form, $definition = []) {
    $form['cache'] = [
      '#type'        => 'select',
      '#title'       => t('Cache'),
      '#options'     => $this->getCacheOptions(),
      '#weight'      => 98,
      '#enforced'    => TRUE,
      '#description' => t('Ditch all the logic to cached bare HTML. <ol><li><strong>Permanent</strong>: cached contents will persist (be displayed) till the next cron runs.</li><li><strong>Any number</strong>: expired by the selected expiration time, and fresh contents are fetched till the next cache rebuilt.</li></ol>A working cron job is required to clear stale cache. At any rate, cached contents will be refreshed regardless of the expiration time after the cron hits. <br />Leave it empty to disable caching.<br /><strong>Warning!</strong> Be sure no useless/ sensitive data such as Edit links as they are rendered as is regardless permissions. Only enable it when all is done, otherwise cached options will be displayed while changing them.'),
    ];

    $form['current_view_mode'] = [
      '#type'          => 'hidden',
      '#default_value' => isset($definition['current_view_mode']) ? $definition['current_view_mode'] : '_custom',
      '#weight'        => 100,
    ];

    // $this->finalizeForm($form, $definition);
  }

}
