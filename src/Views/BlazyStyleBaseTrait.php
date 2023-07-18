<?php

namespace Drupal\blazy\Views;

use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Markup;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Utility\Sanitize;

/**
 * A Trait common for optional views style plugins.
 *
 * @todo remove it into BlazyStyleBase after sub-modules extending it.
 */
trait BlazyStyleBaseTrait {

  /**
   * The first Blazy formatter found to get data from for lightbox gallery, etc.
   *
   * @var array
   */
  protected $firstImage;

  /**
   * The dynamic html settings.
   *
   * @var array
   */
  protected $htmlSettings = [];

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * {@inheritdoc}
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldString($row, $name, $index, $clean = TRUE): array {
    $values = [];

    // Content title/List/Text, either as link or plain text.
    if ($value = $this->getFieldValue($index, $name)) {
      $value = is_array($value) ? array_filter($value) : $value;

      // Entity reference label where the above $value can be term ID.
      if ($markup = $this->getField($index, $name)) {
        $value = is_object($markup) ? trim(strip_tags($markup->__toString()) ?: '') : $value;
      }

      if (is_string($value)) {
        // Only respects tags with default CSV, just too much to worry about.
        if (strpos($value, ',') !== FALSE) {
          $tags = array_map('trim', explode(',', $value));
          $rendered_tags = [];
          foreach ($tags as $tag) {
            $tag = trim($tag ?: '');
            $rendered_tags[] = $clean ? Html::cleanCssIdentifier(mb_strtolower($tag)) : $tag;
          }
          // Meant to have space delimited taxonomy values.
          $clean = FALSE;
          $values[$index] = implode(' ', $rendered_tags);
        }
        else {
          $values[$index] = $value;
        }
      }
      else {
        // Normally link field values.
        if (is_array($value)) {
          if ($val = $value[0]['value'] ?? '') {
            $values[$index] = $val;
          }
        }
      }
    }

    return Sanitize::attribute($values, TRUE, $clean);
  }

  /**
   * Provides commons settings for the style plugins.
   */
  protected function buildSettings() {
    $view      = $this->view;
    $count     = count($view->result);
    $settings  = $this->options;
    $view_name = $view->storage->id();
    $view_mode = $view->current_display;
    $plugin_id = $this->getPluginId();
    $display   = $view->style_plugin->displayHandler->getPluginId();
    $instance  = str_replace('_', '-', "{$view_name}-{$display}-{$view_mode}");
    $id        = empty($settings['id']) ? '' : $settings['id'];
    $id        = Blazy::getHtmlId("{$plugin_id}-views-{$instance}", $id);
    $settings += BlazyDefault::lazySettings();

    $this->blazyManager->preSettings($settings);
    $this->prepareSettings($settings);
    $blazies = $settings['blazies'];

    // Prepare needed settings to work with.
    // @todo convert some to blazies, and remove these after sub-modules.
    $settings['id']           = $id;
    $settings['count']        = $count;
    $settings['instance_id']  = $instance;
    $settings['multiple']     = TRUE;
    $settings['plugin_id']    = $settings['view_plugin_id'] = $plugin_id;
    $settings['view_name']    = $view_name;
    $settings['view_display'] = $display;

    $view_info = [
      'display'     => $display,
      'instance_id' => $instance,
      'name'        => $view_name,
      'plugin_id'   => $plugin_id,
      'view_mode'   => $view_mode,
      'count'       => $count,
      'embedded'    => FALSE,
    ];

    $blazies->set('cache.metadata.keys', [$id, $view_mode, $count], TRUE)
      ->set('cache.metadata.tags', $view->getCacheTags() ?: [], TRUE)
      ->set('count', $count)
      ->set('total', $count)
      ->set('css.id', $id)
      ->set('is.multiple', TRUE)
      ->set('is.view', TRUE)
      ->set('use.ajax', $view->ajaxEnabled())
      ->set('view', $view_info, TRUE);

    if (!empty($this->htmlSettings)) {
      $settings = $this->blazyManager->merge($this->htmlSettings, $settings);
    }

    $this->blazyManager->postSettings($settings);

    $this->blazyManager->moduleHandler()->alter('blazy_settings_views', $settings, $view);
    $this->blazyManager->postSettingsAlter($settings);
    return $settings;
  }

  /**
   * Check Blazy formatter to build lightbox galleries.
   */
  protected function checkBlazy(array &$settings, array $build, array $rows = []) {
    // Extracts Blazy formatter settings if available.
    // @todo re-check and remove, first.data already takes care of this.
    // if (empty($settings['vanilla']) && isset($build['items'][0])) {
    // $this->blazyManager()->isBlazy($settings, $build['items'][0]);
    // }
    $blazies = $settings['blazies'];
    if ($data = $this->getFirstImage($rows[0] ?? NULL)) {
      $blazies->set('first.data', $data);
    }
  }

