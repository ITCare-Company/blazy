<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\blazy\BlazyDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Blazy Grid Text' formatter.
 *
 * @FieldFormatter(
 *   id = "blazy_text",
 *   label = @Translation("Blazy Grid"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *   },
 *   quickedit = {"editor" = "disabled"}
 * )
 */
class BlazyTextFormatter extends FormatterBase {

  use BlazyFormatterTrait;

  /**
   * {@inheritdoc}
   */
  protected $namespace = 'blazy';

  /**
   * {@inheritdoc}
   */
  protected $itemId = 'blazy';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    return self::injectServices($instance, $container, 'text');
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return BlazyDefault::gridSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    return $this->baseViewElements($items, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $this->admin()->buildSettingsForm($element, $this->getScopedFormElements());
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->isMultiple();
  }

  /**
   * Build the grid text elements.
   */
  protected function buildElements(array &$build, $items, $langcode) {
    $settings = $build['settings'];
    $blazies = $settings['blazies'];

    $blazies->set('is.grid', TRUE)
      ->set('is.unblazy', TRUE)
      ->set('is.text', TRUE)
      ->set('lazy', []);

    // Since 2.17, match sub-modules `items` for easy swap later to DRY.
    foreach ($this->getElements($items) as $element) {
      $build['items'][] = $element;
    }
  }

  /**
   * Returns the Blazy elements, also for sub-modules to re-use.
   */
  protected function getElements($items): \Generator {
    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    foreach ($items as $item) {
      $element = [];
      if ($item instanceof FieldItemInterface) {
        $class    = get_class($item);
        $property = $class::mainPropertyName();
        $value    = $item->{$property};

        if (!$value) {
          continue;
        }

        $element = [
          '#type'     => 'processed_text',
          '#text'     => $value,
          '#format'   => $item->format ?? NULL,
          '#langcode' => $item->getLangcode(),
        ];
      }

      yield $element;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    return [
      'grid_form'        => TRUE,
      'grid_required'    => TRUE,
      'no_image_style'   => TRUE,
      'no_layouts'       => TRUE,
      'responsive_image' => FALSE,
      'style'            => TRUE,
      'multiple'         => $this->isMultiple(),
    ];
  }

}
