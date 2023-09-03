<?php

namespace Drupal\blazy\Views;

use Drupal\blazy\Blazy;
use Drupal\blazy\internals\Internals;
use Drupal\blazy\Theme\BlazyViews;
use Drupal\blazy\Utility\Sanitize;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\style\StylePluginBase;
// @todo enable use Drupal\blazy\Field\BlazyElementTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base for blazy views integration to support plain old images, or none.
 */
abstract class BlazyStyleBase extends StylePluginBase implements BlazyStyleBaseInterface {

  // @todo enable if similar to field formatters:
  // use BlazyElementTrait;
  /**
   * The main module namespace.
   *
   * @var string
   * @see https://www.php.net/manual/en/reserved.keywords.php
   */
  protected static $namespace = 'blazy';

  /**
   * The item property to store image or media: content, slide, box, etc.
   *
   * Prioritize sub-modules in case mismatched versions.
   *
   * @var string
   */
  protected static $itemId = 'slide';

  /**
   * The item prefix for captions, e.g.: blazy__caption, slide__caption, etc.
   *
   * @var string
   */
  protected static $itemPrefix = 'slide';

  /**
   * The caption property to store captions.
   *
   * @var string
   */
  protected static $captionId = 'caption';

  /**
   * Whether using the SVG.
   *
   * @var bool
   */
  protected static $useSvg = FALSE;

  /**
   * The blazy formatter service manager.
   *
   * @var \Drupal\blazy\BlazyFormatterInterface
   */
  protected $formatter;

  /**
   * The blazy formatter service manager, dups but no dups for sub-modules.
   *
   * @var \Drupal\blazy\BlazyFormatterInterface
   */
  protected $manager;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   *
   * @todo remove at/by 3.x, no longer in use.
   */
  protected $blazyManager;

  /**
   * The blazy media service.
   *
   * @var \Drupal\blazy\Media\BlazyMediaInterface
   */
  protected $mediaManager;

  /**
   * The svg manager service.
   *
   * @var \Drupal\blazy\Media\Svg\SvgInterface
   */
  protected $svgManager;

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
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // For consistent call against ecosystem shared methods, Blazy has straight
    // inheritance, sub-modules deviate:
    $instance->manager = $instance->formatter = $container->get('blazy.formatter');
    $instance->mediaManager = $container->get('blazy.media');
    $instance->svgManager = $container->get('blazy.svg');

    // @todo remove for consistent call against sub-modules shared methods:
    $instance->blazyManager = $instance->manager;

    return $instance;
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
    $view    = $this->view;
    $options = $this->options;

    $data = [
      'embedded'  => FALSE,
      'is_view'   => TRUE,
      'plugin_id' => $this->getPluginId(),
    ];

    // Prepare needed settings to work with.
    $settings = BlazyViews::settings($view, $options, $data);
    $blazies  = $settings['blazies'];
    $is_grid  = !empty($settings['style']) && !empty($settings['grid']);

    $settings['caption'] = empty($settings['caption'])
      ? [] : array_filter($settings['caption']);

    // Since 2.17, the item array was to replace all sub-modules theme_ITEM() by
    // theme_blazy() for easy improvements at 3.x. Not implemented at 2.x, yet.
    $blazies->set('namespace', static::$namespace)
      ->set('is.grid', $is_grid && $blazies->is('multiple'))
      ->set('item.id', static::$itemId)
      ->set('item.prefix', static::$itemPrefix)
      ->set('item.caption', static::$captionId);

    // Be sure to run after item setup.
    if (!method_exists($this->manager, 'verifySafely')) {
      return $settings;
    }

    $this->manager->verifySafely($settings);
    $this->manager->preSettings($settings);

    $this->prepareSettings($settings);

    $this->manager->postSettings($settings);

    $this->postSettings($settings);

    $this->manager->moduleHandler()->alter('blazy_settings_views', $settings, $view);
    $this->manager->postSettingsAlter($settings);

