<?php

namespace Drupal\blazy\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatterTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for entity reference formatters without field details.
 *
 * @see \Drupal\blazy\Field\BlazyEntityMediaBase
 */
abstract class BlazyEntityVanillaBase extends EntityReferenceFormatterBase {

  // Since 2.9 Blazy adapts to sub-module self::viewElements() to DRY so they
  // can remove their own FormatterViewTrait later thanks to similarities.
  use BlazyFormatterTrait {
    pluginSettings as traitPluginSettings;
  }

  /**
   * The module namespace.
   *
   * @var string
   * @see https://www.php.net/manual/en/reserved.keywords.php
   */
  protected static $namespace = 'blazy';

  /**
   * The item property to store image or media: content, slide, box, etc.
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
   * Tne navigation ID.
   *
   * @var string
   */
  protected static $navId = 'thumb';

  /**
   * The fake field type identifier for service DI, e.g: entity, image, text.
   *
   * @var string
   */
  protected static $fieldType = 'entity';

  /**
   * Whether using the SVG.
   *
   * @var bool
   */
  protected static $useSvg = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    return static::injectServices($instance, $container, static::$fieldType);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element    = [];
    $definition = $this->getScopedFormElements();

    $definition['_views'] = isset($form['field_api_classes']);

    // @todo remove after sub-modules.
    $definition['view_mode'] = $this->viewMode;
    $definition['plugin_id'] = $this->getPluginId();
    $definition['target_type'] = $this->getFieldSetting('target_type');

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
   * Returns media contents.
   */
  protected function buildElements(array &$build, $entities, $langcode) {
    foreach ($entities as $delta => $entity) {
      // Protect ourselves from recursive rendering.
      static $depth = 0;
      $depth++;
      if ($depth > 20) {
        $this->loggerFactory->get('entity')
          ->error('Recursive rendering detected when rendering entity @entity_type @entity_id. Aborting rendering.', [
            '@entity_type' => $entity->getEntityTypeId(),
            '@entity_id' => $entity->id(),
          ]);
        return $build;
      }

      $build['#delta']    = $delta;
      $build['#entity']   = $entity;
      $build['#langcode'] = $langcode;

      $this->preElement($build);

      // Add the entity to cache dependencies so to clear when it is updated.
      if ($item = $build['items'][$delta] ?? []) {
        $this->formatter
          ->renderer()
          ->addCacheableDependency($item, $entity);
      }

      $depth = 0;
    }
  }

  /**
   * Returns available bundles.
   */
  protected function getAvailableBundles(): array {
    $field = $this->fieldDefinition;
    return BlazyField::getAvailableBundles($field);
  }

  /**
   * Returns fields as options. Passing empty array will return them all.
   *
   * @return array
   *   The available fields as options.
   */
  protected function getFieldOptions(array $names = [], $target_type = NULL): array {
    $target_type = $target_type ?: $this->getFieldSetting('target_type');
    $bundles     = $this->getAvailableBundles();

    return $this->admin()->getFieldOptions($bundles, $names, $target_type);
  }

  /**
   * Prepare item contents.
   */
  protected function preElement(array &$build): void {
    // @todo remove the helper at/ by 3.x post migrations:
    $this->formatter->hashtag($build);

    $settings = &$build['#settings'];
    $delta    = $build['#delta'];
    $entity   = $build['#entity'];
    $langcode = $build['#langcode'];
    $blazies  = $settings['blazies']->reset($settings);
    $bundle   = $entity->bundle();

    $blazies->set('bundles.' . $bundle, $bundle, TRUE)
      ->set('language.code', $langcode)
      ->set('delta', $delta);

    // @todo remove at 3.x, not used by any sub-modules:
    $this->prepareElement($build, $entity, $langcode, $delta);
    $this->withElement($build);
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    $multiple = $this->isMultiple();
    return [
      'no_layouts'       => TRUE,
      'no_image_style'   => TRUE,
      'responsive_image' => FALSE,
      'target_bundles'   => $this->getAvailableBundles(),
      'vanilla'          => TRUE,
      'view_mode'        => $this->viewMode,
      'multiple'         => $this->isMultiple(),
      'grid_form'        => $multiple,
      'style'            => $multiple,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function pluginSettings(&$blazies, array &$settings): void {
    $this->traitPluginSettings($blazies, $settings);
    $blazies->set('is.blazy', TRUE);

    // @todo remove.
    $settings['blazy'] = TRUE;
  }

  /**
   * Build item elements.
   */
  protected function withElement(array &$build) {
    $settings = $build['#settings'];
    $entity   = $build['#entity'];
    $langcode = $build['#langcode'];

    // Sub-modules always flag `vanilla` as required, -- configurable, or not.
    if (!empty($settings['vanilla'])) {
      // @todo recheck to pass $build directly, last time it caused too early
      // render error somewhere.
      $data = [
        '#entity'   => $entity,
        '#settings' => $settings,
        '#delta'    => $build['#delta'],
        '#langcode' => $build['#langcode'],
      ];

      // @todo merge all these after sub-modules use theme_blazy() at/ by 3.x.
      if ($output = $this->blazyEntity->view($data)) {
        if (static::$namespace == 'blazy') {
          $build['items'][] = $output;
        }
        else {
          $build['items'][] = [static::$itemId => $output];
        }
      }
    }

    // @todo remove at 3.x for self::withElement().
    $this->buildElement($build, $entity, $langcode);
  }

  /**
   * Deprecated in blazy:8.x-2.17,  and is removed from blazy:3.0.0.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   *   self::withElement() instead.
   * @see https://www.drupal.org/node/3367291
   */
  protected function buildElement(array &$build, $entity, $langcode) {
    // @todo @trigger_error('buildElement is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use self::withElement() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
  }

  /**
   * Prepare item contents.
   *
   * @todo remove extra params at 3 for destructured properties from $build.
   */
  protected function prepareElement(array &$build, $entity, $langcode, $delta): void {
    // @todo @trigger_error('prepareElement is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use self::withElement() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
  }

}
