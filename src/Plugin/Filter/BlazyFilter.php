<?php

namespace Drupal\blazy\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazyOEmbed;
use Drupal\blazy\Dejavu\BlazyVideoTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter to lazyload image, or iframe elements.
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

  use BlazyVideoTrait;

  /**
   * An entity manager object.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityRepositoryInterface $entity_repository, ImageFactory $image_factory, BlazyOEmbed $blazy_oembed) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityRepository = $entity_repository;
    $this->imageFactory = $image_factory;
    $this->blazyOembed = $blazy_oembed;
    $this->blazyManager = $blazy_oembed->blazyManager();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.repository'),
      $container->get('image.factory'),
      $container->get('blazy.oembed')
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
    $settings['grid'] = stristr($text, 'data-grid') !== FALSE;
    $settings['column'] = stristr($text, 'data-column') !== FALSE;
    $settings['media_switch'] = $switch = $this->settings['media_switch'];
    $settings['lightbox'] = ($switch && in_array($switch, $this->blazyManager->getLightboxes())) ? $switch : FALSE;
    $settings['plugin_id'] = 'blazy_filter';
    $settings['_grid'] = $settings['column'] || $settings['grid'];

    // Allows lightboxes to provide its own optionsets.
    if ($switch) {
      $settings[$switch] = empty($settings[$switch]) ? $switch : $settings[$switch];
    }

    // Provides alter like formatters to modify at one go, even clumsy here.
    $build = ['settings' => $settings];
    $this->blazyManager->getModuleHandler()->alter('blazy_settings', $build, $this->settings);
    $settings = array_merge($settings, $build['settings']);

    $elements = [];
    foreach ($allowed_tags as $allowed_tag) {
      $nodes = $dom->getElementsByTagName($allowed_tag);
      if ($nodes->length > 0) {
        $item_settings = $settings;
        $item_settings['count'] = $nodes->length;
        foreach ($nodes as $delta => $node) {
          if ($node->hasAttribute('data-unblazy')) {
            continue;
          }

          // Build Blazy elements with lazyloaded image, or iframe.
          $item_settings['delta'] = $delta;
          $this->buildSettings($item_settings, $node);
          $build = [
            'item' => $this->buildImageItem($item_settings, $node),
            'settings' => $item_settings,
          ];

          // Sanitazion was done by Caption filter when arriving here, as
          // otherwise we cannot see this figure, yet provide fallback.
          if ($node->parentNode->tagName === 'figure') {
            $caption = $node->parentNode->getElementsByTagName('figcaption');
            if ($caption->length > 0 && $caption->item(0) && $text = $caption->item(0)->nodeValue) {
              $build['captions']['alt'] = ['#markup' => Xss::filter($text, BlazyDefault::TAGS)];
            }
          }

          $output = $this->blazyManager->getBlazy($build);
          if ($settings['_grid']) {
            $elements[] = $output;
          }
          else {
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
            $node->parentNode->removeChild($node);
          }
        }
      }
    }

    $all = ['blazy' => TRUE, 'filter' => TRUE, 'ratio' => TRUE];
    $all['media_switch'] = $settings['media_switch'];
    if ($settings['_grid']) {
      $all['grid'] = $settings['grid'];
      $all['column'] = $settings['column'];
      $all[$switch] = $settings[$switch];
      $this->buildGrid($dom, $settings, $elements);
    }

    // Attach Blazy component libraries.
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
        <p><strong>Blazy</strong>: Image or iframe is lazyloaded. To disable, add attribute <code>data-unblazy</code>:</p>
        <ul>
            <li><code>&lt;img data-unblazy /&gt;</code></li>
            <li><code>&lt;iframe data-unblazy /&gt;</code></li>
        </ul>
        <p>To build a grid of images/ videos, add attribute <code>data-grid</code> or <code>data-column</code> (only to the first item):
        <ul>
            <li><code>&lt;img data-grid="1 3 4" /&gt;</code></li>
            <li><code>&lt;iframe data-column="1 3 4" /&gt;</code></li>
        </ul>
        The numbers represent the amount of grids/ columns for small, medium and large devices respectively, space delimited. Be aware! All media items will be grouped regardless of their placements, unless those given a <code>data-unblazy</code>. Also <b>required</b> if using <b>Image to lightbox</b> (Colorbox, Photobox, PhotoSwipe) to build the gallery correctly.</p>');
    }
    else {
      return $this->t('To disable lazyload, add attribute <code>data-unblazy</code> to <code>&lt;img&gt;</code> or <code>&lt;iframe&gt;</code> elements. Examples: <code>&lt;img data-unblazy</code> or <code>&lt;iframe data-unblazy</code>.');
    }
  }

  /**
   * Build the grid.
   *
   * @param \DOMDocument $dom
   *   The HTML DOM object being modified.
   * @param array $settings
   *   The settings array.
   * @param array $elements
   *   The renderable array of blazy item.
   */
  private function buildGrid(\DOMDocument &$dom, array $settings, array $elements = []) {
    $xpath = new \DOMXPath($dom);
    $query = $settings['style'] = $settings['column'] ? 'column' : 'grid';
    $grid = FALSE;

    // This is weird, variables not working for xpath?
    $node = $query == 'column' ? $xpath->query('//*[@data-column]') : $xpath->query('//*[@data-grid]');
    if ($node->length > 0 && $node->item(0) && $node->item(0)->hasAttribute('data-' . $query)) {
      $grid = $node->item(0)->getAttribute('data-' . $query);
    }

    if ($grid && $elements) {
      $grids = array_map('trim', explode(' ', $grid));

      foreach (['small', 'medium', 'large'] as $key => $item) {
        if (isset($grids[$key])) {
          $settings['grid_' . $item] = $grids[$key];
          $settings['grid'] = $grids[$key];
        }
      }

      $build = [
        'items' => $elements,
        'settings' => $settings,
      ];

      $output = $this->blazyManager->build($build);
      $altered_html = $this->blazyManager->getRenderer()->render($output);
      $dom->loadHTML($altered_html);
      $dom->saveHTML();
    }
  }

  /**
   * Returns the faked image item for the image, uploaded or hard-coded.
   *
   * @param array $settings
   *   The settings array being modified.
   * @param object $node
   *   The HTML DOM object.
   *
   * @return object
   *   The faked image item.
   */
  private function buildImageItem(array &$settings, &$node) {
    $item = new \stdClass();
    $item->uri = $settings['uri'];
    $item->entity = NULL;
    $uuid = $node->hasAttribute('data-entity-uuid') ? $node->getAttribute('data-entity-uuid') : '';

    if ($uuid && $node->hasAttribute('src')) {
      $file = $this->entityRepository->loadEntityByUuid('file', $uuid);
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

      $settings['media_attributes']['class'] = array_unique($settings['media_attributes']['class']);
    }

    return $item;
  }

  /**
   * Returns the settings for the current $node.
   *
   * @param array $settings
   *   The settings being modified.
   * @param object $node
   *   The HTML DOM object.
   */
  private function buildSettings(array &$settings, $node) {
    $src = $node->getAttribute('src');
    $width = $node->getAttribute('width');
    $height = $node->getAttribute('height');

    if (!$width && $node->tagName == 'img') {
      if ($src && $data = @getimagesize(DRUPAL_ROOT . $src)) {
        list($width, $height) = $data;
      }
    }

    $settings['ratio'] = !$width ? '' : 'fluid';
    $settings['image_url'] = $src;
    $settings['media_switch'] = $this->settings['media_switch'];

    // @todo file_build_uri() makes public://sites/default/files/media/Screen...
    $uri = strpos($src, 'http') === FALSE ? $src : $src;
    if ($node->tagName == 'iframe') {
      $settings['input_url'] = $src;
      $resource = $this->blazyOembed->build($settings);

      if ($resource) {
        // @todo figure out to get local uri, if any, anyway.
        $uri = $settings['image_url'] = $resource->getThumbnailUrl()->getUri();
        $width = !$width ? $resource->getWidth() : $width;
        $height = !$height ? $resource->getHeight() : $height;
      }

      $settings['ratio'] = !$width ? '16:9' : 'fluid';
    }

    $settings['blazy'] = TRUE;
    $settings['lazy'] = 'blazy';
    $settings['uri'] = $uri;
    $settings['width'] = $width;
    $settings['height'] = $height;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $lightboxes = $this->blazyManager->getLightboxes();

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
      '#description' => $this->t('<ul><li><b>Image to iframe</b> will hide iframe behind image till toggled.</li><li><b>Image to lightbox</b> (Colorbox, Photobox, PhotoSwipe) <b>requires</b> a grid to build the gallery correctly. Add <code>data-column="1 3 4"</code> or <code>data-grid="1 3 4"</code> to the first image/ iframe only.</li></ul>'),
    ];

    if (!empty($lightboxes)) {
      foreach ($lightboxes as $lightbox) {
        $name = Unicode::ucwords(str_replace('_', ' ', $lightbox));
        $form['media_switch']['#options'][$lightbox] = $this->t('Image to @lightbox', ['@lightbox' => $name]);
      }
    }

    return $form;
  }

}
