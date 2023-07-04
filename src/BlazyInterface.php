<?php

namespace Drupal\blazy;

/**
 * Provides common blazy utility methods.
 */
interface BlazyInterface {

  /**
   * Returns the app root.
   *
   * @return string
   *   The app root.
   */
  public function root();

  /**
   * Returns the entity repository service.
   *
   * @return \Drupal\Core\Entity\EntityRepositoryInterface
   *   The entity repository.
   */
  public function entityRepository();

  /**
   * Returns the entity type manager service.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function entityTypeManager();

  /**
   * Returns the module handler service.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler.
   */
  public function moduleHandler();

  /**
   * Returns the renderer service.
   *
   * @return \Drupal\Core\Render\RendererInterface
   *   The renderer.
   */
  public function renderer();

  /**
   * Returns the config factory service.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory.
   */
  public function configFactory();

  /**
   * Returns the cache service.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   The app root.
   */
  public function cache();

  /**
   * Returns the language manager service.
   *
   * @return \Drupal\Core\Language\LanguageManager
   *   The language manager.
   */
  public function languageManager();

  /**
   * Returns any config, or keyed by the $setting_name.
   *
   * @param string $key
   *   The setting key.
   * @param string $group
   *   The settings object group key.
   *
   * @return mixed
   *   The config value(s), or empty.
   */
  public function config($key = NULL, $group = 'blazy.settings');

  /**
   * Returns any config by the $group, alternative to ugly NULL key.
   *
   * @param string $group
   *   The settings object group key.
   *
   * @return array
   *   The config values, or empty array.
   */
  public function configMultiple($group = 'blazy.settings'): array;

  /**
   * Implements hook_config_schema_info_alter().
   */
  public function configSchemaInfoAlter(
    array &$definitions,
    $formatter = 'blazy_base',
    array $settings = []
  ): void;

  /**
   * Alias for Blazy::denied() for sub-modules.
   *
   * @param object $entity
   *   The expected entity interface object to check for its view access.
   *
   * @return array
   *   The renderable array of the minimal denial info, or empty if accessible.
   */
  public function denied($entity): array;

  /**
   * Returns the entity query object for this entity type.
   *
   * @param string $type
   *   The entity type.
   * @param string $conjunction
   *   The operator for the query.
   *
   * @return object
   *   The entity query object.
   */
  public function entityQuery($type, $conjunction = 'AND');

  /**
   * Returns cached data identified by its cache ID, normally alterable data.
   *
   * @param string $cid
   *   The cache ID, als used for the hook_alter.
   * @param array $data
   *   The given data to cache, accepting empty array to trigger hook_alter.
   * @param array $info
   *   The optional info containing:
   *   - reset: Whether to bypass cache.
   *   - alter: key for the hook_alter, otherwise $cid.
   *   - context: additional data to be altered or as contextual info.
   *
   * @return array
   *   The cache data.
   */
  public function getCachedData(
    $cid,
    array $data = [],
    array $info = []
  ): array;

  /**
   * Returns cached options identified by its cache ID, normally alterable data.
   *
   * @param string $cid
   *   The cache ID, als used for the hook_alter.
   * @param array $data
   *   The given data to cache, accepting empty array to trigger hook_alter.
   * @param bool $as_options
   *   Whether to use it for select options.
   * @param array $info
   *   The optional info containing:
   *   - reset: Whether to bypass cache,
   *   - alter: key for the hook_alter, otherwise $cid.
   *   - context: additional data to be altered or as contextual info.
   *
   * @return array
   *   The cache data/ options.
   */
  public function getCachedOptions(
    $cid,
    array $data = [],
    $as_options = TRUE,
    array $info = []
  ): array;

  /**
   * Returns the cache metadata common for all blazy-related modules.
   *
   * @param array $build
   *   The provided build info.
   *
   * @return array
   *   The cache metadata.
   */
  public function getCacheMetadata(array $build);

  /**
   * Returns available entities for select options.
   *
   * To get all entities of an entity_type, use self::loadMultiple() instead.
   *
   * @param string $entity_type
   *   The entity type.
   *
   * @return array
   *   The entity types
   */
  public function getEntityAsOptions($entity_type): array;

  /**
   * Alias for Blazy::getHtmlId() to get the trusted HTML ID.
   *
   * @param string $name
   *   The module name.
   * @param string $id
   *   The optional hardcoded ID.
   *
   * @return string
   *   The static CSS ID.
   */
  public function getHtmlId($name = 'blazy', $id = ''): string;

