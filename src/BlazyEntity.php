<?php

namespace Drupal\blazy;

use Drupal\Core\Entity\EntityInterface;
use Drupal\blazy\Field\BlazyField;
use Drupal\blazy\Media\BlazyOEmbedInterface;
use Drupal\blazy\Utility\CheckItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\blazy\Deprecated\BlazyEntityDeprecatedTrait;

/**
 * Provides common entity utilities to work with field details.
 */
class BlazyEntity implements BlazyEntityInterface {

  use BlazyEntityDeprecatedTrait;

  /**
   * The blazy oembed service.
   *
   * @var \Drupal\blazy\Media\BlazyOEmbedInterface
   */
  protected $oembed;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * Constructs a BlazyFormatter instance.
   */
  public function __construct(BlazyOEmbedInterface $oembed) {
    $this->oembed = $oembed;
    $this->blazyManager = $oembed->blazyManager();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('blazy.oembed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function oembed() {
    return $this->oembed;
  }

  /**
   * {@inheritdoc}
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * {@inheritdoc}
   *
   * @todo make it single param after sub-modules for easy updates.
   */
  public function build(array $data, $entity = NULL, $fallback = ''): array {
    // Using hashed key to avoid render error with BVEF due to out of sync.
    // @todo remove the second after migrations at/by 3.x.
    $entity   = $data['#entity'] ?? $entity;
    $fallback = $data['fallback'] ?? $fallback;
    $settings = &$data['settings'];
    $manager  = $this->blazyManager;

    if (!$entity instanceof EntityInterface) {
      return [];
    }

    if ($denied = $manager->denied($entity)) {
      return $denied;
    }

    // @todo remove $settings after sub-modules: gridstack, slick_browser.
    $delta = $settings['delta'] = $data['delta'] ?? ($settings['delta'] ?? -1);

    // Prepare container settings.
    // This class was designed for a single entity, not multiple.
    // Call this method at the container level if multiple.
    $this->prepare($data);

    // Individual entity settings.
    self::settings($settings, $entity);
    $blazies = $settings['blazies']->reset($settings);
    $blazies->set('delta', $delta);

    $manager->postSettingsAlter($settings, $entity);

    // Build the Media item.
    $this->oembed->build($data);
    $settings = $data['settings'];
    $blazies = $settings['blazies'];

    // @todo remove for $data after single param implemented.
    $view = [
      '#entity' => $entity,
      'settings' => $settings,
      'fallback' => $fallback,
    ];

    // Only pass to Blazy for known entities related to File or Media.
    if (in_array($entity->getEntityTypeId(), ['file', 'media'])) {
      /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $data['item'] */
      if (empty($data['item'])) {
        $data['content'][] = $this->view($view);
      }

      // Pass it to Blazy for consistent markups.
      unset($data['delta'], $data['fallback']);
      $build = $manager->getBlazy($data);

      // Allows top level elements to load Blazy once rather than per field.
      // This is still here for non-supported Views style plugins, etc.
      $detached = $blazies->is('detached') ?: $settings['_detached'] ?? FALSE;
      if (!$detached) {
        $load = $manager->attach($settings);
        $build['#attached'] = $manager->merge($load, $build, '#attached');
      }
    }
    else {
      $build = $this->view($view);
    }

    $manager->moduleHandler()->alter('blazy_build_entity', $build, $entity, $settings);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$data): void {
    $manager  = $this->blazyManager;
    $settings = &$data['settings'];

    Blazy::verify($settings);

    $blazies = $settings['blazies'];
    if ($blazies->was('entity_prepared')) {
      return;
    }

    $manager->preSettings($settings);
    $manager->prepareData($data);
    $manager->postSettings($settings);

    $blazies->set('was.entity_prepared', TRUE);
  }

  /**
   * {@inheritdoc}
   *
   * @todo make it single param after sub-modules for easy updates.
   */
  public function view($entity, array $settings = [], $fallback = ''): array {
    if (is_array($entity)) {
      $settings = $entity['settings'] ?? [];
      $fallback = $entity['fallback'] ?? '';
      $entity = $entity['#entity'] ?? NULL;
    }

    // Re-defined, needed downstream by local video, etc.
    $settings['view_mode'] = $settings['view_mode'] ?? 'default';

    // @todo remove $data as the single param after sub-modules.
    $data = [
      '#entity' => $entity,
      'settings' => $settings,
      'fallback' => $fallback,
    ];

    if ($entity instanceof EntityInterface) {
      $build = $this->blazyManager->view($data);

      // @todo figure out why video_file empty, this is blatant assumption.
      if ($entity->getEntityTypeId() == 'file') {
        try {
          $build = BlazyField::getOrViewMedia($entity, $settings, TRUE) ?: $build;
        }
        catch (\Exception $ignore) {
          // Do nothing, no need to be chatty in mischievous deeds.
        }
      }
      return $build;
    }
    return [];
  }

  /**
   * Modifies the common settings extracted from the given entity.
   */
  public static function settings(array &$settings, $entity): void {
    // Might be accessed by tests, or anywhere outside the workflow.
    Blazy::verify($settings);
    $blazies = $settings['blazies'];
    $langcode = $blazies->get('language.current');

    if ($info = CheckItem::entity($entity, $langcode)) {
      $data = $info['data'];
      $id = $data['id'];
      $rid = $data['rid'];

      $blazies->set('cache.keys', [$id, $rid], TRUE)
        ->set('entity', $data, TRUE);
    }
  }

}