    return $settings;
  }

  /**
   * Check Blazy formatter to build lightbox galleries.
   *
   * Make this view container aware of Blazy formatters, normally to inject
   * relevant lightbox info about which it is not aware of due to such info is
   * not provided at view style level, but field formatter one.
   */
  protected function checkBlazy(array &$settings, array $build, array $rows = []) {
    // Extracts Blazy formatter settings if available.
    // @todo re-check and remove, first.data already takes care of this.
    // The ::isBlazy() is still needed for Views fields, not just this view,
    // but not here, normally at modules' managers.
    // However if any issues, re-enable this check, and refine downstream more.
    // if (empty($settings['vanilla']) && isset($build['items'][0])) {
    // $this->manager()->isBlazy($settings, $build['items'][0]);
    // }
    $blazies = $settings['blazies'];
    if ($data = $this->getFirstImage($rows[0] ?? NULL)) {
      $blazies->set('first.data', $data);

      // @todo recheck $this->manager->preSettings($settings);
      if ($subsets = $this->manager->toHashtag($data)) {
        if ($blazy = $subsets['blazies']) {
          $field = $blazy->get('field', []);
          $field['count'] = $blazy->get('count');
          $blazies->set('view.formatter', $field);
        }
      }
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

            $doable = isset($options['media_switch'])
              || isset($options['settings']['image_style']);

            if (!$type) {
              continue;
            }

            if (!empty($options['field']) && $doable) {
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
              // D10/9.5.10 moves it into indices only if theme_field required
              // with group rows. The chaos of blazy:2.15 with lightboxes.
              $rendered = $result['rendered'][0]['#build']
                ?? $result['rendered']['#build'] ?? $result['rendered'] ?? [];
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
    if (!$name) {
      return [];
    }

    /** @var \Drupal\views\Plugin\views\field\EntityField $field */
    $field = $this->view->field[$name] ?? NULL;
    if ($field && method_exists($field, 'getItems')) {
      $result = $field->getItems($row);
      if ($result && is_array($result)) {
        // @todo recheck the last: a plain array, rendered/raw, markup, etc.
        return $multiple ? $result : ($result[0] ?? []);
      }
    }
    return [];
  }

  /**
   * Returns the rendered field, either string or array.
   */
  protected function getFieldRendered($index, $name, $restricted = FALSE, $row = NULL): array {
    if ($name && $output = $this->getField($index, $name)) {
      // Linked title has weird value: ….
      if ($row && $output == "…") {
        if ($check = $this->getFieldRenderable($row, $index, $name)) {
          $output = $check['rendered'] ?? [];
        }
      }

      return is_array($output) ? $output : [
        '#markup' => ($restricted ? Xss::filterAdmin($output) : $output),
      ];
    }
    return [];
  }

  /**
   * Checks if we can work with this formatter, otherwise no go if flattened.
   */
  protected function getImageArray($row, $index, $field_image): array {
    if ($field_image
      && $image = $this->getFieldRenderable($row, $index, $field_image)) {

      // Just to be sure, replace raw with the found image item.
      if ($item = $this->getImageItem($image)) {
        $image['raw'] = $item;
      }

      // Known image formatters: Blazy, Image, etc. which provides ImageItem.
      // Else dump Video embed thumbnail/video/colorbox as is.
      if ($item || isset($image['rendered'])) {
        return $image;
      }
    }
    return [];
  }

  /**
   * Get the image item to work with out of this formatter.
   *
   * All this mess is because Views may render/flatten images earlier.
   */
  protected function getImageItem($image): ?object {
    $item = NULL;

    if ($rendered = ($image['rendered'] ?? [])) {
      // Image formatter.
      $item = $rendered['#item'] ?? NULL;

      // Blazy formatter, also supports multiple, `group_rows`.
      if ($build = ($rendered['#build'] ?? [])) {
        $item = $this->manager->toHashtag($build, 'item') ?: $item;
        $item = $build[0]['#item'] ?? $item;
      }
    }

    // Don't know other reasonable formatters to work with.
    return $this->isValidImageItem($item) ? $item : NULL;
  }

  /**
   * Returns the modified renderable image_formatter to support lazyload.
   */
  protected function getImageRenderable(array &$settings, $row, $index): array {
    $_image = $settings['image'] ?? NULL;
    if (!$_image) {
      return [];
    }

    $image    = $this->getImageArray($row, $index, $_image);
    $rendered = $image['rendered'] ?? [];
    $item     = $image['raw'] ?? NULL;

    // Supports 'group_rows' option.
    // @todo recheck if any side issues for not having raw key.
    $image['applicable'] = FALSE;
    if (!$rendered) {
      return $image;
    }

    // If the image has #item property, lazyload may work, otherwise skip.
    // This hustle is to lazyload tons of images -- grids, large galleries,
    // gridstack, mason, with multimedia/ lightboxes for free.
    /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
    if ($this->isValidImageItem($item)) {
      $image['raw'] = $item;

      // Supports multiple image styles within a single view such as GridStack,
      // else fallbacks to the defined image style if available.
      if (empty($settings['image_style'])) {
        $settings['image_style'] = $rendered['#image_style']
          ?? $rendered['#style_name'] ?? '';
      }

      // Converts image formatter for blazy to reduce complexity with CSS
      // background option, and other options, and still lazyload it.
      $theme = $rendered['#theme']
        ?? $rendered['#build'][0]['#theme']
        ?? '';

      if ($theme && in_array($theme, ['blazy', 'image_formatter'])) {
        if ($theme == 'blazy') {
          $this->withBlazyFormatter($settings, $rendered, $index);
        }
        elseif ($theme == 'image_formatter') {
          $this->withImageFormatter($settings, $rendered, $index);
        }

        $image['applicable'] = TRUE;
      }
    }

    return $image;
  }

  /**
   * Provides a potential unique thumbnail different from the main image.
   *
   * Be sure to reset settings before calling this method:
   * $this->reset($sets);
   *
   * @todo remove the new param default NULL at/ by 3.x after sub-modules.
   */
  protected function getThumbnail(array &$sets, $row, $index, $field_caption = NULL): array {
    $name    = $sets['thumbnail'] ?? NULL;
    $blazies = $sets['blazies'];

    $blazies->set('is.reset', TRUE);

    // Thumbnail image is optional for tab navigation like.
    [
      'doable' => $doable,
      'item' => $item,
    ] = $this->getWorkableThumbnail($sets, $row, $name, $index);

    // Caption is optional for thumbed navigation only.
    $caption = [];
    if ($field_caption) {
      $caption = $this->getFieldRendered($index, $field_caption, FALSE, $row);
    }

    // Replace empty image item with the rendered output if not doable.
    if (!$doable && $name) {
      $item = $this->getFieldRendered($index, $name, FALSE, $row);
    }

    // If ($id = $blazies->get('thumbnail.id')) {
    // $sets['thumbnail_style'] = $id;
    // }
    // Even if multiple, only one thumbnail can exist.
    return $this->manager->getThumbnail($sets, $item, $caption);
  }

  /**
   * Returns TRUE if a valid image item, else FALSE.
   */
  protected function isValidImageItem($item): bool {
    return is_object($item) && (isset($item->uri) || isset($item->target_id));
  }

  /**
   * Prepares commons settings for the style plugins.
   */
  protected function prepareSettings(array &$settings): void {
    // Do nothing to let extenders modify.
  }

  /**
   * Provide post settings for the style plugins.
   */
  protected function postSettings(array &$settings): void {
    // Do nothing to let extenders modify.
  }

  /**
   * Renew settings per item.
   */
  protected function reset(array &$settings, $key = 'blazies', array $defaults = []) {
    return Internals::reset($settings, $key, $defaults);
  }

  /**
   * Extract image style and url from blazy image formatter.
   */
  protected function withBlazyFormatter(array &$settings, array $rendered, $index): void {
    // Pass Blazy field formatter settings into Views style plugin.
    // This allows richer contents such as multimedia/ lightbox for free.
    // Yet, ensures the Views style plugin wins over Blazy formatter,
    // such as with GridStack which may have its own breakpoints.
    $newbies   = $this->manager->toHashtag($rendered['#build']);
    $formatter = array_filter($newbies);
    $settings  = array_merge($formatter, array_filter($settings));

    // Reserves crucial blazy specific settings.
    Internals::preserve($settings, $formatter);

    // Each blazy delta is always 0 within a view, this makes it gallery.
    $blazies = $settings['blazies'];
    $blazies->merge($formatter['blazies']->storage());
    $blazies->set('delta', $index)
      ->set('is.gallery', !empty($settings['media_switch']));

    $tn  = $blazies->get('thumbnail.uri', 'x');
    $uri = $blazies->get('image.uri');

    // Views Media thumbnail may not have expected thumbnail URI, override.
    if ($uri && strpos($tn, 'media-icons') !== FALSE) {
      $style = NULL;
      if ($tn_style = $settings['thumbnail_style'] ?? NULL) {
        $style = $this->manager->load($tn_style, 'image_style');
        $uri = $style->buildUri($uri);
        $blazies->set('thumbnail.id', $tn_style);
      }

      $url = Blazy::url($uri, $style);

      $blazies->set('thumbnail.uri', $uri)
        ->set('thumbnail.url', $url)
        ->set('thumbnail.item', $rendered['#item']);
    }
  }

  /**
   * Extract image style and url from core image formatter.
   */
  protected function withImageFormatter(array &$settings, array $rendered, $index): void {
    $blazies = $settings['blazies'];

    // Deals with "link to content/image" by formatters.
    $url = $rendered['#url'] ?? '';

    // Checks if an object.
    if ($url instanceof Url) {
      $url = $url->setAbsolute()->toString();
    }

    // Prevent images from having absurd height when being lazyloaded.
    // Allows to disable it by _noratio such as enforced CSS background.
    $noratio = $settings['_noratio'] ?? FALSE;
    $settings['ratio'] = $blazies->is('noratio', $noratio) ? '' : 'fluid';

    if (empty($settings['media_switch']) && $url) {
      $settings['media_switch'] = 'content';
      $blazies->set('switch', 'content');
    }

    $blazies->set('delta', $index)
      ->set('entity.url', $url);
  }

  /**
   * Provides a workable thumbnail if any.
   *
   * Be sure to reset settings before calling this method:
   * $this->reset($sets);
   */
  private function getWorkableThumbnail(array &$sets, $row, $name, $index): array {
    if (!$name) {
      return ['doable' => FALSE, 'item' => NULL];
    }

    // Can only have one thumbnail even if multiple.
    // Supports core image formatter, the most sensible, and Blazy formatter.
    $blazies  = $sets['blazies'];
    $doable   = FALSE;
    $result   = $this->getFieldRenderable($row, 0, $name);
    $rendered = $result['rendered'] ?? [];
    $tn_style = $rendered['#image_style'] ?? $rendered['#style_name'] ?? NULL;
    $item     = $rendered['#item'] ?? NULL;
    $uri      = $rendered['#uri'] ?? NULL;
    $build    = $rendered['#build'] ?? [];

    // Might be group_rows, the first two are blazy, the last image_formatter.
    if (!$item) {
      $item = $build['#item'] ?? $build[0]['#item'] ?? $rendered['raw'] ?? NULL;
    }

    // If no URI, but we have an ImageItem.
    if (!$uri && is_object($item)) {
      $uri = Blazy::uri($item);
    }

    // Core media Thumbnail formatter might deliver just default media icons,
    // while this Views style expects a real image via Image + Thumbnail option.
    if ($uri && strpos($uri, 'media-icons') !== FALSE) {
      // @todo make this call usable for the Main stage option downstream.
      if ($image = $this->getImageRenderable($sets, $row, $index)) {
        $item = $image['raw'];
        $uri = $blazies->get('image.uri');
      }
    }

    // Only if we have an URI.
    if ($uri) {
      $tn_uri = $uri;

      // Also set it as an image.uri for lazy load to work.
      if (!$blazies->get('image.uri')) {
        $blazies->set('image.uri', $uri);
      }

      // This allows a thumbnail different from the main stage, such as logos
      // thumbnails, and company buildings for the main stage.
      if ($tn_uri) {
        $style = NULL;

        if ($tn_style && !Internals::isSvg($tn_uri)) {
          $style = $this->manager->load($tn_style, 'image_style');
          $sets['thumbnail_style'] = $tn_style;
          $tn_uri = $style->buildUri($tn_uri);
        }

        $tn_url = Blazy::url($tn_uri, $style);

        // @todo remove the first here.
        $sets['thumbnail_uri'] = $tn_uri;
        $blazies->set('thumbnail.id', $tn_style)
          ->set('thumbnail.uri', $tn_uri)
          ->set('thumbnail.url', $tn_url)
          ->set('thumbnail.item', $item);

        $doable = TRUE;
      }
      else {
        $doable = $blazies->get('image.uri') != NULL;
      }
    }
    return ['doable' => $doable, 'item' => $item];
  }

}
