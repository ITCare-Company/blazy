<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\blazy\Cache\BlazyCache;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Media\BlazyResponsiveImage;
use Drupal\blazy\Utility\Check;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides common shared methods across Blazy ecosystem to DRY.
 */
abstract class BlazyManagerBase implements BlazyManagerInterface {

  // Fixed for EB AJAX issue: #2893029.
  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The app root.
   *
   * @var \SplString
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
   * Static cache for the lightboxes.
   *
   * @var array
   */
  protected $lightboxes;

  /**
   * Constructs a BlazyManager object.
   */
  public function __construct($root, EntityRepositoryInterface $entity_repository, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, RendererInterface $renderer, ConfigFactoryInterface $config_factory, CacheBackendInterface $cache) {
    $this->root              = $root;
    $this->entityRepository  = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler     = $module_handler;
    $this->renderer          = $renderer;
    $this->configFactory     = $config_factory;
    $this->cache             = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      Blazy::root($container),
      $container->get('entity.repository'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('renderer'),
      $container->get('config.factory'),
      $container->get('cache.default')
    );

    // @todo remove and use DI at 2.x+ post sub-classes updates.
    $instance->setLanguageManager($container->get('language_manager'));
    return $instance;
  }

  /**
   * Returns the app root.
   */
  public function root() {
    return $this->root;
  }

  /**
   * Returns the language manager service.
   */
  public function languageManager() {
    return $this->languageManager;
  }

  /**
   * Sets the language manager service.
   *
   * @todo remove and use DI at 3.x+ post sub-classes updates.
   */
  public function setLanguageManager($language_manager) {
    $this->languageManager = $language_manager;
    return $this;
  }

  /**
   * Returns the entity repository service.
   */
  public function getEntityRepository() {
    return $this->entityRepository;
  }

  /**
   * Returns the entity type manager.
   */
  public function getEntityTypeManager() {
    return $this->entityTypeManager;
  }

  /**
   * Returns the module handler.
   */
  public function getModuleHandler() {
    return $this->moduleHandler;
  }

  /**
   * Returns the renderer.
   */
  public function getRenderer() {
    return $this->renderer;
  }

  /**
   * Returns the config factory.
   */
  public function getConfigFactory() {
    return $this->configFactory;
  }

  /**
   * Returns the cache.
   */
  public function getCache() {
    return $this->cache;
  }

  /**
   * Returns any config, or keyed by the $setting_name.
   */
  public function configLoad($setting_name = '', $settings = 'blazy.settings') {
    $config  = $this->configFactory->get($settings);
    $configs = $config->get();
    unset($configs['_core']);
    return empty($setting_name) ? $configs : $config->get($setting_name);
  }

  /**
   * Returns a shortcut for entity type storage.
   */
  public function getStorage($type = 'media') {
    return $this->entityTypeManager->getStorage($type);
  }

  /**
   * Returns a shortcut for loading entity by its properties.
   */
  public function loadByProperties($properties, $type = 'file') {
    return $this->getStorage($type)->loadByProperties($properties);
  }

  /**
   * Returns a shortcut for loading entity by its UUID.
   */
  public function loadByUuid($uuid, $type = 'file') {
    return $this->entityRepository->loadEntityByUuid($type, $uuid);
  }

  /**
   * Returns a shortcut for loading a config entity: image_style, slick, etc.
   */
  public function entityLoad($id, $type = 'image_style') {
    return $this->getStorage($type)->load($id);
  }

