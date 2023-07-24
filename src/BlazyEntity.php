<?php

namespace Drupal\blazy;

use Drupal\Core\Entity\EntityInterface;
use Drupal\media\MediaInterface;
use Drupal\blazy\Field\BlazyField;
use Drupal\blazy\Media\BlazyMedia;
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
   */
  public function build(array $data): array {
    $manager = $this->blazyManager;
    $manager->hashtag($data);

    $access   = $data['#access'] ?? FALSE;
    $entity   = $data['#entity'] ?? NULL;
    $settings = &$data['#settings'];

    if (!$entity instanceof EntityInterface) {
      return [];
    }

    if (!$access && $denied = $manager->denied($entity)) {
      return $denied;
    }

    // @todo remove $settings after sub-modules: gridstack, slick_browser.
    $data['#access'] = TRUE;
    $delta = $data['#delta'] ?? ($settings['delta'] ?? -1);

    // Extract media data with translated one, dup required by self::prepare().
    if ($entity instanceof MediaInterface) {
      $entity = BlazyMedia::prepare($data);
    }

    // Build the Media item.
    // No joy here: $this->oembed->build($data);
    // Prepare container settings.
    // This class was designed for a single entity, not multiple.
    // Call this method at the container level if multiple.
    // @todo re-arrange, this needs media metadata from ::oembed() below.
    $this->prepare($data);

    // Individual entity settings.
    self::settings($settings, $entity);
    $blazies = $settings['blazies']->reset($settings);

    $blazies->set('delta', $delta)
      ->set('is.denied', FALSE);

    $manager->postSettingsAlter($settings, $entity);

    // Build the Media item.
    $this->oembed->build($data);
    $blazies = $settings['blazies'];

    // Only pass to Blazy for known entities related to File or Media.
    // @todo move it to BlazyMedia::build() after being a non-static at/by 3.x.
    if (in_array($entity->getEntityTypeId(), ['file', 'media'])) {
      /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      $item = $manager->toHashtag($data, 'item', NULL);
      if (!$item) {
        $data['content'][] = $this->view($data);
      }

      // Pass it to Blazy for consistent markups.
      unset($data['delta'], $data['fallback']);
      $build = $manager->getBlazy($data);

      // Allows top level elements to load Blazy once rather than per field.
      // This is still here for non-supported Views style plugins, etc.
      if (!$blazies->is('detached')) {
        $load = $manager->attach($settings);
        $build['#attached'] = $manager->merge($load, $build, '#attached');
      }
    }
    else {
      $build = $this->view($data);
    }

    $manager->moduleHandler()->alter('blazy_build_entity', $build, $entity, $settings);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$data): void {
    $manager = $this->blazyManager;
    $manager->hashtag($data);

    $settings = &$data['#settings'];
    $manager->verify($settings);

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
   */
  public function view(array $data): array {
    $manager  = $this->blazyManager;
    $settings = $manager->toHashtag($data);
    $entity   = $data['#entity'] ?? NULL;

    if (!$entity instanceof EntityInterface) {
      return [];
    }

    // Provides vanilla entity view.
    $build = $manager->view($data);

    // @todo figure out why video_file empty, this is blatant assumption.
    if ($entity->getEntityTypeId() == 'file') {
      try {
        // Re-defined, needed downstream by local video, etc.
        $settings['view_mode'] = $settings['view_mode'] ?? 'default';
        $build = BlazyField::getOrViewMedia($entity, $settings, TRUE) ?: $build;
      }
      catch (\Exception $ignore) {
        // Do nothing, no need to be chatty in mischievous deeds.
      }
    }
    return $build;
  }

  /**
   * Modifies the common settings extracted from the given entity.
   */
  public static function settings(array &$settings, $entity): void {
    // Might be accessed by tests, or anywhere outside the workflow.
    Blazy::verify($settings);

    $blazies  = $settings['blazies'];
    $langcode = $blazies->get('language.current');

    if ($info = CheckItem::entity($entity, $langcode)) {
      $data = $info['data'];
      $id   = $data['id'];
      $rid  = $data['rid'];

      $blazies->set('cache.metadata.keys', [$id, $rid], TRUE)
        ->set('entity', $data, TRUE);
    }
  }

}
