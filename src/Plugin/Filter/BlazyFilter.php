<?php

namespace Drupal\blazy\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\blazy\BlazyOEmbed;
use Drupal\blazy\Dejavu\BlazyVideoTrait;
use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatterBaseTrait;
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

  use BlazyFormatterBaseTrait;
  use BlazyVideoTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entity_manager, BlazyOEmbed $blazy_oembed) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityManager = $entity_manager;
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
      $container->get('entity.manager'),
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
    $xpath = new \DOMXPath($dom);
    $settings['grid'] = stristr($text, 'data-grid') !== FALSE;
    $settings['column'] = stristr($text, 'data-column') !== FALSE;
    $settings['media_switch'] = $switch = $this->settings['media_switch'];
    $settings['lightbox'] = ($switch && in_array($switch, $this->blazyManager()->getLightboxes())) ? $switch : FALSE;

    foreach ($allowed_tags as $allowed_tag) {
      $nodes = $dom->getElementsByTagName($allowed_tag);
      if ($nodes->length > 0) {
        foreach ($nodes as $node) {
          if ($node->hasAttribute('data-unblazy')) {
            continue;
          }

          // Build Blazy elements with lazyloaded image, or iframe.
          $settings = array_merge($settings, $this->buildSettings($node));
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

    $all = ['blazy' => TRUE, 'filter' => TRUE, 'ratio' => TRUE];
    $all['media_switch'] = $settings['media_switch'];
    if ($settings['column'] || $settings['grid']) {
      $all['grid'] = $settings['grid'];
      $all['column'] = $settings['column'];
      $this->buildGrid($dom, $xpath, $settings);
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
        <p>Image or iframe is lazyloaded. To disable, add attribute <code>data-unblazy</code>:</p>
        <ul>
            <li><code>&lt;img data-unblazy /&gt;</code></li>
            <li><code>&lt;iframe data-unblazy /&gt;</code></li>
        </ul>
        <p>To build a grid of images/ videos, add attribute <code>data-grid</code> or <code>data-column</code> (only to the first item):
        <ul>
            <li><code>&lt;img data-grid="1 3 4" /&gt;</code></li>
            <li><code>&lt;iframe data-column="1 3 4" /&gt;</code></li>
        </ul>
        where numbers represent the amount of grids/ columns for small, medium and large devices respectively, space delimited. Be aware! All media items will be grouped regardless of their placements. This is also required if using <b>Image to lightbox</b> (Colorbox, Photobox, PhotoSwipe).</p>');
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
   * @param \DOMXpath $xpath
   *   The DOM Xpath object.
   * @param array $settings
   *   The settings array.
   *
   * @todo use BlazyGrid if you can.
   */
  private function buildGrid(\DOMDocument &$dom, \DOMXpath $xpath, array $settings) {
    $query = $settings['column'] ? 'column' : 'grid';
    $nodes = $xpath->evaluate('//*[contains(@class, "grid")]');

    // @todo assign variable. This is weird, variables not working for xpath?
    if ($query == 'column') {
      $grid = $xpath->query('//*[@data-column]')->item(0)->getAttribute('data-column');
    }
    else {
      $grid = $xpath->query('//*[@data-grid]')->item(0)->getAttribute('data-grid');
    }

    $classes = [];
    if (($first = $nodes[0]) && $grid) {
      // Create the parent grid container, and put it before the first.
      $container = $first->parentNode->insertBefore($dom->createElement('div'), $first);
      $grids = array_map('trim', explode(' ', $grid));
      $classes[] = 'blazy blazy--filter blazy--' . $query . ' block-' . $query;

      foreach (['small', 'medium', 'large'] as $key => $item) {
        if (isset($grids[$key])) {
          $classes[] = $item . '-block-' . $query . '-' . $grids[$key];
        }
      }

      // Add the container classes, and merge grid items into container.
      $container->setAttribute('class', implode(' ', $classes));
      $container->setAttribute('data-blazy', '');

      if (!empty($settings['media_switch'])) {
        $switch = str_replace('_', '-', $settings['media_switch']);
        $container->setAttribute('data-' . $switch . '-gallery', TRUE);
      }

      foreach ($nodes as $node) {
        $container->appendChild($node);
      }
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
  private function buildImageItem(&$node, array &$settings = []) {
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

        if ($settings['column'] || $settings['grid']) {
          if ($node->parentNode->tagName === 'figure') {
            $classes = $node->parentNode->getAttribute('class');
            $classes = (strlen($classes) > 0) ? explode(' ', $classes) : [];
            $classes[] = 'grid';
            $node->parentNode->setAttribute('class', implode(' ', array_unique($classes)));
          }
          else {
            $settings['media_attributes']['class'][] = 'grid';
          }
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
    $src = $node->getAttribute('src');
    $width = $node->getAttribute('width');
    $height = $node->getAttribute('height');

    if (!$width && $node->tagName == 'img') {
      if ($src && $data = @getimagesize(DRUPAL_ROOT . $src)) {
        list($width, $height) = $data;
      }
    }

    $settings = [
      'ratio' => !$width ? '' : 'fluid',
      'image_url' => $src,
      'input_url' => $src,
      'media_switch' => $this->settings['media_switch'],
    ];

    $uri = file_build_uri($src);
    if ($node->tagName == 'iframe') {
      $resource = $this->blazyOembed->build($settings);

      if ($resource) {
        // @todo figure out to get local uri, if any, anyway.
        $uri = $settings['image_url'] = $resource->getThumbnailUrl()->getUri();
        $width = !$width ? $resource->getWidth() : $width;
        $height = !$height ? $resource->getHeight() : $height;
      }

      $settings['ratio'] = !$width ? '16:9' : 'fluid';
    }

    return [
      'blazy' => TRUE,
      'lazy' => 'blazy',
      'uri' => $uri,
      'width' => $width,
      'height' => $height,
    ] + $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $lightboxes = \Drupal::service('blazy.admin')->blazyManager()->getLightboxes();

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
      '#description' => $this->t('<ul><li><b>Image to iframe</b> will hide iframe behind image till toggled.</li><li><b>Image to lightbox</b> (Colorbox, Photobox, PhotoSwipe) requires a grid. Add <code>data-column="1 3 4"</code> or <code>data-grid="1 3 4"</code> to the first image/ iframe only.</li></ul>'),
    ];

    if (!empty($lightboxes)) {
      foreach ($lightboxes as $lightbox) {
        $form['media_switch']['#options'][$lightbox] = $this->t('Image to @lightbox', ['@lightbox' => $lightbox]);
      }
    }

    return $form;
  }

}
