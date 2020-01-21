<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\media\Entity\MediaType;
use Drupal\media\Plugin\media\Source\OEmbedInterface;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazyFormatterInterface;
use Drupal\blazy\BlazyOEmbedInterface;
use Drupal\blazy\Dejavu\BlazyDependenciesTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin for blazy oembed formatter.
 *
 * @FieldFormatter(
 *   id = "blazy_oembed",
 *   label = @Translation("Blazy"),
 *   field_types = {
 *     "link",
 *     "string",
 *     "string_long",
 *   }
 * )
 *
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatterBase
 * @see \Drupal\media\Plugin\Field\FieldFormatter\OEmbedFormatter
 */
class BlazyOEmbedFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  use BlazyDependenciesTrait;
  use BlazyFormatterTrait;

  /**
   * Constructs a BlazyImageFormatter instance.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, BlazyFormatterInterface $formatter, BlazyOEmbedInterface $oembed) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->formatter = $this->blazyManager = $formatter;
    $this->blazyOembed = $oembed;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('blazy.formatter'),
      $container->get('blazy.oembed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return BlazyDefault::baseImageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Collects specific settings to this formatter.
    $settings              = $this->buildSettings();
    $settings['blazy']     = TRUE;
    $settings['namespace'] = $settings['item_id'] = $settings['lazy'] = 'blazy';
    $settings['langcode']  = $langcode;

    // Build the settings.
    $build = ['settings' => $settings];

    // Modifies settings before building elements.
    $this->formatter->preBuildElements($build, $items);

    // Build the elements.
    $this->buildElements($build, $items, $langcode);

    // Modifies settings post building elements.
    $this->formatter->postBuildElements($build, $items);

    // Pass to manager for easy updates to all Blazy formatters.
    return $this->formatter->build($build);
  }

  /**
   * Build the blazy elements.
   */
  public function buildElements(array &$build, $items) {
    $settings = $build['settings'];

    foreach ($items as $delta => $item) {
      $main_property = $item->getFieldDefinition()->getFieldStorageDefinition()->getMainPropertyName();
      $value = trim($item->{$main_property});

      if (empty($value)) {
        continue;
      }

      $settings['delta'] = $delta;
      $settings['input_url'] = $value;
      $image_item = new \stdClass();

      // Attempts to fetch media entity.
      $media = $this->formatter->getEntityTypeManager()->getStorage('media')->loadByProperties([$settings['field_name'] => $value]);
      if (count($media) && $media = reset($media)) {
        $data['settings'] = $settings;
        $this->blazyOembed->getMediaItem($data, $media);

        // Update data with local image.
        $settings = array_merge($settings, $data['settings']);
        $image_item = $data['item'];
      }

      $box = ['item' => $image_item, 'settings' => $settings];

      // Media OEmbed with lazyLoad and lightbox supports.
      $build[$delta] = $this->formatter->getBlazy($box);
      unset($box);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $definition = $this->getScopedFormElements();
    $definition['_views'] = isset($form['field_api_classes']);

    $this->admin()->buildSettingsForm($element, $definition);

    // Makes options look compact.
    if (isset($element['background'])) {
      $element['background']['#weight'] = -99;
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getScopedFormElements() {
    return [
      'background'        => TRUE,
      'media_switch_form' => TRUE,
      'multimedia'        => TRUE,
      'responsive_image'  => FALSE,
    ] + $this->getCommonScopedFormElements();
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    if ($field_definition->getTargetEntityTypeId() !== 'media') {
      return FALSE;
    }

    if ($media_type = $field_definition->getTargetBundle()) {
      $media_type = MediaType::load($media_type);
      return $media_type && $media_type->getSource() instanceof OEmbedInterface;
    }

    return FALSE;
  }

}
