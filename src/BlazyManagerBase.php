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
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Media\BlazyResponsiveImage;
use Drupal\blazy\Theme\BlazyLightbox;
use Drupal\blazy\Theme\BlazyViews;
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
  public function getStorage($type = 'image_style') {
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
    $this->postSettings($attach);

    $blazies = $attach['blazies'];
    $unblazy = $blazies->is('unblazy', FALSE);
    $unload  = $blazies->get('ui.nojs.lazy', FALSE);
    $switch  = $attach['media_switch'] ?? '';
    $load    = [];

    if ($switch && $switch != 'content') {
      $attach[$switch] = $switch;

      BlazyLightbox::attach($load, $attach);
    }

    // Allow variants of grid, columns, flexbox, native grid to co-exist.
    // @todo remove once moved to blazies.libs, too catch-all.
    if ($attach['style']) {
      $attach[$attach['style']] = $attach['style'];
    }

    // Always keep Drupal UI config to support dynamic compat features.
    $config = $this->configLoad('blazy');
    $config['loader'] = !$unload;
    $config['unblazy'] = $unblazy;

    // One is enough due to various formatters negating each others.
    $compat = $blazies->get('libs.compat');

    // Only if `No JavaScript` option is disabled, or has compat.
    // Compat is a loader for Blur, BG, Video which Native doesn't support.
    if ($compat || !$unload) {
      if ($compat) {
        $config['compat'] = $compat;
      }

      // Modern sites may want to forget oldies, respect.
      if (!$unblazy) {
        $load['library'][] = 'blazy/blazy';
      }

      foreach (BlazyDefault::nojs() as $key) {
        if (empty($blazies->get('ui.nojs.' . $key))) {
          $lib = $key == 'lazy' ? 'load' : $key;
          $load['library'][] = 'blazy/' . $lib;
        }
      }
    }

    $load['drupalSettings']['blazy'] = $config;
    $load['drupalSettings']['blazyIo'] = $this->getIoSettings($attach);

    foreach (BlazyDefault::components() as $component) {
      // @todo remove the second condition, too catch-all.
      if ($blazies->get('libs.' . $component, FALSE) || !empty($attach[$component])) {
        $load['library'][] = 'blazy/' . $component;
      }
    }

    // Adds AJAX helper to revalidate Blazy/ IO, if using VIS, or alike.
    if ($blazies->get('use.ajax', FALSE)) {
      $load['library'][] = 'blazy/bio.ajax';
    }

    // Preload.
    if (!empty($attach['preload'])) {
      BlazyFile::preload($load, $attach);
    }

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
   * Prepare base settings.
   *
   * @todo remove some settings after migration and sub-modules
   */
  public function preSettings(array &$settings = []): void {
    if (!isset($settings['blazies'])) {
      $settings += BlazyDefault::htmlSettings();
    }

    $blazies = $settings['blazies'];
    if ($blazies->is('presettings')) {
      return;
    }

    $ui = array_intersect_key($this->configLoad(), BlazyDefault::uiSettings());
    $switch = $settings['media_switch'];
    $iframe_domain = $this->configLoad('iframe_domain', 'media.settings');
    $lightboxes = $this->getLightboxes();
    $lightbox = $blazies->get('lightbox', $settings['lightbox'] ?? FALSE);
    $lightbox = ($switch && in_array($switch, $lightboxes)) ? $switch : $lightbox;
    $namespace = $blazies->get('namespace', $settings['namespace'] ?? 'blazy');
    $item_id = $blazies->get('item.id', $settings['item_id'] ?? 'blazy');

    $ui['fx'] = $ui['fx'] ?? '';
    $ui['fx'] = empty($settings['fx']) ? $ui['fx'] : $settings['fx'];
    $settings['fx'] = $fx = $settings['_fx'] ?? $ui['fx'];
    $settings['lightbox'] = $lightbox;
    $settings['loading'] = $settings['loading'] ?: 'lazy';
    $settings['route_name'] = $route_name = $this->getRouteName();

    $language = $this->languageManager->getCurrentLanguage()->getId();
    $bundle = $blazies->get('media.bundle', $settings['bundle'] ?? '');
    $is_blur = $fx == 'blur';
    $is_resimage = $this->moduleHandler->moduleExists('responsive_image');
    $is_preview = $settings['is_preview'] = Blazy::isPreview();
    $is_amp = Blazy::isAmp();
    $is_sandboxed = Blazy::isSandboxed();
    $is_bg = !empty($settings['background']);
    $is_unload = !empty($ui['nojs']['lazy']);
    $is_slider = $settings['loading'] == 'slider';
    $is_unloading = $settings['loading'] == 'unlazy';
    $is_defer = $settings['loading'] == 'defer';
    $is_fluid = $settings['ratio'] == 'fluid';
    $is_static = $is_preview || $is_amp || $is_sandboxed;
    $is_undata = $is_static || $is_unloading;
    $is_nojs = $is_unload || $is_undata;
    $is_local_video = $bundle == 'video'
      || in_array('video', $blazies->get('bundles', []));

    // When `defer` is chosen, overrides global `No JavaScript: lazy`, ensures
    // to not affect AMP, CKEditor, or other preview pages where nojs is a must.
    if ($is_nojs && $is_defer) {
      $is_nojs = $is_undata;
    }

    // Compat is anything that Native lazy doesn't support.
    $is_compat = $fx
      || $is_bg
      || $is_fluid
      || $is_local_video
      || $is_defer
      || $blazies->get('libs.compat');

    // Lazy load types: blazy, and slick: ondemand, anticipated, progressive.
    $is_blazy = $blazies->is('blazy', !empty($settings['blazy']));
    $is_blazy = $is_blazy || $is_bg || $blazies->get('resimage.style');
    $lazy = $is_blazy ? 'blazy' : ($settings['lazy'] ?? '');
    $settings['blazy'] = $is_blazy;
    $settings['lazy'] = $is_lazy = $is_nojs ? '' : $lazy;

    // @todo re-check after sub-modules which were only aware of `is_preview`.
    // Basically tricking overrides by the reversed name due to sub-modules are
    // not updated to the new options `No JavaScript` + `Loading priority`, yet.
    // As known, Splide/ Slick have their own lazy, but might break till further
    // updates. Choosing Blazy as their lazyload method is the solution to be
    // compatible with the mentioned options. Better than sacrificing Native.
    $settings['unlazy'] = $is_unlazy = empty($is_lazy);

    // Some should be refined per item against potential mixed media items.
    $blazies->set('is.amp', $is_amp)
      ->set('is.blazy', $is_blazy)
      ->set('is.fluid', $is_fluid)
      ->set('is.lazy', $is_lazy)
      ->set('is.nojs', $is_nojs)
      ->set('is.preview', $is_preview)
      ->set('is.sandboxed', $is_sandboxed)
      ->set('is.slider', $is_slider)
      ->set('is.static', $is_static)
      ->set('is.unblazy', $this->configLoad('io.unblazy'))
      ->set('is.undata', $is_undata)
      ->set('is.unload', $is_unload)
      ->set('is.unloading', $is_unloading)
      ->set('is.resimage', $is_resimage)
      ->set('is.unlazy', $is_unlazy)
      ->set('item.id', $item_id)
      ->set('libs.animate', $fx)
      ->set('libs.background', $is_bg)
      ->set('libs.blur', $is_blur)
      ->set('libs.compat', $is_compat)
      ->set('current_language', $language)
      ->set('fx', $fx)
      ->set('iframe_domain', $iframe_domain)
      ->set('lightbox', $lightbox)
      ->set('lightboxes', $lightboxes)
      ->set('namespace', $namespace)
      ->set('route_name', $route_name)
      ->set('ui', $ui)
      ->set('use.dataset', $is_bg);

    // Allows lightboxes to provide its own optionsets, e.g.: ElevateZoomPlus.
    if ($switch) {
      $settings[$switch] = $feature = empty($settings[$switch]) ? $switch : $settings[$switch];
      if ($lightbox) {
        $blazies->set($feature, $feature);
        $blazies->set('first.lightbox', $feature);
      }
      else {
        // Non-lightboxes: media player, link to content, image rendered, etc.
        $blazies->set('media.switch', $feature);
      }
    }

    $blazies->set('presettings', TRUE);
  }

  /**
   * Modifies the common UI settings inherited down to each item.
   *
   * The `fx` sequence: hook_alter > formatters (not implemented yet) > UI.
   * The `_fx` is a special flag such as to temporarily disable till needed.
   * Called by field formatters, views [styles|fields via BlazyEntity],
   * [blazy|splide|slick] filters.
   */
  public function postSettings(array &$settings = []) {
    // Might be called directly at ::attach().
    if (!isset($settings['blazies'])) {
      $settings += BlazyDefault::htmlSettings();
    }

    $blazies = $settings['blazies'];
    if (!$blazies->is('presettings')) {
      $this->preSettings($settings);
    }

    if (!$blazies->is('postsettings')) {
      // @tod remove settings after sub-modules.
      $has_grid = !empty($settings['grid']);
      $is_grid = $has_grid && !empty($settings['visible_items']);
      $is_grid = $is_grid ?: (!empty($settings['style']) && $has_grid);
      $is_grid = $blazies->is('grid', $settings['_grid'] ?? $is_grid);

      $blazies->set('is.grid', $is_grid);

      // Checks for [Responsive] image styles.
      BlazyImage::styles($settings);

      // Formatters, Views style, not Filters.
      if (!empty($settings['style'])) {
        BlazyGrid::toNativeGrid($settings);
      }

      $blazies->set('postsettings', TRUE);
    }
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
        $lightboxes = [];
        foreach (['colorbox', 'photobox'] as $lightbox) {
          if (function_exists($lightbox . '_theme')) {
            $lightboxes[] = $lightbox;
          }
        }

        $paths = [
          'photobox' => 'photobox/photobox/jquery.photobox.js',
          'mfp' => 'magnific-popup/dist/jquery.magnific-popup.min.js',
        ];

        foreach ($paths as $key => $path) {
          if (is_file($this->root . '/libraries/' . $path)) {
            $lightboxes[] = $key;
          }
        }

        $this->moduleHandler->alter('blazy_lightboxes', $lightboxes);
        $lightboxes = array_unique($lightboxes);
        sort($lightboxes);

        $count = count($lightboxes);
        $tags = Cache::buildTags($cid, ['count:' . $count]);
        $this->cache->set($cid, $lightboxes, Cache::PERMANENT, $tags);

        $this->lightboxes = $lightboxes;
      }
    }
    return $this->lightboxes;
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
    // Retrieves Blazy formatter related settings from within Views style.
    if (!$blazies = $settings['blazies'] ?? NULL) {
      return;
    }

    // 1. Blazy formatter within Views styles by supported modules.
    $blazy   = $data['settings'] ?? [];
    $item_id = $blazies->get('item.id', $settings['item_id'] ?? 'x');
    $content = $data[$item_id] ?? $data;

    // 2. Blazy Views fields by supported modules.
    // Prevents edge case with unexpected flattened Views results which is
    // normally triggered by checking "Use field template" option.
    if (is_array($content) && ($view = ($content['#view'] ?? NULL))) {
      if ($blazy_field = BlazyViews::viewsField($view)) {
        $blazy = $blazy_field->mergedViewsSettings();
        $settings = array_merge(array_filter($blazy), array_filter($settings));
      }
    }

    // Makes this container aware of Blazy formatter it might contain.
    if ($blazy) {
      Blazy::preserve($settings, $blazy);
    }

    $blazies->unset('first.data');
  }

  /**
   * Return the cache metadata common for all blazy-related modules.
   */
  public function getCacheMetadata(array $build = []) {
    $settings = $build['settings'] ?? $build;

    // @todo remnove after sub-modules, including some fallback settings.
    if (!isset($settings['blazies'])) {
      $settings += BlazyDefault::htmlSettings();
    }

    $blazies           = $settings['blazies'];
    $namespace         = $blazies->get('namespace', $settings['namespace'] ?? 'blazy');
    $max_age           = $this->configLoad('cache.page.max_age', 'system.performance');
    $max_age           = empty($settings['cache']) ? $max_age : $settings['cache'];
    $id                = $settings['id'] ?? Blazy::getHtmlId($namespace);
    $id                = $blazies->get('css.id', $id);
    $count             = $settings['count'] ?? count($settings);
    $count             = $blazies->get('count', $count);
    $suffixes[]        = $count;
    $cache['tags']     = Cache::buildTags($namespace . ':' . $id, $suffixes, '.');
    $cache['contexts'] = ['languages'];
    $cache['max-age']  = $max_age;
    $cache['keys']     = $blazies->get('cache.keys', [$id]);

    if ($tags = $blazies->get('cache.tags', $settings['cache_tags'] ?? [])) {
      $cache['tags'] = Cache::mergeTags($cache['tags'], $tags);
    }

    return $cache;
  }

  /**
   * Returns the thumbnail image using theme_image(), or theme_image_style().
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
   * {@inheritdoc}
   */
  public function getRouteName() {
    return Blazy::routeMatch()->getRouteName();
  }

  /**
   * Provides attachments and cache common for all blazy-related modules.
   */
  protected function setAttachments(array &$element, array $settings, array $attachments = []) {
    $cache                = $this->getCacheMetadata($settings);
    $attached             = $this->attach($settings);
    $attachments          = empty($attachments) ? $attached : NestedArray::mergeDeep($attached, $attachments);
    $element['#attached'] = empty($element['#attached']) ? $attachments : NestedArray::mergeDeep($element['#attached'], $attachments);
    $element['#cache']    = empty($element['#cache']) ? $cache : NestedArray::mergeDeep($element['#cache'], $cache);
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
   * Deprecated method.
   *
   * @deprecated in blazy:8.x-2.5 and is removed from blazy:3.0.0. Use
   *   BlazyResponsiveImage::dimensions() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function setResponsiveImageDimensions(array &$settings = [], $initial = TRUE) {
    BlazyResponsiveImage::dimensions($settings, $initial);
  }

  /**
   * Deprecated method.
   *
   * @deprecated in blazy:8.x-2.5 and is removed from blazy:3.0.0. Use
   *   BlazyResponsiveImage::styles() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getResponsiveImageStyles($responsive) {
    return BlazyResponsiveImage::styles($responsive);
  }

  /**
   * Deprecated method.
   *
   * @deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   self::postSettings() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getCommonSettings(array &$settings = []) {
    $this->postSettings($settings);
  }

  /**
   * Deprecated method.
   *
   * @deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyEntity::settings() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getEntitySettings(array &$settings, $entity) {
    BlazyEntity::settings($settings, $entity);
  }

}
