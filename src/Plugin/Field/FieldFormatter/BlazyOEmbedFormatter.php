<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\media\Entity\MediaType;
use Drupal\media\Plugin\media\Source\OEmbedInterface;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Field\BlazyDependenciesTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin for blazy oembed formatter.
 *
 * @FieldFormatter(
 *   id = "blazy_oembed",
 *   label = @Translation("Blazy OEmbed"),
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
class BlazyOEmbedFormatter extends FormatterBase {

  use BlazyDependenciesTrait;
  use BlazyFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    return self::injectServices($instance, $container, 'entity');
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
    return $this->commonViewElements($items, $langcode);
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
    return parent::settingsForm($form, $form_state) + $element;
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

  /**
   * Build the blazy elements.
   */
  protected function buildElements(array &$build, $items, $langcode) {
    $settings   = $build['settings'];
    $field_name = $this->fieldDefinition->getName();
    $entity     = $items->getParent()->getEntity();

    foreach ($items as $delta => $item) {
      if (!$item instanceof FieldItemInterface) {
        break;
      }

      $class    = get_class($item);
      $property = $class::mainPropertyName();
      $value    = $item->{$property};
      $sets     = $settings;

      if (!$value) {
        continue;
      }

      $blazies = $sets['blazies']->reset($sets);
      $blazies->set('delta', $delta)
        ->set('media.input_url', $value);

      $data = ['item' => NULL, 'settings' => $sets];

      if ($entity->getEntityTypeId() == 'media'
            && $entity->hasField($field_name)
            && $entity->get($field_name)->getString() == $value) {
        // We are on the right media entity.
        $media = $entity;
      }
      else {
        // Attempts to fetch media entity.
        $media = $this->formatter
          ->loadByProperties([
            $field_name => $value,
          ], 'media', TRUE);
        $media = reset($media);
      }

      if ($media) {
        $data['#entity'] = $media;
        $this->blazyOembed->build($data);
      }

      // Media OEmbed with lazyLoad and lightbox supports.
      $build[$delta] = $this->formatter->getBlazy($data);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    return [
      'image_style_form'  => TRUE,
      'background'        => TRUE,
      'media_switch_form' => TRUE,
      'multimedia'        => TRUE,
      'no_preload'        => TRUE,
      'responsive_image'  => TRUE,
    ];
  }

}