  /**
   * Returns a shortcut for loading multiple configuration entities.
   */
  public function entityLoadMultiple($type = 'image_style', $ids = NULL) {
    return $this->getStorage($type)->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function attach(array $attach = []) {
    $load = [];
    Check::attachments($load, $attach);

    $this->moduleHandler->alter('blazy_attach', $load, $attach);
    return $load;
  }

  /**
   * {@inheritdoc}
   */
  public function getIoSettings(array $attach = []) {
    $io = [];
    $thold = trim($this->configLoad('io.threshold') ?? "");
    $thold = str_replace(['[', ']'], '', $thold ?: '0');

    // @todo re-check, looks like the default 0 is broken sometimes.
    if ($thold == '0') {
      $thold = '0, 0.25, 0.5, 0.75, 1';
    }

    $thold = strpos($thold, ',') !== FALSE ? array_map('trim', explode(',', $thold)) : [$thold];
    $formatted = [];
    foreach ($thold as $value) {
      $formatted[] = strpos($value, '.') !== FALSE ? (float) $value : (int) $value;
    }

    // Respects hook_blazy_attach_alter() for more fine-grained control.
    foreach (['disconnect', 'rootMargin', 'threshold'] as $key) {
      $default = $key == 'rootMargin' ? '0px' : FALSE;
      $value = $key == 'threshold' ? $formatted : $this->configLoad('io.' . $key);
      $io[$key] = $attach['io.' . $key] ?? ($value ?: $default);
    }

    return (object) $io;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareData(array &$build, $entity = NULL): void {
    // Do nothing, let extenders share data at ease as needed.
  }

  /**
   * Prepare base preliminary settings.
   *
   * The `fx` sequence: hook_alter > formatters (not implemented yet) > UI.
   * The `_fx` is a special flag such as to temporarily disable till needed.
   * Called by field formatters, views [styles|fields via BlazyEntity],
   * [blazy|splide|slick] filters.
   */
  public function preSettings(array &$settings = []): void {
    Blazy::verify($settings);

    $blazies = $settings['blazies'];
    $ui = array_intersect_key($this->configLoad(), BlazyDefault::uiSettings());
    $iframe_domain = $this->configLoad('iframe_domain', 'media.settings');
    $is_debug = !$this->configLoad('css.preprocess', 'system.performance');
    $ui['fx'] = $ui['fx'] ?? '';
    $ui['fx'] = empty($settings['fx']) ? $ui['fx'] : $settings['fx'];
    $fx = $settings['fx'] = $settings['_fx'] ?? $ui['fx'];
    $language = $this->languageManager->getCurrentLanguage()->getId();
    $lightboxes = $this->getLightboxes();
    $lightboxes = $blazies->get('lightbox.plugins', $lightboxes) ?: [];
    $is_blur = $fx == 'blur';
    $is_resimage = $this->moduleHandler->moduleExists('responsive_image');

    $blazies->set('fx', $fx)
      ->set('iframe_domain', $iframe_domain)
      ->set('is.blur', $is_blur)
      ->set('is.debug', $is_debug)
      ->set('is.resimage', $is_resimage)
      ->set('is.unblazy', $this->configLoad('io.unblazy'))
      ->set('language.current', $language)
      ->set('libs.animate', $fx)
      ->set('libs.blur', $is_blur)
      ->set('lightbox.plugins', $lightboxes)
      ->set('ui', $ui);

    if ($router = Blazy::routeMatch()) {
      $settings['route_name'] = $route_name = $router->getRouteName();
      $blazies->set('route_name', $route_name);
    }

    // Preliminary globals when using the provided API.
    Blazy::preSettings($settings);
  }

  /**
   * Modifies the common UI settings inherited down to each item.
   */
  public function postSettings(array &$settings = []) {
    Blazy::postSettings($settings);
  }

  /**
   * {@inheritdoc}
   */
  public function getLightboxes() {
    if (!isset($this->lightboxes)) {
      $cid = 'blazy_lightboxes';

      if ($cache = $this->cache->get($cid)) {
        $this->lightboxes = $cache->data;
      }
      else {
        $lightboxes = BlazyCache::lightboxes($this->root);

        $this->moduleHandler->alter('blazy_lightboxes', $lightboxes);
        $lightboxes = array_unique($lightboxes);
        sort($lightboxes);

        $count = count($lightboxes);
        $tags = Cache::buildTags($cid, ['count:' . $count]);
        $this->cache->set($cid, $lightboxes, Cache::PERMANENT, $tags);

        $this->lightboxes = $lightboxes;
      }
    }
    return $this->lightboxes ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function getImageEffects() {
    $effects[] = 'blur';

    $this->moduleHandler->alter('blazy_image_effects', $effects);
    $effects = array_unique($effects);
    return array_combine($effects, $effects);
  }

  /**
   * {@inheritdoc}
   */
  public function isBlazy(array &$settings, array $data = []) {
    Check::blazyOrNot($settings, $data);
  }

  /**
   * Alias for BlazyCache::metadata() to forget looking up unknown classes.
   */
  public function getCacheMetadata(array $build = []) {
    return BlazyCache::metadata($build);
  }

  /**
   * Alias for BlazyImage::thumbnail() to forget looking up unknown classes.
   */
  public function getThumbnail(array $settings = [], $item = NULL) {
    return BlazyImage::thumbnail($settings, $item);
  }

  /**
   * Provides alterable display styles.
   */
  public function getStyles() {
    $styles = [
      'column' => 'CSS3 Columns',
      'grid' => 'Grid Foundation',
      'flex' => 'Flexbox Masonry',
      'nativegrid' => 'Native Grid',
    ];
    $this->moduleHandler->alter('blazy_style', $styles);
    return $styles;
  }

  /**
   * Provides attachments and cache common for all blazy-related modules.
   */
  protected function setAttachments(
    array &$element,
    array $settings,
    array $attachments = []
  ) {
    $cache                = $this->getCacheMetadata($settings);
    $attached             = $this->attach($settings);
    $attachments          = empty($attachments)
      ? $attached : NestedArray::mergeDeep($attached, $attachments);
    $element['#attached'] = empty($element['#attached'])
      ? $attachments : NestedArray::mergeDeep($element['#attached'], $attachments);
    $element['#cache']    = empty($element['#cache'])
      ? $cache : NestedArray::mergeDeep($element['#cache'], $cache);
  }

  /**
   * Collects defined skins as registered via hook_MODULE_NAME_skins_info().
   *
   * @todo remove for sub-modules own skins as plugins at blazy:8.x-2.1+.
   * @see https://www.drupal.org/node/2233261
   * @see https://www.drupal.org/node/3105670
   */
  public function buildSkins($namespace, $skin_class, $methods = []) {
    return [];
  }

  /**
   * Deprecated method, not safe to remove before 3.x for being generic.
   *
   * @deprecated in blazy:8.x-2.5 and is removed from blazy:3.0.0. Use
   *   BlazyResponsiveImage::styles() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getResponsiveImageStyles($responsive) {
    return BlazyResponsiveImage::styles($responsive);
  }

  /**
   * Deprecated method, safe to remove before 3.x for being too specific.
   *
   * @deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   self::postSettings() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getCommonSettings(array &$settings = []) {
    $this->postSettings($settings);
  }

  /**
   * Deprecated method, safe to remove before 3.x for being too specific.
   *
   * @deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyEntity::settings() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getEntitySettings(array &$settings, $entity) {
    BlazyEntity::settings($settings, $entity);
  }

  /**
   * Deprecated method, safe to remove before 3.x for being too specific.
   *
   * @deprecated in blazy:8.x-2.5 and is removed from blazy:3.0.0. Use
   *   BlazyResponsiveImage::dimensions() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function setResponsiveImageDimensions(array &$settings = [], $initial = TRUE) {
    BlazyResponsiveImage::dimensions($settings, $initial);
  }

}
