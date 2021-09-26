<?php

namespace Drupal\blazy\Plugin\Filter;

use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\Plugin\FilterBase;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazyUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides base filter class.
 */
abstract class BlazyFilterBase extends FilterBase implements BlazyFilterInterface, ContainerFactoryPluginInterface {

  /**
   * The app root.
   *
   * @var string
   */
  protected $root;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The blazy admin service.
   *
   * @var \Drupal\blazy\Form\BlazyAdminInterface
   */
  protected $blazyAdmin;

  /**
   * The blazy oembed service.
   *
   * @var \Drupal\blazy\BlazyOEmbedInterface
   */
  protected $blazyOembed;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);

    $instance->root = isset($instance->root) ? $instance->root : $container->get('app.root');
    $instance->entityFieldManager = isset($instance->entityFieldManager) ? $instance->entityFieldManager : $container->get('entity_field.manager');
    $instance->blazyAdmin = isset($instance->blazyAdmin) ? $instance->blazyAdmin : $container->get('blazy.admin');
    $instance->blazyOembed = isset($instance->blazyOembed) ? $instance->blazyOembed : $container->get('blazy.oembed');
    $instance->blazyManager = $instance->blazyOembed->blazyManager();

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildImageItem(array &$build, &$node) {
    $settings = &$build['settings'];
    $item = NULL;

    // Checks if we have a valid file entity, not hard-coded image URL.
    // Prioritize data-src for sub-module filters after Blazy.
    $src = $node->getAttribute('data-src');
    if (empty($src)) {
      $src = $node->getAttribute('src');
    }

    if ($src) {
      // Prevents data URI from screwing up.
      $data_uri = mb_substr($src, 0, 10) === 'data:image';
      if (!$data_uri) {
        // If starts with 2 slashes, it is always external.
        if (mb_substr($src, 0, 2) === '//') {
          // We need to query stored SRC, https is enforced.
          $src = 'https:' . $src;
        }

        if ($node->tagName == 'img') {
          $item = $this->getImageItemFromImageSrc($settings, $node, $src);
        }
        elseif ($node->tagName == 'iframe') {
          try {
            // Prevents invalid video URL (404, etc.) from screwing up.
            $item = $this->getImageItemFromIframeSrc($settings, $node, $src);
          }
          catch (\Exception $ignore) {
            // Do nothing, likely local work without internet, or the site is
            // down. No need to be chatty on this.
          }
        }
      }
    }

    if ($item) {
      $item->alt = $node->getAttribute('alt') ?: (isset($item->alt) ? $item->alt : '');
      $item->title = $node->getAttribute('title') ?: (isset($item->title) ? $item->title : '');

      // Supports hard-coded image url without file API.
      if (!empty($item->uri) && empty($item->width)) {
        if ($data = @getimagesize($item->uri)) {
          list($item->width, $item->height) = $data;
        }
      }
    }

    $build['item'] = $item;
  }

  /**
   * {@inheritdoc}
   */
  public function buildImageCaption(array &$build, &$node) {
    $item = $this->getCaptionElement($node);

    // Sanitization was done by Caption filter when arriving here, as
    // otherwise we cannot see this figure, yet provide fallback.
    if ($item) {
      if ($text = $item->ownerDocument->saveXML($item)) {
        $settings = &$build['settings'];
        $markup = Xss::filter(trim($text), BlazyDefault::TAGS);
        $build['captions']['alt'] = ['#markup' => $markup];

        if (isset($settings['box_caption']) && $settings['box_caption'] == 'inline') {
          $settings['box_caption'] = $markup;
        }

        $this->cleanupImageCaption($build, $node, $item);
      }
    }
    return $item;
  }

  /**
   * Returns the expected caption DOMElement.
   */
  protected function getCaptionElement($node) {
    if ($node->parentNode && $node->parentNode->tagName === 'figure') {
      $caption = $node->parentNode->getElementsByTagName('figcaption');
      return ($caption && $caption->item(0)) ? $caption->item(0) : NULL;
    }
    return NULL;
  }

  /**
   * Cleanups image caption.
   */
  protected function cleanupImageCaption(array &$build, &$node, &$item) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function getImageItemFromImageSrc(array &$settings, $node, $src) {
    $data['item'] = NULL;
    $uuid = $node->hasAttribute('data-entity-uuid') ? $node->getAttribute('data-entity-uuid') : '';

    // Uploaded image has UUID with file API.
    if ($uuid && $file = $this->blazyManager->getEntityRepository()->loadEntityByUuid('file', $uuid)) {
      $data = $this->blazyOembed->getImageItem($file);
      if (isset($data['settings'])) {
        $settings = array_merge($settings, $data['settings']);
        $settings['entity_uuid'] = $uuid;
      }
    }
    else {
      // Manually hard-coded image has no UUID, nor file API.
      $settings['uri'] = $src;

      // Attempts to get the correct URI with hard-coded URL if applicable.
      if ($uri = BlazyUtil::buildUri($src)) {
        $settings['uri'] = $uri;
        $data['item'] = Blazy::image($settings);
      }
      else {
        // At least provide root URI to figure out image dimensions.
        $settings['uri_root'] = mb_substr($src, 0, 4) === 'http' ? $src : $this->root . $src;
      }
    }
    return $data['item'];
  }

  /**
   * {@inheritdoc}
   */
  public function getImageItemFromIframeSrc(array &$settings, &$node, $src) {
    // Iframe with data: alike scheme is a serious kidding, strip it earlier.
    $settings['input_url'] = $src;
    $this->blazyOembed->checkInputUrl($settings);
    $data['item'] = NULL;

    // @todo figure out to not hard-code `field_media_oembed_video`.
    if (!empty($settings['is_media_library'])) {
      $media = $this->blazyManager->getEntityTypeManager()->getStorage('media')->loadByProperties(['field_media_oembed_video' => $settings['input_url']]);
      $media = reset($media);
    }

    // We have media entity.
    if (isset($media) && $media) {
      $data['settings'] = $settings;
      $this->blazyOembed->getMediaItem($data, $media);

      // Update data with local image.
      $settings = array_merge($settings, $data['settings']);
    }
    // Attempts to build safe embed URL directly from oEmbed resource.
    else {
      $data['item'] = $this->blazyOembed->getExternalImageItem($settings);

      // Runs after type, width and height set, if any, to not recheck them.
      $this->blazyOembed->build($settings);
    }
    return $data['item'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettings($text) {
    $settings = $this->settings + BlazyDefault::lazySettings();
    $definitions = $this->entityFieldManager->getFieldDefinitions('media', 'remote_video');

    $settings['_check_protocol'] = TRUE;
    $settings['is_media_library'] = $definitions && isset($definitions['field_media_oembed_video']);
    $settings['_resimage'] = $this->blazyManager->getModuleHandler()->moduleExists('responsive_image');

    if (isset($settings['hybrid_style']) && $style = $settings['hybrid_style']) {
      $settings['resimage'] = $this->blazyManager->entityLoad($style, 'responsive_image_style');
      if ($settings['_resimage'] && $settings['resimage']) {
        $settings['responsive_image_style'] = $style;
      }
      else {
        $settings['image_style'] = $style;
      }
    }

    $this->blazyManager->getCommonSettings($settings);
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function buildItemSettings(array &$build, $node) {
    $settings = &$build['settings'];
    // Set an image style based on node data properties.
    // See https://www.drupal.org/project/drupal/issues/2061377,
    // https://www.drupal.org/project/drupal/issues/2822389, and
    // https://www.drupal.org/project/inline_responsive_images.
    $settings['uri'] = $settings['image_url'] = '';
    if ($image_style = $node->getAttribute('data-image-style')) {
      $settings['image_style'] = $image_style;
    }

    if (!empty($settings['_resimage'])
      && $resimage_style = $node->getAttribute('data-responsive-image-style')) {
      $settings['responsive_image_style'] = $resimage_style;
      $settings['resimage'] = $this->blazyManager->entityLoad($resimage_style, 'responsive_image_style');
    }

    $settings['width'] = $node->getAttribute('width');
    $settings['height'] = $node->getAttribute('height');
    $settings['media_switch'] = empty($settings['media_switch']) ? $this->settings['media_switch'] : $settings['media_switch'];
  }

  /**
   * {@inheritdoc}
   */
  protected function mediaSwitchForm(array &$form) {
    $lightboxes = $this->blazyManager->getLightboxes();

    $form['media_switch'] = [
      '#type' => 'select',
      '#title' => $this->t('Media switcher'),
      '#options' => [
        'media' => $this->t('Image to iframe'),
      ],
      '#empty_option' => $this->t('- None -'),
      '#default_value' => isset($this->settings['media_switch']) ? $this->settings['media_switch'] : '',
      '#description' => $this->t('<ul><li><b>Image to iframe</b> will play video when toggled.</li><li><b>Image to lightbox</b> (Colorbox, Photobox, PhotoSwipe, Slick Lightbox, Zooming, Intense, etc.) will display media in lightbox.</li></ul>Both can stand alone or grouped as a gallery. To build a gallery, add <code>data-column="1 3 4"</code> or <code>data-grid="1 3 4"</code> to the first image/ iframe only.'),
    ];

    if (!empty($lightboxes)) {
      foreach ($lightboxes as $lightbox) {
        $name = Unicode::ucwords(str_replace('_', ' ', $lightbox));
        $form['media_switch']['#options'][$lightbox] = $this->t('Image to @lightbox', ['@lightbox' => $name]);
      }
    }

    $styles = $this->blazyAdmin->getResponsiveImageOptions() + $this->blazyAdmin->getEntityAsOptions('image_style');
    $form['hybrid_style'] = [
      '#type' => 'select',
      '#title' => $this->t('(Responsive) image style'),
      '#options' => $styles,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => isset($this->settings['hybrid_style']) ? $this->settings['hybrid_style'] : '',
      '#description' => $this->t('Fallback (Responsive) image style when <code>[data-image-style]</code> or <code>[data-responsive-image-style]</code> attributes are not present, see https://drupal.org/node/2061377.'),
    ];

    $form['box_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Lightbox (Responsive) image style'),
      '#options' => $styles,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => isset($this->settings['box_style']) ? $this->settings['box_style'] : '',
    ];

    $captions = $this->blazyAdmin->getLightboxCaptionOptions();
    unset($captions['entity_title'], $captions['custom']);
    $form['box_caption'] = [
      '#type' => 'select',
      '#title' => $this->t('Lightbox caption'),
      '#options' => $captions + ['inline' => $this->t('Caption filter')],
      '#empty_option' => $this->t('- None -'),
      '#default_value' => isset($this->settings['box_caption']) ? $this->settings['box_caption'] : '',
      '#description' => $this->t('Automatic will search for Alt text first, then Title text. <br>Image styles only work for uploaded images, not hand-coded ones.'),
    ];
  }

}
