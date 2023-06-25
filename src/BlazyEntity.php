<?php

namespace Drupal\blazy;

use Drupal\Core\Entity\EntityInterface;
use Drupal\blazy\Field\BlazyField;
use Drupal\blazy\Media\BlazyOEmbedInterface;
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
  public function build(array &$data, $entity = NULL, $fallback = ''): array {
    $entity = $data['entity'] ?? $entity;
    $fallback = $data['fallback'] ?? $fallback;
    $manager = $this->blazyManager;
    $settings = &$data['settings'];

    if (!$entity instanceof EntityInterface) {
      return [];
    }

    if ($denied = $manager->denied($entity)) {
      return $denied;
    }

    // @todo remove $settings after sub-modules: gridstack, slick_browser.
    $delta = $settings['delta'] = $data['delta'] ?? ($settings['delta'] ?? -1);
    unset($data['entity'], $data['delta'], $data['fallback']);

    // Common settings.
    $manager->preSettings($settings);
    $manager->prepareData($data, $entity);
    $manager->postSettings($settings);

    // Entity settings.
    self::settings($settings, $entity);
    $blazies = $settings['blazies']->reset($settings);
    $blazies->set('delta', $delta);

    $manager->postSettingsAlter($settings, $entity);

    // Build the Media item.
    $this->oembed->build($data, $entity);
    $settings = &$data['settings'];
    $view = [
      'entity' => $entity,
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
   *
   * @todo make it single param after sub-modules for easy updates.
   */
  public function view($entity, array $settings = [], $fallback = ''): array {
    if (is_array($entity)) {
      $settings = $entity['settings'] ?? [];
      $fallback = $entity['fallback'] ?? '';
      $entity = $entity['entity'] ?? NULL;
    }

    $settings['view_mode'] = $settings['view_mode'] ?? 'default';
    // @todo remove $data as the single param after sub-modules.
    $data = [
      'entity' => $entity,
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
    $internal_path = $absolute_path = NULL;
    $langcode = $blazies->get('language.current');

    // @todo remove after test updates.
    if (!$entity) {
      return;
    }

    // Deals with UndefinedLinkTemplateException such as paragraphs type.
    // @see #2596385, or fetch the host entity.
    if (!$entity->isNew()) {
      try {
        // Provides translated $entity, if any.
        $entity = Blazy::translated($entity, $langcode);
        $url = $entity->toUrl();

        $internal_path = $url->getInternalPath();
        $absolute_path = $url->setAbsolute()->toString();
      }
      catch (\Exception $ignore) {
        // Do nothing.
      }
    }

    $id = $entity->id();
    $rid = $entity->getRevisionID();
    $blazies->set('cache.keys', [$id, $rid], TRUE);

    $info = [
      'bundle' => $entity->bundle(),
      'id' => $id,
      'rid' => $rid,
      'type_id' => $entity->getEntityTypeId(),
      'url' => $absolute_path,
      'path' => $internal_path,
    ];

    $blazies->set('entity', $info, TRUE);
  }

}