  /**
   * Returns the first Blazy formatter found, to save image dimensions once.
   *
   * Given 100 images on a page, Blazy will call
   * ImageStyle::transformDimensions() once rather than 100 times and let the
   * 100 images inherit it as long as the image style has CROP in the name.
   */
  protected function getFirstImage($row): array {
    if (!isset($this->firstImage)) {
      $view = $this->view;
      // Fixed for Undefined property: Drupal\views\ViewExecutable::$row_index
      // by Drupal\views\Plugin\views\field\EntityField->prepareItemsByDelta.
      if (!isset($view->row_index)) {
        $view->row_index = 0;
      }

      $rendered = [];
      if ($row && $view->rowPlugin->render($row)) {
        if ($fields = $view->field ?? []) {
          foreach ($fields as $field) {
            $options = $field->options ?? [];
            $id = $options['plugin_id'] ?? '';
            $type = $options['type'] ?? $id;
            $switch = isset($options['media_switch'])
              || isset($options['settings']['media_switch']);

            if (!$type) {
              continue;
            }

            if (!empty($options['field'])
              && $switch
              && strpos($type, 'blazy') !== FALSE) {
              $name = $options['field'];
            }
          }

          if (isset($name)) {
            // Blazy Views field plugins: Blazy File and Media.
            if (strpos($name, 'blazy_') !== FALSE
            && $field = ($view->field[$name] ?? NULL)) {
              $result['rendered'] = $field->render($row);
            }
            else {
              // Blazy, Splide, Slick, etc. field formatters.
              $result = $this->getFieldRenderable($row, 0, $name);
            }

            if ($result
              && is_array($result)
              && isset($result['rendered'])
              && !($result['rendered'] instanceof Markup)) {
              // D10/9.5.10 moves it into indices.
              $rendered = $result['rendered'][0]['#build']
                ?? $result['rendered']['#build'] ?? [];
            }
          }
        }
      }

      $this->firstImage = $rendered;
    }
    return $this->firstImage;
  }

  /**
   * Returns the renderable array of field containing rendered and raw data.
   */
  protected function getFieldRenderable($row, $index, $name, $multiple = FALSE): array {
    // Be sure to not check "Use field template" under "Style settings" to have
    // renderable array to work with, otherwise flattened string!
    /** @var \Drupal\views\Plugin\views\field\EntityField $field */
    /* @phpstan-ignore-next-line */
    if ($name && $field = ($this->view->field[$name] ?? NULL)) {
      if (method_exists($field, 'getItems')) {
        $result = $field->getItems($row);
        if ($result && is_array($result)) {
          // @todo recheck the last: a plain array, rendered/raw, markup, etc.
          return $multiple ? $result : ($result[0] ?? []);
        }
      }
    }
    return [];
  }

  /**
   * Returns the rendered field, either string or array.
   */
  protected function getFieldRendered($index, $name, $restricted = FALSE): array {
    if ($name && $output = $this->getField($index, $name)) {
      return is_array($output) ? $output : [
        '#markup' => ($restricted ? Xss::filterAdmin($output) : $output),
      ];
    }
    return [];
  }

  /**
   * Returns the thumbnail if so configured.
   *
   * Be sure to reset settings before calling this method:
   * $this->reset($sets);
   */
  protected function getThumbnail(array &$sets, $row, $index): array {
    $name = $sets['thumbnail'] ?? NULL;

    if (empty($name)) {
      return [];
    }

    // Provides a potential unique thumbnail different from the main image.
    $blazies = $sets['blazies'];
    $blazies->set('is.reset', TRUE);
    $tn = $this->getFieldRenderable($row, 0, $name);
    $rendered = $tn['rendered'] ?? [];

    // Core image formatter:
    $tn_style = $rendered['#image_style'] ?? NULL;
    $item = $rendered['#item'] ?? NULL;

    // Blazy formatter, might be group_rows.
    $build = $rendered['#build'] ?? [];

    // Even if ignorantly multiple, thumbnails must be one only.
    if (!$tn_style && $build) {
      $subsets = Blazy::toHashtag($build);
      $tn_style = $subsets['thumbnail_style']
        ?? $subsets['image_style']
        ?? NULL;
    }

    if (!$item) {
      // Might be group_rows.
      $item = $build['#item'] ?? $build[0]['#item'] ?? $rendered['raw'] ?? NULL;
    }

    if ($tn_style && is_object($item)) {
      $uri = Blazy::uri($item);
      $sets['thumbnail_style'] = $tn_style;

      $tn_uri = $uri ? $this->blazyManager
        ->load($tn_style, 'image_style')
        ->buildUri($uri) : NULL;

      // This allows a thumbnail different from the main stage, such as logos
      // thumbnails, and company buildings for the main stage.
      if ($tn_uri) {
        $sets['thumbnail_uri'] = $tn_uri;
        $blazies->set('thumbnail.uri', $tn_uri);
      }
    }

    // If multiple, only one thumbnail can exist.
    if (isset($build[1])) {
      $tn = $this->blazyManager->getThumbnail($sets, $item);
    }
    else {
      /* @phpstan-ignore-next-line */
      $tn = $this->getFieldRendered($index, $name);
    }
    return is_array($tn) ? $tn : [$tn];
  }

  /**
   * Prepares commons settings for the style plugins.
   */
  protected function prepareSettings(array &$settings = []) {
    // Do nothing to let extenders modify.
  }

  /**
   * Sets dynamic html settings.
   */
  protected function setHtmlSettings(array $settings = []) {
    $this->htmlSettings = $settings;
    return $this;
  }

  /**
   * Renew settings per item.
   */
  protected function reset(array &$settings, $key = 'blazies') {
    return Blazy::reset($settings, $key);
  }

}
