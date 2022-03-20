<?php

namespace Drupal\blazy\Plugin\Filter;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\FileInterface;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Render\FilteredMarkup;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;
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
   * Filter manager.
   *
   * @var \Drupal\filter\FilterPluginManager
   */
  protected $filterManager;

  /**
   * The blazy admin service.
   *
   * @var \Drupal\blazy\Form\BlazyAdminInterface
   */
  protected $blazyAdmin;

  /**
   * The blazy oembed service.
   *
   * @var \Drupal\blazy\Media\BlazyOEmbedInterface
   */
  protected $blazyOembed;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * The filter HTML plugin.
   *
   * @var \Drupal\filter\Plugin\Filter\FilterHtml
   */
  protected $htmlFilter;

  /**
   * The langcode.
   *
   * @var string
   */
  protected $langcode;

  /**
   * The result.
   *
   * @var \Drupal\filter\FilterProcessResult
   */
  protected $result;

  /**
   * The excluded settings to fetch from attributes.
   *
   * @var array
   */
  protected $excludedSettings = ['filter_tags'];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);

    $instance->root = $instance->root ?? Blazy::root($container);
    $instance->entityFieldManager = $instance->entityFieldManager ?? $container->get('entity_field.manager');
    $instance->filterManager = $instance->filterManager ?? $container->get('plugin.manager.filter');
    $instance->blazyAdmin = $instance->blazyAdmin ?? $container->get('blazy.admin');
    $instance->blazyOembed = $instance->blazyOembed ?? $container->get('blazy.oembed');
    $instance->blazyManager = $instance->blazyOembed->blazyManager();

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettings($text) {
    $settings = &$this->settings;
    $settings += BlazyDefault::lazySettings();
    $definitions = $this->entityFieldManager->getFieldDefinitions('media', 'remote_video');

    $settings['plugin_id'] = $plugin_id = $this->getPluginId();
    $settings['id'] = $id = BlazyFilterUtil::getId($plugin_id);
    $is_media_library = $definitions && isset($definitions['field_media_oembed_video']);

    $this->preSettings($settings);

    $blazies = $settings['blazies'];
    $exist = $blazies->is('resimage');

    $blazies->set('is.filter', TRUE)
      ->set('is.media_library', $is_media_library)
      ->set('is.unsafe', TRUE)
      ->set('libs.filter', TRUE);

    if ($style = ($settings['hybrid_style'] ?? FALSE)) {
      if ($exist && $resimage = $this->blazyManager->entityLoad($style, 'responsive_image_style')) {
        $settings['responsive_image_style'] = $style;
        $blazies->set('resimage.style', $resimage);
      }
      else {
        $settings['image_style'] = $style;
      }
    }

    if (!isset($this->htmlFilter)) {
      $this->htmlFilter = $this->filterManager->createInstance('filter_html', [
        'settings' => [
          'allowed_html' => '<a href hreflang target rel> <em> <strong> <b> <i> <cite> <code> <br>',
          'filter_html_help' => FALSE,
          'filter_html_nofollow' => FALSE,
        ],
      ]);
    }

    $this->blazyManager->postSettings($settings);
    $blazies->set('lightbox.gallery_id', $id)
      ->set('css.id', $id)
      ->set('filter.plugin_id', $plugin_id);

    return $settings;
  }

  /**
   * Prepare settings.
   */
  protected function preSettings(array &$settings) {
    $this->blazyManager->preSettings($settings);
  }

  /**
   * {@inheritdoc}
   */
  public function buildImageItem(array &$build, &$node) {
    $settings = &$build['settings'];
    $blazies = $settings['blazies'];
    $src = BlazyFilterUtil::getValidSrc($node);

    if ($src) {
      // If starts with 2 slashes, it is always external.
      if (mb_substr($src, 0, 2) === '//') {
        // We need to query stored SRC, https is enforced.
        $src = 'https:' . $src;
      }

      if ($node->tagName == 'img') {
        $this->getImageItemFromImageSrc($build, $node, $src);
      }
      elseif ($node->tagName == 'iframe') {
        try {
          // Prevents invalid video URL (404, etc.) from screwing up.
          $this->getImageItemFromIframeSrc($build, $node, $src);
        }
        catch (\Exception $ignore) {
          // Do nothing, likely local work without internet, or the site is
          // down. No need to be chatty on this.
        }
      }
    }

    $item = $build['item'] ?? NULL;
    if ($item) {
      $item->alt = $node->getAttribute('alt') ?: ($item->alt ?? '');
      $item->title = $node->getAttribute('title') ?: ($item->title ?? '');

      // Supports hard-coded image url without file API.
      if ($uri = BlazyFile::uri($item)) {
        $settings['uri'] = $uri;
        $blazies->set('uri', $uri);

        // @todo remove.
        if (empty($item->width) && $data = @getimagesize($uri)) {
          [$item->width, $item->height] = $data;
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

        // Supports other caption source if not using Filter caption.
        if (empty($build['captions'])) {
          $build['captions']['alt'] = ['#markup' => $markup];
        }

        if (isset($settings['box_caption']) && $settings['box_caption'] == 'inline') {
          $settings['box_caption'] = $markup;
        }

        $this->cleanupImageCaption($build, $node, $item);
      }
    }
    return $item;
  }

  /**
   * Prepares the blazy.
   */
  protected function prepareSettings(\DOMElement $node, array &$settings) {
    $blazies = $settings['blazies'];
    if ($check = $node->getAttribute('settings')) {
      $check = str_replace("'", '"', $check);
      $check = Json::decode($check);
      if ($check) {
        $settings = array_merge($settings, $check);
      }
    }

    // Merge all defined attributes into settings for convenient.
    $defaults = $this->defaultConfiguration()['settings'] ?? [];
    if ($defaults) {
      foreach ($defaults as $key => $value) {
        if (in_array($key, $this->excludedSettings)) {
          continue;
        }

        $type = gettype($value);

        if ($node->hasAttribute($key)) {
          $node_value = $node->getAttribute($key);
          settype($node_value, $type);
          $settings[$key] = $node_value;
        }
      }
    }

    if (isset($settings['count'])) {
      $blazies->set('count', $settings['count']);
    }

    BlazyFilterUtil::toGrid($node, $settings);
  }

  /**
   * Render the output.
   */
  protected function render(\DOMElement $node, array $output) {
    $dom = $node->ownerDocument;
    $altered_html = $this->blazyManager->getRenderer()->render($output);

    // Load the altered HTML into a new DOMDocument, retrieve element.
    $updated_nodes = Html::load($altered_html)->getElementsByTagName('body')
      ->item(0)
      ->childNodes;

    foreach ($updated_nodes as $updated_node) {
      // Import the updated from the new DOMDocument into the original
      // one, importing also the child nodes of the updated node.
      $updated_node = $dom->importNode($updated_node, TRUE);
      $node->parentNode->insertBefore($updated_node, $node);
    }

    // Finally, remove the original blazy node.
    if ($node->parentNode) {
      $node->parentNode->removeChild($node);
    }
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
  public function getImageItemFromImageSrc(array &$build, $node, $src) {
    $settings = &$build['settings'];
    $blazies = $settings['blazies'];
    // Attempts to get the correct URI with hard-coded URL if applicable.
    $uri = $settings['uri'] = BlazyFile::buildUri($src);
    $uuid = $node->getAttribute('data-entity-uuid');
    $blazies->set('entity.uuid', $uuid);

    $file = BlazyFile::item(NULL, $settings);

    // Uploaded image has UUID with file API.
    if ($file instanceof FileInterface) {
      $uuid = $uuid ?: $file->uuid();

      if ($data = BlazyImage::fromAny($file, $settings)) {
        $blazies->set('entity.uuid', $uuid);
        $build = NestedArray::mergeDeep($build, $data);
      }
    }
    else {
      // Manually hard-coded image has no UUID, nor file API.
      // URI validity is not crucial, URL is the bare minimum for Blazy to work.
      $settings['uri'] = $uri ?: $src;

      if ($uri) {
        $build['item'] = BlazyImage::fake($settings);
      }
      else {
        // At least provide root URI to figure out image dimensions.
        $settings['uri_root'] = mb_substr($src, 0, 4) === 'http' ? $src : $this->root . $src;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getImageItemFromIframeSrc(array &$build, &$node, $src) {
    $settings = &$build['settings'];
    $blazies = $settings['blazies'];

    // Iframe with data: alike scheme is a serious kidding, strip it earlier.
    $blazies->set('media.input_url', $src);
    $this->blazyOembed->checkInputUrl($settings);

    // @todo figure out to not hard-code `field_media_oembed_video`.
    $media = NULL;
    if ($blazies->is('media_library')) {
      $media = $this->blazyManager->loadByProperties([
        'field_media_oembed_video' => $blazies->get('media.input_url'),
      ], 'media');
      $media = reset($media);
    }

    // Runs after type, width and height set, if any, to not recheck them.
    $this->blazyOembed->build($build, $media);
  }

  /**
   * Provides the grid item attributes, and caption, if any.
   */
  protected function buildItemAttributes(array &$build, $node) {
    $sets = &$build['settings'];
    $blazies = $sets['blazies'];
    $blazies->set('is.blazy_tag', TRUE);

    if ($caption = $node->getAttribute('caption')) {
      $build['captions']['alt'] = ['#markup' => $this->filterHtml($caption)];
      $node->removeAttribute('caption');
    }

    if ($attributes = BlazyFilterUtil::getAttribute($node)) {
      // Move it to .grid__content for better displays like .well/ .card.
      if (!empty($attributes['class'])) {
        $build['content_attributes']['class'] = $attributes['class'];
        unset($attributes['class']);
      }
      $build['attributes'] = $attributes;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildItemSettings(array &$build, $node) {
    $settings = &$build['settings'];
    $blazies = $settings['blazies'];
    // Set an image style based on node data properties.
    // See https://www.drupal.org/project/drupal/issues/2061377,
    // https://www.drupal.org/project/drupal/issues/2822389, and
    // https://www.drupal.org/project/inline_responsive_images.
    $update = FALSE;
    if ($style = $node->getAttribute('data-image-style')) {
      $update = TRUE;
      $settings['image_style'] = $style;
    }

    if ($blazies->is('resimage')
      && $style = $node->getAttribute('data-responsive-image-style')) {
      $settings['responsive_image_style'] = $style;
      $update = TRUE;
    }

    $settings['width'] = $node->getAttribute('width');
    $settings['height'] = $node->getAttribute('height');

    if ($update) {
      // Checks for [Responsive] image styles at individual items.
      BlazyImage::styles($settings, TRUE);
    }
  }

  /**
   * Return sanitized caption, stolen from Filter caption.
   */
  protected function filterHtml($text) {
    // Read the data-caption attribute's value, then delete it.
    $caption = Html::escape($text);

    // Sanitize caption: decode HTML encoding, limit allowed HTML tags; only
    // allow inline tags that are allowed by default, plus <br>.
    $caption = Html::decodeEntities($caption);
    $filtered_caption = $this->htmlFilter->process($caption, $this->langcode);

    if (isset($this->result)) {
      $this->result->addCacheableDependency($filtered_caption);
    }

    return FilteredMarkup::create($filtered_caption->getProcessedText());
  }

  /**
   * Provides media switch form.
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
      '#default_value' => $this->settings['media_switch'] ?? '',
      '#description' => $this->t('<ul><li><b>Image to iframe</b> will play video when toggled.</li><li><b>Image to lightbox</b> (Colorbox, Photobox, PhotoSwipe, Slick Lightbox, Zooming, Intense, etc.) will display media in lightbox.</li></ul>Both can stand alone or grouped as a gallery. To build a gallery, use the grid shortcodes.'),
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
      '#default_value' => $this->settings['hybrid_style'] ?? '',
      '#description' => $this->t('Fallback (Responsive) image style when <code>[data-image-style]</code> or <code>[data-responsive-image-style]</code> attributes are not present, see https://drupal.org/node/2061377.'),
    ];

    $form['box_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Lightbox (Responsive) image style'),
      '#options' => $styles,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->settings['box_style'] ?? '',
    ];

    $captions = $this->blazyAdmin->getLightboxCaptionOptions();
    unset($captions['entity_title'], $captions['custom']);
    $form['box_caption'] = [
      '#type' => 'select',
      '#title' => $this->t('Lightbox caption'),
      '#options' => $captions + ['inline' => $this->t('Caption filter')],
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->settings['box_caption'] ?? '',
      '#description' => $this->t('Automatic will search for Alt text first, then Title text. <br>Image styles only work for uploaded images, not hand-coded ones. Caption filter will use <code>data-caption</code> normally managed by Caption filter.'),
    ];
  }

}