  /**
   * Alias for Blazy::getLibrariesPath() to get libraries path.
   *
   * A few libraries have inconsistent namings, given different packagers:
   *   - splide x splidejs--splide
   *   - slick x slick-carousel
   *   - DOMPurify x dompurify, etc.
   *
   * @param array|string $name
   *   The library name(s), e.g.: 'colorbox', or ['DOMPurify', 'dompurify'].
   * @param bool $base_path
   *   Whether to prefix it with an a base path, deprecated.
   *
   * @return string|null
   *   The path to the library, or NULL if not found.
   */
  public function getLibrariesPath($name, $base_path = FALSE): ?string;

  /**
   * Alias for Blazy::getPath() to get module or theme path.
   *
   * @param string $type
   *   The object type, can be module or theme.
   * @param string $name
   *   The object name.
   * @param bool $absolute
   *   Whether to return an absolute path.
   *
   * @return string|null
   *   The path to object, or NULL if not found.
   */
  public function getPath($type, $name, $absolute = FALSE): ?string;

  /**
   * Returns a shortcut for entity type storage.
   *
   * @param string $type
   *   The entity type.
   *
   * @return object|null
   *   The entity type storage object.
   */
  public function getStorage($type = 'media');

  /**
   * Returns a shortcut for loading an entity: image_style, slick, etc.
   *
   * @param string $id
   *   The entity ID.
   * @param string $type
   *   The entity type, can be configuration object like blazy.settings.
   *
   * @return mixed
   *   The entity, or config values: string, bool, etc.
   */
  public function load($id, $type = 'image_style');

  /**
   * Returns a shortcut for loading multiple entities.
   *
   * @param string $type
   *   The entity type.
   * @param array|string $ids
   *   The entity ID(s) as fiters.
   *
   * @return array
   *   The entities, or empty array.
   */
  public function loadMultiple($type = 'image_style', $ids = NULL): array;

  /**
   * Returns a shortcut for loading entity by its properties.
   *
   * The only difference from EntityStorageBase::loadByProperties() is the
   * explicit access TRUE specific for content entities, FALSE config ones.
   *
   * @see https://www.drupal.org/node/3201242
   */
  public function loadByProperties(
    array $values,
    $type = 'file',
    $access = TRUE,
    $conjunction = 'AND',
    $condition = 'IN'
  ): array;

  /**
   * Returns a shortcut for loading entity by its UUID.
   *
   * @param string $uuid
   *   The entity UUID.
   * @param string $type
   *   The entity type.
   *
   * @return object|null
   *   The entity, else NULL.
   */
  public function loadByUuid($uuid, $type = 'file'): ?object;

  /**
   * Provides a shortcut to parse the markdown string for better hook_help().
   */
  public function markdown($string, $help = TRUE): string;

  /**
   * Merge data with a new one with an optional key.
   */
  public function merge(array $data, array $element, $key = NULL): array;

  /**
   * A wrapper for \Drupal\Core\Extension\ModuleHandlerInterface::moduleExists.
   *
   * @param string $name
   *   The module name.
   *
   * @return bool
   *   Whether the module exists, or not.
   */
  public function moduleExists($name): bool;

  /**
   * A wrapper for Blazy::service()
   *
   * @param string $name
   *   The service name.
   *
   * @return object|null
   *   The service if already initialized, or NULL.
   */
  public function service($name): ?object;

  /**
   * Returns items wrapped by theme_item_list(), can be a grid, or plain list.
   *
   * Alias for Blazy::grid() for sub-modules and easy organization later.
   *
   * @param array $items
   *   The grid items.
   * @param array $settings
   *   The given settings.
   *
   * @return array
   *   The modified array of grid items.
   */
  public function toGrid(array $items, array $settings): array;

  /**
   * Returns escaped options.
   *
   * @param array $options
   *   The given options.
   *
   * @return array
   *   The modified array of options suitable for select options.
   */
  public function toOptions(array $options): array;

  /**
   * A wrapper for the entity view with access check.
   *
   * @param array $data
   *   The data containing: entity, settings, and fallback.
   *
   * @return array
   *   The renderable array of the view builder, or empty if not applicable.
   *
   * @see https://www.drupal.org/node/3033656
   */
  public function view(array $data): array;

}
