<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Field\BlazyDependenciesTrait;
use Drupal\blazy\Utility\Sanitize;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for blazy/slick image, and file ER formatters.
 *
 * Defines one base class to extend for both image and file ER formatters as
 * otherwise different base classes: ImageFormatterBase or FileFormatterBase.
 * All blazy sub-modules image/file related formatters extend this class.
 *
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatter.
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFileFormatter.
 *
 * @todo remove no longer in use: ImageFactory at blazy:3.x.
 */
abstract class BlazyFileFormatterBase extends FileFormatterBase {

  use BlazyFormatterTrait {
    getScopedFormElements as traitGetScopedFormElements;
  }
  use BlazyDependenciesTrait;

  /**
   * The main module namespace.
   *
   * @var string
   * @see https://www.php.net/manual/en/reserved.keywords.php
   */
  protected static $namespace = 'blazy';

  /**
   * The item id: content, slide, box, etc.
   *
   * Prioritize sub-modules in case mismatched versions.
   *
   * @var string
   */
  protected static $itemId = 'slide';

  /**
   * {@inheritdoc}
   */
  protected static $itemPrefix = 'slide';

  /**
   * The caption id.
   *
   * @var string
   */
  protected static $captionId = 'caption';

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
    return self::injectServices($instance, $container, 'image');
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return BlazyDefault::imageSettings() + BlazyDefault::gridSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element    = [];
    $definition = $this->getScopedFormElements();

    $definition['_views'] = isset($form['field_api_classes']);
    $this->admin()->buildSettingsForm($element, $definition);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $entities = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($entities)) {
      return [];
    }

    return $this->commonViewElements($items, $langcode, $entities);
  }

  /**
   * Build individual item if so configured such as for file ER goodness.
   */
  protected function buildElement(array &$element, $entity) {
    // Do nothing.
  }

  /**
   * Returns the Blazy elements, also for sub-modules to re-use.
   *
   * @todo remove parameter $options for properties after sub-modules.
   */
  protected function getElements(array $build, $files, $options = NULL): \Generator {
    $settings = $this->formatter->toHashtag($build);

    foreach ($files as $delta => $file) {
      /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      $item  = $file->_referringItem;
      $sets  = $settings;
      $blazy = $sets['blazies']->reset($sets);
      $uri   = $file->getFileUri();

      // @todo update tests and move it out of here.
      $blazy->set('delta', $delta)
        ->set('media.type', 'image')
        ->set('image.uri', $uri);

      // Hashtags to avoid render errors with some potential leaks.
      $data = [
        '#delta'    => $delta,
        '#entity'   => $file,
        '#item'     => $item,
        '#settings' => $sets,
      ];

      // Build individual element, no real use here since VEF deprecated.
      $this->buildElement($data, $file);

      // Build captions if so configured.
      $captions = $this->getCaptions($data);

      // @todo merge all these into theme_blazy() at 3.x after sub-modules.
      // We all have similar IMAGE + CAPTION constructs. The only difference is
      // sub-modules separate blazy image from captions while Blazy merges them.
      // Plus thumbnails, already managed by themselves, not blazy's business.
      // Mergers allow improvements as seen with thumbnail below at one go.
      // Split for different formatters with very minimal difference.
      // @todo implement when merged at 3.x, not before, of course:
      // $data['#media_attributes']['class'][] =
      // static::$itemPrefix . '__media';
      if (static::$namespace == 'blazy') {
        $data[static::$captionId] = $captions;
        $element = $this->formatter->getBlazy($data);
      }
      else {
        $blazy = $this->formatter->getBlazy($data);
        $element = $data;

        $element[static::$itemId] = $blazy;
        $element[static::$captionId] = $captions;

        // This is the only reason for the change. Thumbnails are
        // poorly-informed like image without styles, SVG, etc.
        // Update with blazy processed settings such as unstyled extensions.
        $item_build = $blazy['#build'] ?? [];
        if ($blazysets = $this->formatter->toHashtag($item_build)) {
          $element['#settings']['blazies']->merge($blazysets['blazies']->storage());
        }
      }

      // Image with grid, responsive image, lazyLoad, and lightbox supports.
      yield $element;
    }
  }

  /**
   * Builds the captions.
   */
  protected function getCaptions(array $data): array {
    $settings = $this->formatter->toHashtag($data);
    $item     = $this->formatter->toHashtag($data, 'item');
    $captions = $settings['caption'] ?? [];
    $output   = [];

    if ($captions && $item) {
      foreach ($captions as $caption) {
        if ($content = ($item->{$caption} ?? NULL)) {
          if ($caption == 'alt') {
            $content = '<p>' . $content . '</p>';
          }
          $output[$caption] = [
            '#markup' => Sanitize::caption($content),
          ];
        }
      }
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    $multiple = $this->isMultiple();

    return [
      'background'        => TRUE,
      'captions'          => 'default',
      'grid_form'         => $multiple,
      'image_style_form'  => TRUE,
      'media_switch_form' => TRUE,
      'style'             => $multiple,
      'thumbnail_style'   => TRUE,
      'no_image_style'    => FALSE,
      'responsive_image'  => TRUE,
      'multiple'          => $multiple,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * One step back to have both image and file ER plugins extend this, because
   * EntityReferenceItem::isDisplayed() doesn't exist, except for ImageItem
   * which is always TRUE anyway for type image and file ER.
   */
  protected function needsEntityLoad(EntityReferenceItem $item) {
    return !$item->hasNewEntity();
  }

  /**
   * {@inheritdoc}
   *
   * A clone of Drupal\image\Plugin\Field\FieldFormatter\ImageFormatterBase so
   * to have one base class to extend for both image and file ER formatters.
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode) {
    // Add the default image if the type is image.
    if ($items->isEmpty() && $this->fieldDefinition->getType() === 'image') {
      $default_image = $this->getFieldSetting('default_image');
      // If we are dealing with a configurable field, look in both
      // instance-level and field-level settings.
      if (empty($default_image['uuid']) && $this->fieldDefinition instanceof FieldConfigInterface) {
        $default_image = $this->fieldDefinition->getFieldStorageDefinition()->getSetting('default_image');
      }
      if (!empty($default_image['uuid']) && $file = $this->formatter->loadByUuid($default_image['uuid'], 'file')) {
        // Clone the FieldItemList into a runtime-only object for the formatter,
        // so that the fallback image can be rendered without affecting the
        // field values in the entity being rendered.
        $items = clone $items;
        $items->setValue([
          'target_id' => $file->id(),
          'alt' => $default_image['alt'],
          'title' => $default_image['title'],
          'width' => $default_image['width'],
          'height' => $default_image['height'],
          'entity' => $file,
          '_loaded' => TRUE,
          '_is_default' => TRUE,
        ]);
        $file->_referringItem = $items[0];
      }
    }

    return parent::getEntitiesToView($items, $langcode);
  }

}
