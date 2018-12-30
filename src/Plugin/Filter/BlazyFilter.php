<?php

namespace Drupal\blazy\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\media\IFrameUrlHelper;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Drupal\media\OEmbed\UrlResolverInterface;
use Drupal\blazy\BlazyManagerInterface;
use Drupal\blazy\Dejavu\BlazyVideoTrait;
use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatterBaseTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter to lazyload image or iframe elements.
 *
 * Best after Align images, caption images.
 *
 * @Filter(
 *   id = "blazy_filter",
 *   title = @Translation("Blazy"),
 *   description = @Translation("Lazyload inline images, or video iframes using Blazy."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   settings = {
 *     "filter_tags" = {"img" = "img", "iframe" = "iframe"},
 *     "media_switch" = "",
 *   },
 *   weight = 3
 * )
 */
class BlazyFilter extends FilterBase implements ContainerFactoryPluginInterface {

  use BlazyFormatterBaseTrait;
  use BlazyVideoTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entity_manager, BlazyManagerInterface $blazy_manager, ResourceFetcherInterface $resource_fetcher, UrlResolverInterface $url_resolver, IFrameUrlHelper $iframe_url_helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityManager = $entity_manager;
    $this->blazyManager = $blazy_manager;
    $this->mediaResourceFetcher = $resource_fetcher;
    $this->mediaUrlResolver = $url_resolver;
    $this->mediaIframeUrlHelper = $iframe_url_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager'),
      $container->get('blazy.manager'),
      $container->get('media.oembed.resource_fetcher'),
      $container->get('media.oembed.url_resolver'),
      $container->get('media.oembed.iframe_url_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    $allowed_tags = array_values((array) $this->settings['filter_tags']);
    if (empty($allowed_tags)) {
      return $result;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    foreach ($allowed_tags as $allowed_tag) {
      $nodes = $dom->getElementsByTagName($allowed_tag);
      if ($nodes->length > 0) {
        foreach ($nodes as $node) {
          if ($node->hasAttribute('data-unblazy')) {
            continue;
          }

          // Build Blazy elements with lazyloaded image or iframe.
          $settings = $this->buildSettings($node);
          $build = [
            'item' => $this->buildImageItem($node, $settings),
            'settings' => $settings,
          ];

          $output = $this->blazyManager->getImage($build);
          $altered_html = $this->blazyManager->getRenderer()->render($output);

          // Load the altered HTML into a new DOMDocument, retrieve the element.
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
          $node->parentNode->removeChild($node);
        }
      }
    }

    // Attach Blazy component libraries.
    $all = ['blazy' => TRUE, 'filter' => TRUE, 'media' => TRUE, 'ratio' => TRUE];
    $result->setProcessedText(Html::serialize($dom))
      ->addAttachments($this->blazyManager->attach($all));

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      return $this->t('
        <p>Image or iframe is lazyloaded. To disable, add attribute <code>data-unblazy</code>:</p>
        <ul>
            <li><code>&lt;img data-unblazy /&gt;</code></li>
            <li><code>&lt;iframe data-unblazy /&gt;</code></li>
        </ul>');
    }
    else {
      return $this->t('To disable lazyload, add attribute <code>data-unblazy</code> to <code>&lt;img&gt;</code> or <code>&lt;iframe&gt;</code> elements. Examples: <code>&lt;img data-unblazy</code> or <code>&lt;iframe data-unblazy</code>.');
    }
  }

  /**
   * Returns the faked image item for the image, uploaded or hard-coded.
   *
   * @param object $node
   *   The HTML DOM object.
   * @param array $settings
   *   The settings array being modified.
   *
   * @return object
   *   The faked image item.
   */
  private function buildImageItem($node, array &$settings = []) {
    $item = new \stdClass();
    $item->uri = $settings['uri'];
    $item->entity = NULL;
    $uuid = $node->hasAttribute('data-entity-uuid') ? $node->getAttribute('data-entity-uuid') : '';

    if ($uuid && $node->hasAttribute('src')) {
      $file = $this->entityManager->loadEntityByUuid('file', $uuid);
      if ($file) {
        $data = $this->getImageItem($file);
        $item = $data['item'];
        $settings = array_merge($settings, $data['settings']);
      }
    }

    // Responsive image with aspect ratio requires an extra container to work
    // with Align/ Caption images filters.
    $settings['media_attributes']['class'] = ['media-wrapper', 'media-wrapper--blazy'];
    // Copy all attributes of the original node to the $item _attributes.
    if ($node->attributes->length) {
      foreach ($node->attributes as $attribute) {
        // Move classes (align-BLAH,etc) to Blazy container, not image so to
        // work with alignments and aspect ratio.
        if ($attribute->nodeName == 'class') {
          $settings['media_attributes']['class'][] = $attribute->nodeValue;
        }
        else {
          $item->_attributes[$attribute->nodeName] = $attribute->nodeValue;
        }
      }
    }

    return $item;
  }

  /**
   * Returns the settings for the current $node.
   *
   * @param object $node
   *   The HTML DOM object.
   *
   * @return array
   *   The settings for the current $node.
   */
  private function buildSettings($node) {
    $src = $url = $node->getAttribute('src');
    $width = $node->getAttribute('width');
    $height = $node->getAttribute('height');

    if (!$width && $node->tagName == 'img') {
      if ($url && $data = @getimagesize(DRUPAL_ROOT . $url)) {
        list($width, $height) = $data;
      }
    }

    $settings = ['ratio' => !$width ? '' : 'fluid', 'image_url' => $url];
    $uri = file_build_uri($url);

    if ($node->tagName == 'iframe') {
      $resource = $this->buildOembed($settings, $src);
      if ($resource) {
        $uri = $settings['image_url'] = $resource->getThumbnailUrl()->getUri();
        $width = !$width ? $resource->getWidth() : $width;
        $height = !$height ? $resource->getHeight() : $height;
      }

      $settings['ratio'] = !$width ? '16:9' : 'fluid';
    }

    return [
      'blazy' => TRUE,
      'lazy' => 'blazy',
      'iframe_lazy' => TRUE,
      'uri' => $uri,
      'width' => $width,
      'height' => $height,
      'media_switch' => $node->tagName == 'iframe' ? $this->settings['media_switch'] : '',
    ] + $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['filter_tags'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enable HTML tags'),
      '#options' => [
        'img' => $this->t('Image'),
        'iframe' => $this->t('Video iframe'),
      ],
      '#default_value' => empty($this->settings['filter_tags']) ? [] : array_values((array) $this->settings['filter_tags']),
      '#description' => $this->t('Best after Align/ Caption images, else broken. If any issue with display, do not embed Blazy within Caption filter. To disable per item, add attribute <code>data-unblazy</code>.'),
    ];

    $form['media_switch'] = [
      '#type' => 'select',
      '#title' => $this->t('Media switcher'),
      '#options' => [
        'media' => $this->t('Image to iframe'),
      ],
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->settings['media_switch'],
      '#description' => $this->t('<b>Image to iframe</b> will hide iframe behind image till toggled. Autoplay is not working correctly, simply disable. Only enable if you can override <code>hook_preprocess_media_oembed_iframe()</code> to add <code>autoplay</code> or <code>auto_play</code> query params.'),
    ];

    return $form;
  }

}
