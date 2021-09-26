<?php

namespace Drupal\blazy\Plugin\Filter;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\blazy\BlazyUtil;

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
 *     "box_style" = "",
 *     "hybrid_style" = "",
 *     "use_data_uri" = "0",
 *   },
 *   weight = 3
 * )
 */
class BlazyFilter extends BlazyFilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    $allowed_tags = array_values((array) $this->settings['filter_tags']);
    if (empty($text) || empty($allowed_tags)) {
      return $result;
    }

    $dom = Html::load($text);
    $attachments = [];
    $settings = $this->buildSettings($text);
    $valid_nodes = BlazyFilterUtil::validNodes($dom, $allowed_tags, 'data-unblazy');

    if (count($valid_nodes) > 0) {
      $elements = $grid_nodes = [];
      foreach ($valid_nodes as $delta => $node) {
        // Build Blazy elements with lazyloaded image, or iframe.
        $settings['delta'] = $delta;

        $build = ['settings' => $settings];
        $this->buildItemSettings($build, $node);

        // Extracts image item from SRC attribute.
        $this->buildImageItem($build, $node);

        // Extracts image caption if available.
        $this->buildImageCaption($build, $node);

        // Marks invalid, unknown, missing IMG or IFRAME for removal.
        // Be sure to not affect external images, only strip missing local URI.
        $uri = $build['settings']['uri'];
        $missing = !empty($uri) && (BlazyUtil::isValidUri($uri) && !is_file($uri));
        if (empty($uri) || $missing) {
          $node->setAttribute('class', 'blazy-removed');
          continue;
        }

        // Build valid nodes as structured render array.
        $output = $this->blazyManager->getBlazy($build);
        if ($settings['_grid']) {
          $elements[] = $output;
          $grid_nodes[] = $node;
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
          if ($node->parentNode) {
            $node->parentNode->removeChild($node);
          }
        }
      }

      // Prepares attachments.
      $all = BlazyFilterUtil::attach($settings);

      // Builds the grids if so provided via [data-column], or [data-grid].
      if ($settings['_grid'] && !empty($elements[0])) {
        $all['grid'] = $settings['grid'];
        $all['column'] = $settings['column'];

        $settings['_uri'] = isset($elements[0]['#build'], $elements[0]['#build']['settings']['uri']) ? $elements[0]['#build']['settings']['uri'] : '';
        $this->buildGrid($dom, $settings, $elements, $grid_nodes);
      }

      // Adds the attachments.
      $attachments = $this->blazyManager->attach($all);

      // Cleans up invalid, or moved nodes.
      $this->cleanupNodes($dom);
    }

    // Attach Blazy component libraries.
    $result->setProcessedText(Html::serialize($dom))
      ->addAttachments($attachments);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettings($text) {
    $settings = parent::buildSettings($text);

    $settings['grid'] = stristr($text, 'data-grid') !== FALSE;
    $settings['column'] = stristr($text, 'data-column') !== FALSE;
    $settings['id'] = $settings['gallery_id'] = BlazyFilterUtil::getId('blazy-filter');
    $settings['plugin_id'] = 'blazy_filter';
    $settings['_grid'] = $settings['column'] || $settings['grid'];

    // Provides alter like formatters to modify at one go, even clumsy here.
    $build = ['settings' => $settings];
    $this->blazyManager->getModuleHandler()->alter('blazy_settings', $build, $this->settings);
    return array_merge($settings, $build['settings']);
  }

  /**
   * Cleanups invalid nodes or those of which their contents are moved.
   *
   * @param \DOMDocument $dom
   *   The HTML DOM object being modified.
   */
  public function cleanupNodes(\DOMDocument &$dom) {
    $xpath = new \DOMXPath($dom);
    $nodes = $xpath->query("//*[contains(@class, 'blazy-removed')]");
    if ($nodes->length > 0) {
      BlazyFilterUtil::removeNodes($nodes);
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
   * @param array $grid_nodes
   *   The grid nodes.
   */
  public function buildGrid(\DOMDocument &$dom, array &$settings, array $elements = [], array $grid_nodes = []) {
    $xpath = new \DOMXPath($dom);
    $query = $settings['style'] = $settings['column'] ? 'column' : 'grid';
    $grid = FALSE;

    // This is weird, variables not working for xpath?
    $node = $query == 'column' ? $xpath->query('//*[@data-column]') : $xpath->query('//*[@data-grid]');
    if ($node->length > 0 && $node->item(0) && $node->item(0)->hasAttribute('data-' . $query)) {
      $grid = $node->item(0)->getAttribute('data-' . $query);
    }

    if ($grid) {
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

      if ($first = $grid_nodes[0]) {
        // Checks if the IMG is managed by caption filter identified by figure.
        if ($first->parentNode && $first->parentNode->tagName == 'figure') {
          $first = $first->parentNode;
        }

        // Create the parent grid container, and put it before the first.
        // This extra container ensures hook_blazy_build_alter() aint screw up.
        $parent = $first->parentNode ? $first->parentNode : $first;
        $container = $parent->insertBefore($dom->createElement('div'), $first);
        $container->setAttribute('class', 'blazy-wrapper blazy-wrapper--filter');

        $updated_nodes = Html::load($altered_html)->getElementsByTagName('body')
          ->item(0)
          ->childNodes;

        foreach ($updated_nodes as $updated_node) {
          // Import the updated from the new DOMDocument into the original
          // one, importing also the child nodes of the updated node.
          $updated_node = $dom->importNode($updated_node, TRUE);
          $container->appendChild($updated_node);
        }

        // Cleanups old nodes already moved into grids.
        BlazyFilterUtil::removeNodes($grid_nodes);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildImageItem(array &$build, &$node) {
    parent::buildImageItem($build, $node);

    $item = $build['item'];
    $settings = $build['settings'];

    if (!empty($settings['_grid']) || !empty($settings['no_item_container'])) {
      return;
    }

    // Provides hints for extensions.
    if ($settings['delta'] == 0) {
      $config = [
        'media_switch',
        'box_style',
        'hybrid_style',
        'image_style',
        'responsive_image_style',
        'box_caption',
        'gallery_id',
        'is_media_library',
        'lightbox',
        'type',
      ];

      foreach ($config as $key) {
        $data[$key] = isset($settings[$key]) ? $settings[$key] : '';
      }
      $build['media_attributes']['data-b-settings'] = Json::encode($data);
    }
    // Responsive image with aspect ratio requires an extra container to work
    // with Align/ Caption images filters.
    $build['media_attributes']['class'] = [
      'media-wrapper',
      'media-wrapper--blazy',
    ];

    // Copy all attributes of the original node to the item_attributes.
    if ($node->attributes->length) {
      foreach ($node->attributes as $attribute) {
        $value = $attribute->nodeValue;
        $name = $attribute->nodeName;
        if ($name == 'src') {
          continue;
        }

        // Move classes (align-BLAH,etc) to Blazy container, not image so to
        // work with alignments and aspect ratio. Sanitization is performed at
        // BlazyManager::prepareBlazy() to avoid double escapes.
        if ($name == 'class') {
          if (mb_strpos($value, 'b-lazy') === FALSE) {
            $build['media_attributes']['class'][] = $value;
          }
        }
        // Uploaded IMG has target_id in the least, respect hard-coded IMG.
        // @todo decide to remove as this is being too risky.
        elseif (!isset($item->target_id)) {
          $build['item_attributes'][$name] = $value;
        }
      }

      $build['media_attributes']['class'] = array_unique($build['media_attributes']['class']);
    }

    if (!empty($settings['type'])) {
      $build['media_attributes']['class'][] = 'media-wrapper--' . $settings['type'];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function cleanupImageCaption(array &$build, &$node, &$item) {
    // Mark the FIGCAPTION for deletion because the caption will be
    // rendered in the Blazy way.
    $item->setAttribute('class', 'blazy-removed');

    // Marks figures for removal as its contents are moved into grids.
    if ($build['settings']['_grid']) {
      $node->parentNode->setAttribute('class', 'blazy-removed');
    }
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
        <p>To build a grid of images/ videos, add attribute <code>data-grid</code> or <code>data-column</code> (only to the first item):</p>
        <ul>
            <li><code>&lt;img data-grid="1 3 4" /&gt;</code></li>
            <li><code>&lt;iframe data-column="1 3 4" /&gt;</code></li>
        </ul>
        <p>The numbers represent the amount of grids/ columns for small, medium and large devices respectively, space delimited. Be aware! All media items will be grouped regardless of their placements, unless those given a <code>data-unblazy</code>. Manually add width and height for SVG, and other images without image styles.</p>');
    }
    else {
      return $this->t('To disable lazyload, add attribute <code>data-unblazy</code> to <code>&lt;img&gt;</code> or <code>&lt;iframe&gt;</code> elements. Examples: <code>&lt;img data-unblazy</code> or <code>&lt;iframe data-unblazy</code>. Manually add width and height for SVG, and other images without image styles.');
    }
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
      '#description' => $this->t('Recommended placement after Align / Caption images. To disable Blazy per individual item, add attribute <code>data-unblazy</code>.'),
      '#prefix' => '<p>' . $this->t('<b>Warning!</b> Blazy Filter is useless and broken when you enable <b>Media embed</b> or <b>Display embedded entities</b>. You can disable Blazy Filter in favor of Blazy formatter embedded inside <b>Media embed</b> or <b>Display embedded entities</b> instead. However it might be useful for User Generated Contents (UGC) where Entity/Media Embed are likely more for privileged users, authors, editors, admins, alike. Or when Entity/Media Embed is disabled. Or when editors prefer pasting embed codes from video providers rather than creating media entities.') . '</p>',
    ];

    $this->mediaSwitchForm($form);

    $form['use_data_uri'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Trust data URI'),
      '#default_value' => isset($this->settings['use_data_uri']) ? $this->settings['use_data_uri'] : FALSE,
      '#description' => $this->t('Enable to support the use of data URI. Leave it unchecked if unsure, or never use data URI.'),
    ];

    return $form;
  }

}
