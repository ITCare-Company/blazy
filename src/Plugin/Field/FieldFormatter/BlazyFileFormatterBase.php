<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\field\FieldConfigInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Field\BlazyDependenciesTrait;
use Drupal\blazy\Field\BlazyField;
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
   * The field type identifier for service injection.
   *
   * @var string
   */
  protected static $fieldType = 'image';

  /**
   * Whether using the SVG.
   *
   * @var bool
   */
  protected static $useSvg = FALSE;

  /**
   * The svg manager service.
   *
   * @var \Drupal\blazy\Media\Svg\SvgInterface
   */
  protected $svgManager;

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
    $instance->svgManager = $container->get('blazy.svg');
    return static::injectServices($instance, $container, static::$fieldType);
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
    $this->viewSvg($element, $entity);
  }

  /**
   * Provides inline SVG if so-configured.
   *
   * @todo move it into BlazyFileSvgFormatterBase after sub-modules.
   */
  protected function viewSvg(array &$element, $entity): void {
    $settings = $this->formatter->toHashtag($element);
    $blazies  = $settings['blazies'];

    if (!static::$useSvg) {
      return;
    }

    $inline = $settings['svg_inline'] ?? FALSE;
    $bg     = $settings['background'] ?? FALSE;
    $exist  = $blazies->is('svg_sanitizer');
    $valid  = $inline && $exist && !$bg;

    if ($valid && $uri = $blazies->get('image.uri')) {
      $options = BlazyDefault::toSvgOptions($settings);
      if ($output = $this->svgManager->view($uri, $options)) {
        $element['content'][] = ['#markup' => Markup::create($output)];
      }
    }
  }

  /**
   * Returns the Blazy elements, also for sub-modules to re-use.
   *
   * @todo remove parameter $options for properties after sub-modules.
   */
  protected function getElements(array $build, $files, $options = NULL): \Generator {
    $settings = $this->formatter->toHashtag($build);
    $blazies  = $settings['blazies'];

    foreach ($files as $delta => $file) {
      /** @var \Drupal\file\Plugin\Field\FieldType\FileItem $item */
      /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $item */
      $item = $file->_referringItem;
      $sets = $settings;
      $uri  = $file->getFileUri();
      $info = [
        'delta'      => $delta,
        'image.uri'  => $uri,
        'media.type' => 'image',
      ];

      // Hashtags to avoid render errors with some potential leaks.
      $data = [
        '#delta'    => $delta,
        '#entity'   => $file,
        '#item'     => $item,
        '#settings' => $this->formatter->toSettings($sets, $info),
      ];

      // Provide parent context for fieldable captions with entity_reference.
      if ($item instanceof EntityReferenceItem) {
        $parent = $item->getParent();
        if (method_exists($parent, 'getEntity')) {
          $data['#parent'] = $parent->getEntity();
        }
      }

      // Build individual element, no real use here since VEF deprecated.
      // Except for SVG since 2.17.
      $this->buildElement($data, $file);

      // Build captions if so configured.
      $captions = $this->getCaptions($data);

      // @todo merge all these into theme_blazy() at 3.x after sub-modules.
      // @todo use $blazies = $this->formatter->preBlazy($data, $item);
      if ($blazies->use('theme_blazy')) {
        $element = $this->themeBlazy($data, $captions, $delta);
      }
      else {
        // @todo remove at 3.x.
        $element = $this->themeItem($data, $captions, $delta);
      }

      // Image with grid, responsive image, lazyLoad, and lightbox supports.
      yield $element;
    }
  }

  /**
   * Provides relevant attributes to feed into theme_blazy().
   */
  protected function toBlazy(array &$data, array &$captions, $delta): void {
    $this->manager->toBlazy($data, $captions, $delta);
  }

  /**
   * Update with blazy processed settings such as unstyled extensions, SVG, etc.
   */
  protected function updateSettings(array &$element, array $blazy): void {
    $item_build = $blazy['#build'] ?? [];

    if ($blazysets = $this->formatter->toHashtag($item_build)) {
      $element['#settings']['blazies']->merge($blazysets['blazies']->storage());
    }
  }

  /**
   * Builds the captions.
   */
  protected function getCaptions(array $data): array {
    $settings = $this->formatter->toHashtag($data);
    $item     = $this->formatter->toHashtag($data, 'item');
    $blazies  = $settings['blazies'];
    $options  = $settings['caption'] ?? [];
    $options  = array_filter($options);
    $display  = empty($settings['svg_hide_caption']);
    $type     = $blazies->get('field.type');
    $output   = [];

    if (!$options) {
      return [];
    }

    // Provides default image captions.
    if ($item) {
      foreach ($options as $name) {
        if ($content = ($item->{$name} ?? NULL)) {
          $caption = Sanitize::caption($content);

          // Entity file with description_field enabled, useful for SVG:
          if ($name == 'description') {
            $blazies->set('image.description', $caption);
          }
          // SVG image field, or plain old image:
          elseif ($name == 'alt' || $name == 'title') {
            // Conflict with sub-modules' markups, not blazy's.
            // @todo enable at 3,x when they use theme_blazy().
            // if ($caption  && $name == 'alt') {
            // $caption = '<p>' . $caption . '</p>';
            // }.
            $blazies->set('image.' . $name, $caption);
          }

          if ($display) {
            $output[$name] = ['#markup' => $caption];
          }
        }
      }
    }

    // Provides fieldable captions.
    if ($type == 'entity_reference' && $entity = $data['#parent'] ?? NULL) {
      foreach ($options as $name) {
        if ($markup = BlazyField::view($entity, $name, [])) {
          $output[$name] = $markup;
        }
      }
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   *
   * @todo move it into BlazyFileSvgFormatterBase after sub-modules.
   */
  protected function getEntityScopes(): array {
    return [
      'fieldable_form'   => TRUE,
      'multimedia'       => TRUE,
      'no_loading'       => TRUE,
      'no_preload'       => TRUE,
      'responsive_image' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    $multiple = $this->isMultiple();
    $is_image = $this->fieldDefinition->getType() == 'image';

    return [
      'background'        => TRUE,
      'captions'          => $this->getCaptionOptions(),
      'grid_form'         => $multiple,
      'image_style_form'  => TRUE,
      'media_switch_form' => TRUE,
      'svg_form'          => static::$useSvg,
      'style'             => $multiple,
      'thumbnail_style'   => TRUE,
      'no_image_style'    => FALSE,
      'responsive_image'  => TRUE,
      'multiple'          => $multiple,
      'view_mode'         => $is_image ? NULL : $this->viewMode,
      'no_view_mode'      => $is_image,
    ];
  }

  /**
   * Returns available bundles.
   *
   * @todo move it into BlazyFileSvgFormatterBase after sub-modules.
   */
  protected function getAvailableBundles(): array {
    $field = $this->fieldDefinition;
    if (method_exists($field, 'get')) {
      $bundle = $field->get('bundle');
      return $bundle ? [$bundle => $bundle] : [];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * @todo move some into BlazyFileSvgFormatterBase after sub-modules.
   */
  protected function getCaptionOptions() {
    $field    = $this->fieldDefinition;
    $type     = $field->getType();
    $_texts   = ['text', 'text_long', 'string', 'string_long', 'link'];
    $captions = [];

    if ($field->getSetting('description_field')) {
      $captions['description'] = $this->t('Description');
    }
    elseif ($type == 'image' || $type == 'svg_image_field') {
      $captions = 'default';
    }
    else {
      if (method_exists($field, 'get')) {
        $captions = $this->getFieldOptions($_texts, $field->get('entity_type'));
      }
    }
    return $captions;
  }

  /**
   * Returns fields as options. Passing empty array will return them all.
   *
   * @return array
   *   The available fields as options.
   *
   * @todo move it into BlazyFileSvgFormatterBase after sub-modules.
   */
  protected function getFieldOptions(array $names = [], $target_type = NULL): array {
    $field       = $this->fieldDefinition;
    $target_type = $target_type ?: $this->getFieldSetting('target_type');
    $bundles     = $this->getAvailableBundles();
    $type        = method_exists($field, 'get') ? $field->get('entity_type') : NULL;

    if (!$bundles && $type && $service = Blazy::service('entity_type.bundle.info')) {
      $bundles = $service->getBundleInfo($type);
    }

    return $this->admin()->getFieldOptions($bundles, $names, $target_type);
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

  /**
   * Builds the item using theme_blazy(), if so-configured.
   *
   * This is the future implementation after mergers at/by 3.x.
   *
   * This is a preliminary exercise for 3.x mergers. Basically, replacing
   * sub-modules theme_ITEM() with theme_blazy() identified by merged captions.
   * We all have similar IMAGE + CAPTION constructs. The only difference is
   * sub-modules separate blazy image from captions while Blazy merges them.
   * Plus thumbnails, already managed by themselves, not blazy's business.
   * Mergers allow improvements as seen with thumbnail below at one go.
   */
  private function themeBlazy(array &$data, array $captions, $delta): array {
    $is_blazy = static::$namespace == 'blazy';
    $internal = $data;

    // Allows sub-modules to use theme_blazy() as their theme_ITEM() contents.
    if (!$is_blazy) {
      $this->toBlazy($internal, $captions, $delta);
    }

    $internal['captions'] = $captions;
    $blazy = $this->formatter->getBlazy($internal);

    if ($is_blazy) {
      $element = $blazy;
    }
    else {
      // This also might be just removed at 3.x, so to leave it all to blazy.
      // Currently still needed as fallback due to being optional.
      $element = $data;
      $element[static::$itemId] = $blazy;
      $this->updateSettings($element, $blazy);
    }
    return $element;
  }

  /**
   * This is the current implementation before mergers at 3.x.
   *
   * Looks simpler, yet it has lots of dup efforts downstream.
   */
  private function themeItem(array &$data, array $captions, $delta): array {
    $internal = $data;

    // Split for different formatters with very minimal difference.
    if (static::$namespace == 'blazy') {
      $internal[static::$captionId] = $captions;
      $element = $this->formatter->getBlazy($internal);
    }
    else {
      $blazy = $this->formatter->getBlazy($internal);
      $element = $data;

      $element[static::$itemId] = $blazy;
      $element[static::$captionId] = $captions;

      $this->updateSettings($element, $blazy);
    }
    return $element;
  }

}
