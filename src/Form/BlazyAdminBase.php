<?php

namespace Drupal\blazy\Form;

use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazyManagerInterface;
use Drupal\blazy\BlazySettings;
use Drupal\blazy\Traits\PluginScopesTrait;
use Drupal\blazy\Utility\Path;

/**
 * A base for blazy admin integration to have re-usable methods in one place.
 *
 * @see \Drupal\gridstack\Form\GridStackAdmin
 * @see \Drupal\mason\Form\MasonAdmin
 * @see \Drupal\slick\Form\SlickAdmin
 * @see \Drupal\blazy\Form\BlazyAdminFormatterBase
 */
abstract class BlazyAdminBase implements BlazyAdminInterface {

  use StringTranslationTrait;
  use PluginScopesTrait;

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
   * A state that represents the custom lightbox caption is enabled.
   */
  const STATE_LIGHTBOX_CUSTOM = 4;

  /**
   * A state that represents the image rendered switch is enabled.
   */
  const STATE_IMAGE_RENDERED_ENABLED = 5;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The typed config manager service.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfig;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * Constructs a BlazyAdminBase object.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
   *   The typed config service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\blazy\BlazyManagerInterface $blazy_manager
   *   The blazy manager service.
   */
  public function __construct(
    EntityDisplayRepositoryInterface $entity_display_repository,
    TypedConfigManagerInterface $typed_config,
    DateFormatterInterface $date_formatter,
    BlazyManagerInterface $blazy_manager
  ) {
    $this->entityDisplayRepository = $entity_display_repository;
    $this->typedConfig             = $typed_config;
    $this->dateFormatter           = $date_formatter;
    $this->blazyManager            = $blazy_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_display.repository'),
      $container->get('config.typed'),
      $container->get('date.formatter'),
      $container->get('blazy.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityDisplayRepository() {
    return $this->entityDisplayRepository;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypedConfig() {
    return $this->typedConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * {@inheritdoc}
   */
  public function openingForm(array &$form, array &$definition): void {
    $scopes = $this->toScopes($definition);

    // @todo remove this failsafe after sub-module migrations done.
    $this->checkScopes($scopes, $definition);

    $this->blazyManager
      ->moduleHandler()
      ->alter('blazy_form_element_definition', $definition);

    $base_form = $this->baseForm($definition);

    // Display style: column, plain static grid, slick grid, slick carousel.
    // https://drafts.csswg.org/css-multicol
    if ($scopes->is('style')) {
      $form['style'] = [
        '#type'         => 'select',
        '#title'        => $this->t('Display style'),
        '#description'  => $this->t('Unless otherwise specified, the styles require <strong>Grid</strong>. Difference: <ul><li><strong>Columns</strong> is best with irregular image sizes (scale width, empty height), affects the natural order of grid items, top-bottom, not left-right.</li><li><strong>Foundation</strong> with regular cropped ones, left-right.</li><li><strong>Flex Masonry</strong> (@deprecated due to an epic failure) uses Flexbox, supports (ir)-regular, left-right flow.</li><li><strong>Native Grid</strong> supports both one and two dimensional grid.</li></ul> Unless required, leave empty to use default formatter, or style. Save for <b>Grid Foundation</b>, the rest are experimental!'),
        '#enforced'     => TRUE,
        '#empty_option' => $this->t('- None -'),
        '#options'      => $this->blazyManager->getStyles(),
        '#required' => $scopes->is('grid_required'),
        '#weight'   => -112,
        '#wrapper_attributes' => [
          'class' => [
            'form-item--style',
            'form-item--tooltip-bottom',
            'form-item--tooltip-wide',
          ],
        ],
      ];
    }

    if ($skins = $scopes->data('skins')) {
      $form['skin'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Skin'),
        '#options'     => $this->toOptions($skins),
        '#enforced'    => TRUE,
        '#description' => $this->t('Skins allow various layouts with just CSS. Some options below depend on a skin. Leave empty to DIY. Or use the provided hook_info() and implement the skin interface to register ones.'),
        '#weight'      => -107,
      ];
    }

    if ($scopes->is('background')) {
      $form['background'] = [
        '#type'        => 'checkbox',
        '#title'       => $this->t('Use CSS background'),
        '#description' => $this->t('Check this to turn the image into CSS background. This opens up the goodness of CSS, such as background cover, fixed attachment, etc. <br /><strong>Important!</strong> Requires an Aspect ratio, otherwise collapsed containers. Unless explicitly removed such as for GridStack which manages its own problem, or a min-height is added manually to <strong>.b-bg</strong> selector.'),
        '#weight'      => -98,
      ];
    }

    if ($layouts = $scopes->data('layouts')) {
      $form['layout'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Layout'),
        '#options'     => $this->toOptions($layouts),
        '#description' => $this->t('Requires a skin. The builtin layouts affects the entire items uniformly. Leave empty to DIY.'),
        '#weight'      => 2,
      ];
    }

    if ($captions = $scopes->data('captions')) {
      $form['caption'] = [
        '#type'        => 'checkboxes',
        '#title'       => $this->t('Caption fields'),
        '#options'     => $this->toOptions($captions),
        '#description' => $this->t('Enable any of the following fields as captions. These fields are treated and wrapped as captions.'),
        '#weight'      => 80,
        '#attributes'  => ['class' => ['form-wrapper--caption']],
      ];
    }

    if ($element = $base_form['view_mode'] ?? []) {
      $form['view_mode'] = $element;
    }

    $weight = -99;
    foreach (Element::children($form) as $key) {
      if (!isset($form[$key]['#weight'])) {
        $form[$key]['#weight'] = ++$weight;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function gridForm(array &$form, array $definition): void {
    $scopes = $this->toScopes($definition);
    $required = $scopes->is('grid_required');

    if (!$scopes->is('no_grid_header')) {
      $header  = $this->t('Group individual items as block grid?');
      $desc    = $definition['grid_header_desc'] ?? $this->gridHeaderDescription();
      $texts[] = ['#markup' => '<h3>' . $header . '</h3>'];
      $texts[] = ['#markup' => '<p>' . $desc . '</p>'];

      $form['grid_header'] = [
        '#type' => 'container',
        'items' => $texts,
        '#access' => !$required,
        '#attributes' => [
          'class' => [
            'form__title',
            'form__title--grids',
            'form-item',
            'form-item--subheader',
            'form-item--fullwidth',
          ],
        ],
      ];
    }

    $description = $this->t('Empty the value first if trouble with changing form states. The amount of block grid columns (1 - 12, or empty) for large monitors 64.063em  (1025px) up.');

    if ($scopes->is('slider')) {
      $description .= $this->t('<br /><strong>Requires</strong>:<ol><li>Any grid-related Display style,</li><li>Visible items,</li><li>Skin Grid for starter,</li><li>A reasonable amount of contents.</li></ol>');
    }

    $form['grid'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Grid large'),
      '#description' => $description,
      '#enforced'    => TRUE,
      '#required'    => $required,
      '#wrapper_attributes' => [
        'class' => [
          'form-item--full',
          'form-item--tooltip-bottom',
          'form-item--tooltip-wide',
        ],
      ],
    ];

    $form['grid_medium'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Grid medium'),
      '#description' => $this->t('Only accepts uniform columns (1 - 12, or empty) for medium devices 40.063em - 64em (641px - 1024px) up, even for Native Grid due to being pure CSS without JS.'),
    ];

    $form['grid_small'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Grid small'),
      '#description' => $this->t('Only accepts uniform columns (1 - 2, or empty) for small devices 0 - 40em (640px) up due to small real estate, even for Native Grid due to being pure CSS without JS. Below this value, always one column.'),
    ];

    if (!$scopes->is('grid_simple')) {
      $form['visible_items'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Visible items'),
        '#options'     => array_combine(range(1, 32), range(1, 32)),
        '#description' => $this->t('How many items per display at a time.'),
      ];

      $form['preserve_keys'] = [
        '#type'        => 'checkbox',
        '#title'       => $this->t('Preserve keys'),
        '#description' => $this->t('If checked, keys will be preserved. Default is FALSE which will reindex the grid chunk numerically.'),
        '#access'      => $scopes->is('grid_preserve_keys'),
      ];
    }

    $grids = [
      'grid_header',
      'grid_medium',
      'grid_small',
      'visible_items',
      'preserve_keys',
    ];

    foreach ($grids as $key) {
      if (isset($form[$key])) {
        $form[$key]['#enforced'] = TRUE;
        $form[$key]['#states'] = [
          'visible' => [
            'input[name$="[grid]"]' => ['!value' => ''],
          ],
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function closingForm(array &$form, array $definition): void {
    $this->finalizeForm($form, $definition);
  }

  /**
   * {@inheritdoc}
   */
  public function baseForm(array &$definition): array {
    $scopes     = $this->toScopes($definition);
    $data       = $scopes->get('data');
    $form       = [];
    $ui_url     = '/admin/config/media/blazy';
    $use_image  = !$scopes->is('no_image_style');
    $multimedia = $scopes->is('multimedia');

    if ($this->blazyManager->moduleExists('blazy_ui')) {
      $ui_url = Url::fromRoute('blazy.settings')->toString();
    }

    if ($use_image) {
      if (!$scopes->is('no_preload')) {
        $form['preload'] = [
          '#type'        => 'checkbox',
          '#title'       => $this->t('Preload'),
          '#weight'      => -111,
          '#description' => $this->t("Preload to optimize the loading of late-discovered resources. Normally large or hero images below the fold. By preloading a resource, you tell the browser to fetch it sooner than the browser would otherwise discover it before Native lazy or lazyloader JavaScript kicks in, or starts its own preload or decoding. The browser caches preloaded resources so they are available immediately when needed. Nothing is loaded or executed at preloading stage. <br>Just a friendly heads up: do not overuse this option, because not everything are critical, <a href=':url'>read more</a>.", [
            ':url' => 'https://www.drupal.org/node/3262804',
          ]),
          '#wrapper_attributes' => [
            'class' => [
              'form-item--preload',
              'form-item--tooltip-bottom',
            ],
          ],
        ];
      }

      if (!$scopes->is('no_loading')) {
        $loadings = ['auto', 'defer', 'eager', 'unlazy'];

        // It is defined in sub-modules, not Blazy.
        if ($scopes->is('slider')) {
          $loadings[] = 'slider';
        }
        $form['loading'] = [
          '#type'         => 'select',
          '#title'        => $this->t('Loading priority'),
          '#options'      => array_combine($loadings, $loadings),
          '#empty_option' => $this->t('lazy'),
          '#weight'       => -111,
          '#description'  => $this->t("Decide the `loading` attribute affected by the above fold aka onscreen critical contents. <ul><li>`lazy`, the default: defers loading below fold or offscreen images and iframes until users scroll near them.</li><li>`auto`: browser determines whether or not to lazily load. Only if uncertain about the above fold boundaries given different devices. </li><li>`eager`: loads right away. Similar effect like without `loading`, included for completeness. Good for above fold.</li><li>`defer`: trigger native lazy after the first row is loaded. Will disable global `No JavaScript: lazy` option on this particular field, <a href=':defer'>read more</a>.</li><li>`unlazy`: explicitly removes loading attribute enforced by core. Also removes old `data-[SRC|SRCSET|LAZY]` if `No JavaScript` is disabled. Best for the above fold.</li><li>`slider`, if applicable: will `unlazy` the first visible, and leave the rest lazyloaded. Best for sliders (one visible at a time), not carousels (multiple visible slides at once).</li></ul><b>Note</b>: lazy loading images/ iframes for the above fold is anti-pattern, avoid, <a href=':url' target='_blank'>read more</a>.", [
            ':url' => 'https://www.drupal.org/node/3262724',
            ':defer' => 'https://drupal.org/node/3120696',
          ]),
          '#wrapper_attributes' => [
            'class' => [
              'form-item--loading',
              'form-item--tooltip-bottom',
            ],
          ],
        ];
      }

      $form['image_style'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Image style'),
        '#options'     => $this->getEntityAsOptions('image_style'),
        '#weight'      => -108,
        '#description' => $this->t('The content image style. This will be treated as the fallback image to override the global option <a href=":url">Responsive image 1px placeholder</a>, which is normally smaller, if Responsive image are provided. Shortly, leave it empty to make Responsive image fallback respected. Otherwise this is the only image displayed. This image style is also used to provide dimensions not only for image/iframe but also any media entity like local video, where no images are even associated with, to have the designated dimensions in tandem with aspect ratio as otherwise no UI to customize for.', [':url' => $ui_url]),
        '#wrapper_attributes' => [
          'class' => [
            'form-item--image-style',
            'form-item--tooltip-bottom',
          ],
        ],
      ];
    }

    if ($scopes->is('switch')) {
      $form['media_switch'] = [
        '#type'         => 'select',
        '#title'        => $this->t('Media switcher'),
        '#options'      => [
          'content' => $this->t('Image linked to content'),
        ],
        '#empty_option' => $this->t('- None -'),
        '#description'  => $this->t('Clear cache if lightboxes do not appear here due to being permanently cached. <ol><li>Link to content: for aggregated small slicks.</li><li>Image to iframe: video is hidden below image until toggled, otherwise iframe is always displayed, and draggable fails. Aspect ratio applies.</li><li>(Quasi-)lightboxes: Colorbox, ElevateZoomPlus, Intense, Photobox, PhotoSwipe, Magnific Popup, Slick Lightbox, Splidebox, Zooming, etc. Depends on the enabled supported modules, or has known integration with Blazy. See docs or <em>/admin/help/blazy_ui</em> for details.</li></ol> Add <em>Thumbnail style</em> if using Photobox, Slick, or others which may need it. Try selecting "<strong>- None -</strong>" first before changing if trouble with this complex form states.'),
        '#weight'       => -99,
      ];

      // Optional lightbox integration.
      if ($lightboxes = $scopes->data('lightboxes')) {
        foreach ($lightboxes as $lightbox) {
          $name = Unicode::ucwords(str_replace('_', ' ', $lightbox));
          if ($lightbox == 'photobox') {
            $name .= ' (Deprecated)';
          }
          if ($lightbox == 'mfp') {
            $name = 'Magnific Popup';
          }
          $form['media_switch']['#options'][$lightbox] = $this->t('Image to @lightbox', ['@lightbox' => $name]);
        }

        // Re-use the same image style for both lightboxes.
        $box_styles = $this->getResponsiveImageOptions()
          + $this->getEntityAsOptions('image_style');
        $form['box_style'] = [
          '#type'        => 'select',
          '#title'       => $this->t('Lightbox image style'),
          '#options'     => $box_styles,
          '#weight'      => -97,
          '#description' => $this->t('Supports both Responsive and regular images.'),
        ];

        if ($multimedia) {
          $form['box_media_style'] = [
            '#type'        => 'select',
            '#title'       => $this->t('Lightbox video style'),
            '#options'     => $this->getEntityAsOptions('image_style'),
            '#description' => $this->t('Allows different lightbox video dimensions. Or can be used to have a swipable video if <a href=":url1">Blazy PhotoSwipe</a>, or <a href=":url2">Slick Lightbox</a>, or <a href=":url3">Splidebox</a> installed.', [
              ':url1' => 'https:drupal.org/project/blazy_photoswipe',
              ':url2' => 'https:drupal.org/project/slick_lightbox',
              ':url3' => 'https:drupal.org/project/splidebox',
            ]),
            '#weight'      => -96,
          ];
        }

        if (!$scopes->is('box_stateless')) {
          foreach (['box_caption', 'box_style', 'box_media_style'] as $key) {
            if (isset($form[$key])) {
              $form[$key]['#states'] = $this->getState(static::STATE_LIGHTBOX_ENABLED, $scopes);
            }
          }
        }
      }

      // Adds common supported entities for media integration.
      if ($multimedia) {
        $form['media_switch']['#options']['media'] = $this->t('Image to iFrame');
      }
    }

    // https://en.wikipedia.org/wiki/List_of_common_resolutions
    $ratio = ['1:1', '3:2', '4:3', '8:5', '16:9', 'fluid'];
    if (!$scopes->is('no_ratio')) {
      $form['ratio'] = [
        '#type'         => 'select',
        '#title'        => $this->t('Aspect ratio'),
        '#options'      => array_combine($ratio, $ratio),
        '#empty_option' => $this->t('- None -'),
        '#description'  => $this->t('Aspect ratio to get consistently responsive images and iframes. Coupled with Image style. And to fix layout reflow, excessive height issues, whitespace below images, collapsed container, no-js users, etc. <a href="@dimensions" target="_blank">Image styles and video dimensions</a> must <a href="@follow" target="_blank">follow the aspect ratio</a>. If not, images will be distorted. <a href="@link" target="_blank">Learn more</a>. <ul><li><b>Fixed ratio:</b> all images use the same aspect ratio mobile up. Use it to avoid JS works, or if it fails Responsive image. </li><li><b>Fluid:</b> aka dynamic, dimensions are calculated and JS works are attempted to fix it.</li><li><b>Leave empty:</b> to DIY (such as using CSS mediaquery), or when working with multi-image-style plugin like GridStack.</li></ul>', [
          '@dimensions'  => '//size43.com/jqueryVideoTool.html',
          '@follow'      => '//en.wikipedia.org/wiki/Aspect_ratio_%28image%29',
          '@link'        => '//www.smashingmagazine.com/2014/02/27/making-embedded-content-work-in-responsive-design/',
        ]),
        '#weight'        => -95,
      ];
    }

    $disabled = $scopes->is('no_view_mode');
    $target_type = $scopes->get('target_type');
    $is_fieldable = $target_type && $scopes->get('view_mode');
    if ($is_fieldable && !$disabled) {
      $form['view_mode'] = [
        '#type'        => 'select',
        '#options'     => $this->getViewModeOptions($target_type),
        '#title'       => $this->t('View mode'),
        '#description' => $this->t('Required to grab the fields, or to have custom entity display as fallback display. If it has fields, be sure the selected "View mode" is enabled, and the enabled fields here are not hidden there.'),
        '#weight'      => -106,
        '#enforced'    => TRUE,
      ];

      if ($this->blazyManager->moduleExists('field_ui')) {
        $form['view_mode']['#description'] .= ' ' . $this->t('Manage view modes on the <a href=":view_modes">View modes page</a>.', [':view_modes' => Url::fromRoute('entity.entity_view_mode.collection')->toString()]);
      }
    }

    if ($use_image || $scopes->is('thumbnail_style')) {
      $form['thumbnail_style'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Thumbnail style'),
        '#options'     => $this->getEntityAsOptions('image_style'),
        '#description' => $this->t('Usages: Placeholder replacement for image effects (blur, etc.), Photobox/PhotoSwipe thumbnail, or custom work with thumbnails. Be sure to have similar aspect ratio for the best blur effect. Leave empty to not use thumbnails.'),
        '#weight'      => -107,
      ];
    }

    // @todo this can also be used for local video poster image option.
    if (isset($data['images'])) {
      $form['image'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Main stage'),
        '#options'     => $this->toOptions($data['images'] ?: []),
        '#description' => $this->t('Main background/stage/poster image field with the only supported field types: <b>Image</b> or <b>Media</b> containing Image field. You may want to add a new Image field to this entity. Be sure to reuse the exact same image field across various entitiy types (Image, Remote video, Local video, etc.) within this particular entity (says, Media).'),
        '#prefix'      => '<h3 class="form__title form__title--fields form-item--subheader form-item--fullwidth">' . $this->t('Fields') . '</h3>',
      ];
    }

    $this->blazyManager->moduleHandler()->alter('blazy_base_form_element', $form, $definition);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function mediaSwitchForm(array &$form, array $definition): void {
    $scopes    = $this->toScopes($definition);
    $is_token  = $this->blazyManager->moduleExists('token');
    $base_form = $this->baseForm($definition);

    foreach (['media_switch', 'ratio'] as $key) {
      if ($element = $base_form[$key] ?? []) {
        $form[$key] = $element;
        if ($key == 'media_switch') {
          $form[$key]['#prefix'] = '<h3 class="form__title form__title--media-switch form-item--subheader form-item--fullwidth">' . $this->t('Media switcher') . '</h3>';
        }
      }
    }

    // Optional lightbox integration.
    if ($scopes->is('switch') && $scopes->is('lightbox')) {
      foreach (['box_style', 'box_media_style'] as $key) {
        if ($element = $base_form[$key] ?? []) {
          $form[$key] = $element;
        }
      }

      if (!$scopes->is('no_box_captions')) {
        $form['box_caption'] = [
          '#type'        => 'select',
          '#title'       => $this->t('Lightbox caption'),
          '#options'     => $this->getLightboxCaptionOptions(),
          '#weight'      => -95,
          '#description' => $this->t('Automatic will search for Alt text first, then Title text. Try selecting <strong>- None -</strong> first when changing if trouble with form states.'),
        ];

        $form['box_caption_custom'] = [
          '#title'       => $this->t('Lightbox custom caption'),
          '#type'        => 'textfield',
          '#weight'      => -94,
          '#states'      => $this->getState(static::STATE_LIGHTBOX_CUSTOM, $scopes),
          '#description' => $this->t('Multi-value rich text field will be mapped to each image by its delta.'),
        ];

        if ($is_token) {
          $entity_type = $scopes->get('entity.type');
          $target_type = $scopes->get('target_type');
          $types = $entity_type ? [$entity_type] : [];
          $types = $target_type ? array_merge($types, [$target_type]) : $types;

          if ($types) {
            $form['box_caption_custom']['#field_suffix'] = [
              '#theme'       => 'token_tree_link',
              '#text'        => $this->t('Tokens'),
              '#token_types' => $types,
            ];
          }
        }
      }
    }

    $this->blazyManager->moduleHandler()->alter('blazy_media_switch_form_element', $form, $definition);
  }

  /**
   * {@inheritdoc}
   */
  public function finalizeForm(array &$form, array $definition): void {
    $scopes    = $this->toScopes($definition);
    $settings  = $definition['settings'] ?? [];
    $admin_css = $scopes->is('admin_css');
    $admin_css = $admin_css ?: $this->blazyManager->config('admin_css', 'blazy.settings');
    $classes   = $this->getOpeningClasses($scopes);
    $excludes  = ['details', 'fieldset', 'hidden', 'markup', 'item', 'table'];
    $selects   = ['cache', 'optionset', 'view_mode'];

    // Disable the admin css in the off canvas menu, to avoid conflicts with
    // the active frontend theme.
    if ($admin_css && $router = Path::requestStack()) {
      $wrapper_format = $router->getCurrentRequest()->query->get('_wrapper_format');

      if ($wrapper_format && $wrapper_format === "drupal_dialog.off_canvas") {
        $admin_css = FALSE;
      }
    }

    // Prevents non-expected overrides.
    if (isset($form['grid'], $form['grid']['#description'])) {
      $description = $form['grid']['#description'];
      $form['grid']['#description'] = $description . $this->nativeGridDescription();
    }

    $this->blazyManager->moduleHandler()->alter('blazy_form_element', $form, $definition);

    // Accounts for hook_alter additions.
    $children  = Element::children($form);
    $grid_sets = [];
    $total     = count($children);

    if ($admin_css) {
      $classes[] = 'b-nativegrid--form';
      $options = [
        'count'   => $total,
        'classes' => $classes,
      ];

      $check      = $this->blazyManager->initNativeGrid($options);
      $grid_attrs = $check['attributes'];
      $grid_sets  = $check['settings'];
      $classes    = implode(' ', $grid_attrs['class']);
    }
    else {
      $classes = implode(' ', $classes);
    }

    $form['opening'] = [
      '#markup' => '<div class="' . $classes . '">',
      '#weight' => -120,
    ];

    $form['closing'] = [
      '#markup' => '</div>',
      '#weight' => 120,
    ];

    // Mostly babysitters to help few things out.
    foreach ($children as $delta => $key) {
      $type = $form[$key]['#type'] ?? NULL;
      if (!$type || in_array($type, $excludes)) {
        continue;
      }

      // If no defined default values, set them from settings.
      if (!isset($form[$key]['#default_value']) && isset($settings[$key])) {
        $value = is_array($settings[$key])
          ? array_values((array) $settings[$key])
          : $settings[$key];

        // @todo remove babysitter.
        if ($scopes->is('grid_required')
          && $key == 'grid'
          && empty($settings[$key])) {
          $value = 3;
        }
        $form[$key]['#default_value'] = $value;
      }

      // Trying to be nice with gazillion options.
      foreach (['attributes', 'wrapper_attributes'] as $attribute) {
        if (!isset($form[$key]["#$attribute"])) {
          $form[$key]["#$attribute"] = [];
        }
      }

      $attrs = &$form[$key]['#attributes'];
      $wrapper_attrs = &$form[$key]['#wrapper_attributes'];
      $content_attrs = [];

      if (isset($form[$key]['#description'])) {
        $attrs['class'][] = 'is-tooltip';
      }

      // Trying to be compact with gazillion options.
      if ($admin_css) {
        if ($grid_sets) {
          $blazy = $grid_sets['blazies']->reset($grid_sets);
          $blazy->set('delta', $delta);
        }

        // $form[$key]['#wrapper_attributes']['class'][] = 'grid';
        if ($type == 'checkbox' && $type != 'checkboxes') {
          // $form[$key]['#field_suffix'] = '&nbsp;';
          $form[$key]['#title_display'] = 'before';
        }
        elseif ($type == 'checkboxes' && !empty($form[$key]['#options'])) {
          $attrs['class'][] = 'form-wrapper--checkboxes';
          $attrs['class'][] = 'form-wrapper--' . str_replace('_', '-', $key);
          $count = count($form[$key]['#options']);
          $attrs['class'][] = 'form-wrapper--count-' . ($count > 3 ? 'max' : $count);

          foreach ($form[$key]['#options'] as $i => $option) {
            // $form[$key][$i]['#field_suffix'] = '&nbsp;';
            $form[$key][$i]['#title_display'] = 'before';
          }

          $box_count = count(Element::children($form[$key]));
          $attrs['data-b-w'][] = 12;
          $attrs['data-b-h'][] = $box_count > 6 ? (int) (($box_count / 2) + 1) : 3;
          $attrs['class'][] = 'grid';
        }

        $dummies['class'] = [];
        $this->blazyManager->gridItemAttributes($dummies, $content_attrs, $grid_sets);
        $wrapper_attrs = $this->blazyManager->merge($wrapper_attrs, $dummies);
        $wrapper_attrs['class'][] = 'grid--admin';

        if ($key == 'grid' || $key == 'style' && $scopes->is('grid_required')) {
          $wrapper_attrs['data-b-w'] = 12;
        }
      }

      $wrapper_attrs['class'][] = 'form-item--' . str_replace('_', '-', $key);

      // Select option babysitters.
      if ($type == 'select' && !in_array($key, $selects)) {
        $required = $form[$key]['#required'] ?? FALSE;
        if ($required) {
          unset($form[$key]['#empty_option']);
        }
        else {
          if (!isset($form[$key]['#empty_option'])) {
            $form[$key]['#empty_option'] = $this->t('- None -');
          }
        }
      }

      // Vanilla states babysitters.
      if ($scopes->is('vanilla') && !isset($form[$key]['#enforced'])) {
        $states['visible'][':input[name*="[vanilla]"]'] = ['checked' => FALSE];
        if (isset($form[$key]['#states'])) {
          $form[$key]['#states']['visible'][':input[name*="[vanilla]"]'] = ['checked' => FALSE];
        }
        else {
          $form[$key]['#states'] = $states;
        }
      }

      // Don't store values babysitters.
      if (($form[$key]['#access'] ?? 'x') == FALSE) {
        unset($form[$key]['#default_value']);
      }

      if (in_array($key, $scopes->data('deprecations'))) {
        unset($form[$key]['#default_value']);
      }
    }

    if ($admin_css) {
      $form['closing']['#attached']['library'][] = 'blazy/admin';
    }

    $this->blazyManager->moduleHandler()->alter('blazy_complete_form_element', $form, $definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheOptions(): array {
    $period = [
      0,
      60,
      180,
      300,
      600,
      900,
      1800,
      2700,
      3600,
      10800,
      21600,
      32400,
      43200,
      86400,
    ];

    $period = array_map([$this->dateFormatter, 'formatInterval'],
      array_combine($period, $period));
    $period[0] = '<' . $this->t('No caching') . '>';
    return $period + [Cache::PERMANENT => $this->t('Permanent')];
  }

  /**
   * {@inheritdoc}
   */
  public function getLightboxCaptionOptions(): array {
    return [
      'auto'         => $this->t('Automatic'),
      'alt'          => $this->t('Alt text'),
      'title'        => $this->t('Title text'),
      'alt_title'    => $this->t('Alt and Title'),
      'title_alt'    => $this->t('Title and Alt'),
      'entity_title' => $this->t('Content title'),
      'custom'       => $this->t('Custom'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityAsOptions($entity_type): array {
    return $this->blazyManager->getEntityAsOptions($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionsetOptions($entity_type): array {
    return $this->getEntityAsOptions($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getViewModeOptions($target_type): array {
    $view_modes = $this->entityDisplayRepository->getViewModeOptions($target_type) ?: [];
    return $this->toOptions($view_modes);
  }

  /**
   * {@inheritdoc}
   */
  public function getResponsiveImageOptions(): array {
    $options = [];
    if ($this->blazyManager->moduleExists('responsive_image')) {
      $image_styles = $this->blazyManager->loadMultiple('responsive_image_style');
      if (!empty($image_styles)) {
        foreach ($image_styles as $name => $image_style) {
          if ($image_style->hasImageStyleMappings()) {
            $options[$name] = Html::escape($image_style->label());
          }
        }
        uasort($options, 'strnatcasecmp');
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldOptions(
    array $target_bundles = [],
    array $allowed_field_types = [],
    $entity_type = 'media',
    $target_type = ''
  ): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array &$form, array $definition): void {}

  /**
   * {@inheritdoc}
   */
  public function fieldableForm(array &$form, array $definition): void {}

  /**
   * {@inheritdoc}
   */
  public function imageStyleForm(array &$form, array $definition): void {}

  /**
   * {@inheritdoc}
   */
  public function getSettingsSummary(array $definition): array {
    return [];
  }

  /**
   * Returns escaped options.
   */
  protected function toOptions(array $data) {
    return $this->blazyManager->toOptions($data);
  }

  /**
   * Verify the plugin scopes is initialized downstream.
   */
  protected function toScopes(array &$definition): BlazySettings {
    $scopes = $definition['scopes'] ?? $this->toPluginScopes();
    if (!$scopes->get('initializer')) {
      $definition['scopes'] = $scopes = $this->getScopes($definition);
      $scopes->set('initializer', get_called_class());
    }
    return $scopes;
  }

  /**
   * Returns native grid description.
   */
  protected function nativeGridDescription() {
    return $this->t('<br>Specific for <b>Native Grid</b>, two recipes: <ol><li><b>One-dimensional</b>: Input a single numeric column grid, acting as Masonry. <em>Best with</em>: scaled pictures.</li><li><b>Two-dimensional</b>: Input a space separated value with <code>WIDTHxHEIGHT</code> pair based on the amount of columns/ rows, at max 12, e.g.: <br><code>4x4 4x3 2x2 2x4 2x2 2x3 2x3 4x2 4x2</code> <br>This will resemble GridStack optionset <b>Tagore</b>. Any single value e.g.: <code>4x4</code> will repeat uniformly like one-dimesional. <br><em>Best with</em>: <ul><li><b>Use CSS background</b> ON.</li><li>Exact item amount or better more designated grids than lacking. Use a little math with the exact item amount to have gapless grids.</li><li>Disabled image aspect ratio to use grid ratio instead.</li></ul></li></ol>This requires any grid-related <b>Display style</b>. Unless required, leave empty to DIY, or to not build grids.');
  }

  /**
   * Get one of the pre-defined states used in this form.
   *
   * Thanks to SAM152 at colorbox.module for the little sweet idea.
   *
   * @param string $state
   *   The state to get that matches one of the state class constants.
   * @param \Drupal\blazy\BlazySettings $scopes
   *   The current scopes.
   *
   * @return array
   *   A corresponding form API state.
   */
  protected function getState($state, $scopes): array {
    $lightboxes = [];

    // @todo remove the second after complete migrations.
    $options = $scopes->data('lightboxes') ?: $this->blazyManager->getLightboxes();

    // @fixme this appears to be broken at some point of Drupal.
    foreach ($options as $key => $lightbox) {
      $lightboxes[$key]['value'] = $lightbox;
    }

    $states = [
      static::STATE_RESPONSIVE_IMAGE_STYLE_DISABLED => [
        'visible' => [
          'select[name$="[responsive_image_style]"]' => ['value' => ''],
        ],
      ],
      static::STATE_LIGHTBOX_ENABLED => [
        'visible' => [
          'select[name*="[media_switch]"]' => $lightboxes,
        ],
      ],
      static::STATE_LIGHTBOX_CUSTOM => [
        'visible' => [
          'select[name$="[box_caption]"]' => ['value' => 'custom'],
          // @fixme 'select[name*="[media_switch]"]' => $lightboxes,
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
      static::STATE_IMAGE_RENDERED_ENABLED => [
        'visible' => [
          'select[name$="[media_switch]"]' => ['!value' => 'rendered'],
        ],
      ],
    ];
    return $states[$state];
  }

  /**
   * Returns grid header description.
   */
  protected function gridHeaderDescription() {
    return $this->t('Depends on the <strong>Display style</strong>.');
  }

  /**
   * Returns form opening classes.
   */
  protected function getOpeningClasses($scopes): array {
    $namespace = $scopes->get('namespace', 'blazy');
    $classes = [];

    $items = ['blazy', 'slick', $namespace, 'half'];

    if ($scopes->is('_views')) {
      $items[] = 'views';
    }
    if ($scopes->is('vanilla')) {
      $items[] = 'vanilla';
    }
    if ($scopes->is('grid_required')) {
      $items[] = 'grid-required';
    }
    if ($plugin_id = $scopes->get('plugin_id')) {
      $items[] = 'plugin-' . str_replace('_', '-', $plugin_id);
    }
    if ($field_type = $scopes->get('field.type')) {
      $items[] = str_replace('_', '-', $field_type);
    }

    foreach ($items as $class) {
      $classes[] = 'form--' . $class;
    }

    $classes[] = 'b-tooltip';
    $classes[] = 'b-tooltip--lg';

    return $classes;
  }

  /**
   * Check scopes, a failsafe till sub-modules migrated.
   *
   * Temporary re-definitions during migration after BlazyFormatterTrait
   * ::getScopedFormElements() for sensible checks.
   *
   * @todo remove most after sub-module migrations.
   */
  private function checkScopes(&$scopes, array &$definition): void {
    $settings = $definition['settings'] ?? [];
    $lightboxes = $this->blazyManager->getLightboxes();
    $is_responsive = function_exists('responsive_image_get_image_dimensions');
    $namespace = $scopes->get('namespace') ?: ($definition['namespace'] ?? NULL);
    $plugin_id = $scopes->get('plugin_id') ?: ($definition['plugin_id'] ?? NULL);
    $target_type = $scopes->get('target_type') ?: ($definition['target_type'] ?? NULL);
    $entity_type = $scopes->get('entity.type') ?: ($definition['entity_type'] ?? NULL);
    $view_mode = $scopes->get('view_mode') ?: ($definition['view_mode'] ?? NULL);
    $vanilla = $scopes->isset('vanilla') || isset($definition['vanilla']);
    $switch = !$scopes->is('no_lightboxes') && isset($settings['media_switch']);

    $bools = [
      'background',
      'caches',
      'grid_required',
      'grid_simple',
      'multimedia',
      'nav',
      'no_box_captions',
      'no_grid_header',
      'no_image_style',
      'no_layouts',
      'no_lightboxes',
      'no_loading',
      'no_preload',
      'no_thumb_effects',
      'responsive_image',
      'style',
      'thumbnail_style',
      '_views',
    ];

    foreach ($bools as $bool) {
      $value = $scopes->is($bool) || !empty($definition[$bool]);
      $scopes->set('is.' . $bool, $value);
    }

    // Redefine for easy calls later due to sub-modules not migrated yet.
    // @todo remove after sub-modules migrations, and simplify all these at 3.x.
    $responsive = $is_responsive && $scopes->is('responsive_image');
    $sliders = in_array($namespace, ['slick', 'splide']);
    $scopes->set('data.lightboxes', $lightboxes)
      ->set('is.fieldable', $target_type && $entity_type)
      ->set('is.lightbox', count($lightboxes) > 0)
      ->set('is.responsive_image', $responsive)
      ->set('is.slider', $scopes->is('slider') ?: $sliders)
      ->set('is.switch', $switch)
      ->set('is.vanilla', $vanilla && isset($settings['vanilla']))
      ->set('entity.type', $entity_type)
      ->set('namespace', $namespace)
      ->set('plugin_id', $plugin_id)
      ->set('target_type', $target_type)
      ->set('view_mode', $view_mode);

    $data = [
      'deprecations',
      'captions',
      'classes',
      'images',
      'layouts',
      'links',
      'optionsets',
      'overlays',
      'skins',
      'thumbnails',
      'thumbnail_effect',
      'thumb_captions',
      'titles',
    ];

    $captions = [
      'alt' => $this->t('Alt'),
      'title' => $this->t('Title'),
    ];

    foreach (['captions', 'thumb_captions'] as $key) {
      $check = $definition[$key] ?? NULL;
      if ($check == 'default') {
        $scopes->set('data.' . $key, $captions);
      }
    }

    foreach ($data as $key) {
      $value = $scopes->data($key) ?: ($definition[$key] ?? NULL);
      // Respects empty arrays so the option is visible to raise awareness.
      if (is_array($value)) {
        $scopes->set('data.' . $key, $value);
      }
    }

    // Merge deprecated settings.
    $scopes->set('data.deprecations', BlazyDefault::deprecatedSettings(), TRUE);

    $forms = [
      'grid',
      'fieldable',
      'image_style',
      'media_switch',
    ];

    foreach ($forms as $key) {
      $value = $scopes->form($key) ?: !empty($definition[$key . '_form']);
      if (is_bool($value)) {
        $scopes->set('form.' . $key, $value);
      }
    }

    // Ensures merged once.
    if (!$scopes->is('scopes_merged') && $definition['scopes']) {
      $definition['scopes'] = $definition['scopes']->merge($scopes->storage());
      $scopes->set('is.scopes_merged', TRUE);
    }
  }

  /**
   * Returns the plugin scopes.
   */
  private function getScopes(array &$definition): BlazySettings {
    return $this->toPluginScopes($definition);
  }

}
