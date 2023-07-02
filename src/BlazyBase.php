<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\blazy\Cache\BlazyCache;
use Drupal\blazy\Utility\BlazyMarkdown;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides common non-media/ generic methods across Blazy ecosystem to DRY.
 */
abstract class BlazyBase implements BlazyInterface {

  // Fixed for EB AJAX issue: #2893029.
  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The app root.
   *
   * @var string
   */
  protected $root;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * The cached data/ options.
   *
   * @var array
   */
  protected $cachedOptions;

  /**
   * The DOM purify path.
   *
   * @var string
   */
  protected $libraresPathAlt;

  /**
   * Constructs a BlazyBase object.
   */
  public function __construct(
    $root,
    EntityRepositoryInterface $entity_repository,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    RendererInterface $renderer,
    ConfigFactoryInterface $config_factory,
    CacheBackendInterface $cache,
    LanguageManager $language_manager
  ) {
    $this->root              = $root;
    $this->entityRepository  = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler     = $module_handler;
    $this->renderer          = $renderer;
    $this->configFactory     = $config_factory;
    $this->cache             = $cache;
    $this->languageManager   = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      Blazy::root($container),
      $container->get('entity.repository'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('renderer'),
      $container->get('config.factory'),
      $container->get('cache.default'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function root() {
    return $this->root;
  }

  /**
   * {@inheritdoc}
   */
  public function entityRepository() {
    return $this->entityRepository;
  }

  /**
   * {@inheritdoc}
   */
  public function entityTypeManager() {
    return $this->entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function moduleHandler() {
    return $this->moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function renderer() {
    return $this->renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function configFactory() {
    return $this->configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function cache() {
    return $this->cache;
  }

  /**
   * {@inheritdoc}
   */
  public function languageManager() {
    return $this->languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public function config($key = NULL, $group = 'blazy.settings') {
    $config  = $this->configFactory->get($group);
    $configs = $config->get();
    unset($configs['_core']);
    return empty($key) ? $configs : $config->get($key);
  }

  /**
   * {@inheritdoc}
   */
  public function configSchemaInfoAlter(
    array &$definitions,
    $formatter = 'blazy_base',
    array $settings = []
  ): void {
    BlazyAlter::configSchemaInfoAlter($definitions, $formatter, $settings);
  }

  /**
   * {@inheritdoc}
   */
  public function denied($entity): array {
    return Blazy::denied($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function entityQuery($type, $conjunction = 'AND') {
    return $this->getStorage($type)->getQuery($conjunction);
  }

  /**
   * {@inheritdoc}
   */
  public function getCachedData(
    $cid,
    array $data = [],
    array $info = []
  ): array {
    return $this->getCachedOptions($cid, $data, FALSE, $info);
  }

  /**
   * {@inheritdoc}
   */
  public function getCachedOptions(
    $cid,
    array $data = [],
    $as_options = TRUE,
    array $info = []
  ): array {
    $reset = $info['reset'] ?? FALSE;
    if (!isset($this->cachedOptions[$cid]) || $reset) {
      $cache = $this->cache->get($cid);

      if (!$reset && $cache && $data = $cache->data) {
        $this->cachedOptions[$cid] = $data;
      }
      else {
        $alter = $info['alter'] ?? NULL;
        $context = $info['context'] ?? [];

        // Allows empty array to trigger hook_alter.
        if (is_array($data)) {
          $this->moduleHandler->alter($alter ?: $cid, $data, $context);
        }

        // Only if we have data, cache them.
        if ($data && is_array($data)) {
          if (isset($data[1])) {
            $data = array_unique($data);
          }

          if ($as_options) {
            $data = $this->toOptions($data);
          }
          else {
            ksort($data);
          }

          $count = count($data);
          $tags = Cache::buildTags($cid, ['count:' . $count]);
          $this->cache->set($cid, $data, Cache::PERMANENT, $tags);
        }

        $this->cachedOptions[$cid] = $data;
      }
    }
    return $this->cachedOptions[$cid] ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMetadata(array $build) {
    return BlazyCache::metadata($build);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityAsOptions($entity_type): array {
    $options = [];
    if ($entities = $this->loadMultiple($entity_type)) {
      foreach ($entities as $entity) {
        $options[$entity->id()] = Html::escape($entity->label());
      }
      uasort($options, 'strnatcasecmp');
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getHtmlId($name = 'blazy', $id = ''): string {
    return Blazy::getHtmlId($name, $id);
  }

  /**
   * {@inheritdoc}
   */
  public function getLibrariesPath($name, $base_path = FALSE): ?string {
    return Blazy::getLibrariesPath($name, $base_path);
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraresPathAlternative(
    $base = 'DOMPurify',
    $packagist = 'dompurify',
    $absolute = FALSE
  ): ?string {
    if (!isset($this->libraresPathAlt[$base])) {
      $this->libraresPathAlt[$base] = $this->getLibrariesPath($packagist, $absolute)
        ?: $this->getLibrariesPath($base, $absolute);
    }
    return $this->libraresPathAlt[$base];
  }

  /**
   * {@inheritdoc}
   */
  public function getPath($type, $name, $absolute = FALSE): ?string {
    return Blazy::getPath($type, $name, $absolute);
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage($type = 'media') {
    return $this->entityTypeManager->getStorage($type);
  }

  /**
   * {@inheritdoc}
   */
  public function load($id, $type = 'image_style') {
    if (strpos($type, '.settings') !== FALSE) {
      return $this->config($id, $type);
    }
    return $this->getStorage($type)->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple($type = 'image_style', $ids = NULL): array {
    return $this->getStorage($type)->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByProperties(
    array $values,
    $type = 'file',
    $access = TRUE,
    $conjunction = 'AND',
    $condition = 'IN'
  ): array {
    $storage = $this->getStorage($type);
    $query = $storage->getQuery($conjunction);

    $query->accessCheck($access);
    $this->buildPropertyQuery($query, $values, $condition);

    $result = $query->execute();
    return $result ? $storage->loadMultiple($result) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function loadByUuid($uuid, $type = 'file'): ?object {
    return $this->entityRepository->loadEntityByUuid($type, $uuid);
  }

  /**
   * {@inheritdoc}
   */
  public function markdown($string, $help = TRUE): string {
    return BlazyMarkdown::parse($string, $help);
  }

  /**
   * {@inheritdoc}
   */
  public function merge(array $data, array $element, $key = NULL): array {
    return Blazy::merge($data, $element, $key);
  }

  /**
   * {@inheritdoc}
   */
  public function moduleExists($name): bool {
    return $this->moduleHandler->moduleExists($name);
  }

  /**
   * {@inheritdoc}
   */
  public function service($name): ?object {
    return Blazy::service($name);
  }

  /**
   * {@inheritdoc}
   */
  public function toGrid(array $items, array $settings): array {
    return Blazy::grid($items, $settings);
  }

  /**
   * {@inheritdoc}
   */
  public function toOptions(array $options): array {
    if ($options) {
      $options = array_map('\Drupal\Component\Utility\Html::escape', $options);
      uasort($options, 'strnatcasecmp');
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array $data): array {
    $entity = $data['#entity'] ?? NULL;
    $settings = $data['settings'] ?? [];
    $fallback = $data['fallback'] ?? '';

    // @todo remove after another check.
    if ($fallback && is_string($fallback)) {
      $fallback = [
        '#markup' => '<span class="b-fallback">' . $fallback . '</span>',
      ];
    }

    if ($entity instanceof EntityInterface) {
      if ($denied = $this->denied($entity)) {
        return $denied;
      }

      $type = $entity->getEntityTypeId();
      $langcode = $entity->language()->getId();
      $view_mode = $settings['view_mode'] ?? 'default';
      $manager = $this->entityTypeManager;

      // If entity has view_builder handler.
      if ($manager->hasHandler($type, 'view_builder')) {
        $builder = $manager->getViewBuilder($type);
        return $builder->view($entity, $view_mode, $langcode);
      }
      else {
        // If module implements own {entity_type}_view.
        // The "paragraphs_type" entity type did not specify a view_builder.
        // @todo remove due to being deprecated at D8.7, and after paragraphs.
        // See https://www.drupal.org/node/3033656.
        $view_hook = $type . '_view';
        if (is_callable($view_hook)) {
          return $view_hook($entity, $view_mode, $langcode);
        }
      }
    }
    return $fallback ?: [];
  }

  /**
   * Builds an entity query.
   */
  private function buildPropertyQuery($query, array $values, $condition = 'IN'): void {
    foreach ($values as $name => $value) {
      // Cast scalars to array so we can consistently use an IN condition.
      $query->condition($name, (array) $value, $condition);
    }
  }

}
