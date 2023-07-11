<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Field\BlazyDependenciesTrait;
use Drupal\blazy\Utility\Sanitize;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for blazy/slick image, and file ER formatters.
 *
 * Defines one base class to extend for both image and file ER formatters as
 * otherwise different base classes: ImageFormatterBase or FileFormatterBase.
 *
 * @see Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatter.
 * @see Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFileFormatter.
 * @see Drupal\slick\Plugin\Field\FieldFormatter\SlickImageFormatter.
 * @see Drupal\slick\Plugin\Field\FieldFormatter\SlickFileFormatter.
 *
 * @todo remove no longer in use: ImageFactory at blazy:3.x.
 */
abstract class BlazyFileFormatterBase extends FileFormatterBase {

  use BlazyFormatterTrait {
    getScopedFormElements as traitGetScopedFormElements;
  }
  use BlazyDependenciesTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
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
   * Build individual item if so configured such as for file ER goodness.
   */
  protected function buildElement(array &$element, $entity) {
    // Do nothing.
  }

  /**
   * Returns available build options.
   */
  protected function buildOptions(array $settings): array {
    return [];
  }

  /**
   * Returns the Blazy elements, also for sub-modules to re-use.
   *
   * @todo replace namespace and item.id with properties post blazy:2.17.
   * @todo remove parameter $options for self::buildOptions().
   */
  protected function getElements(array $build, $files, $options = NULL): \Generator {
    $settings   = Blazy::toHashtag($build);
    $blazies    = $settings['blazies'];
    $options    = $options ?: $this->buildOptions($settings);
    $namespace  = $blazies->get('namespace');
    $item_id    = $blazies->get('item.id');
    $caption_id = $options ?: 'captions';
    $use_media  = FALSE;
    $item_id    = NULL;

    // Prepare for betterment with poorly-informed thumbnails.
    if (is_array($options)) {
      $caption_id = $options['caption_id'] ?? $caption_id;
      $use_media  = $options['use_media'] ?? FALSE;
    }

    foreach ($files as $delta => $file) {
      /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      $item  = $file->_referringItem;
      $sets  = $settings;
      $blazy = $sets['blazies']->reset($sets);
      $uri   = $sets['uri'] = $file->getFileUri();

      // @todo update tests and move it out of here.
      $blazy->set('delta', $delta)
        ->set('media.type', 'image')
        ->set('image.uri', $uri);

      $data = ['item' => $item, 'settings' => $sets];

      // Build individual element, no real use here since VEF deprecated.
      $this->buildElement($data, $file);

      // Build captions if so configured.
      $captions = $this->getCaptions($data);

      // Split for different formatters with very minimal difference.
      if ($namespace == 'blazy') {
        if ($captions) {
          $data[$caption_id] = $captions;
        }

        // @todo move it up after sub-modules.
        $element = $this->formatter->getBlazy($data);
      }
      else {
        $element = $data;

        // @todo remove check after sub-modules.
        if ($use_media) {
          // @todo move it up after sub-modules.
          $blazy = $this->formatter->getBlazy($data);
          $element[$item_id] = $blazy;

          // This is the only reason for the change. Thumbnails are
          // poorly-informed like image without styles, etc.
          // Update with blazy processed settings such as unstyled extensions.
          $item_build = $blazy['#build'] ?? [];
          if ($blazysets = Blazy::toHashtag($item_build)) {
            $element['settings']['blazies']->merge($blazysets['blazies']->storage());
          }
        }

        if ($captions) {
          $element[$caption_id] = $captions;
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
    $settings = Blazy::toHashtag($data);
    $captions = $settings['caption'] ?? [];
    $output   = [];

    if ($captions && $item = Blazy::toHashtag($data, 'item')) {
      foreach ($captions as $caption) {
        if ($content = ($item->{$caption} ?? NULL)) {
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
   * Overrides parent::needsEntityLoad().
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
