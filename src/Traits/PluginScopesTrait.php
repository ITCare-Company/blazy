<?php

namespace Drupal\blazy\Traits;

use Drupal\blazy\Blazy;
use Drupal\blazy\BlazySettings;

/**
 * A Trait for plugins, common for Blazy, Splide, Slick, etc.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 */
trait PluginScopesTrait {

  /**
   * The form element scopes.
   *
   * @var array
   */
  protected $scopes = [];

  /**
   * Converts old plugin scopes array into BlazySettings object to interop.
   */
  protected function toPluginScopes(array $scopes = []): BlazySettings {
    $definitions = [];

    if (empty($scopes)) {
      return new BlazySettings($definitions);
    }

    // Allows to merge at admin level for consistent sane method uses.
    if (isset($scopes['scopes'])) {
      $this->scopes = $scopes['scopes']->storage();
      unset($scopes['scopes']);
    }

    if ($this->scopes) {
      $this->scopes = Blazy::merge($scopes, $this->scopes);
    }
    else {
      $this->scopes = $scopes;
    }

    // Excludes unique keys out of scopes at admin form level.
    foreach (['blazies', 'settings'] as $key) {
      if (isset($this->scopes[$key])) {
        unset($this->scopes[$key]);
      }
    }

    foreach ($this->scopes as $key => $value) {
      if (is_array($value)) {
        // Do not put duplicate keys into $data, already processed.
        if (in_array($key, ['data', 'form', 'use'])) {
          continue;
        }

        $data[$key] = $value;

        if (isset($this->scopes['data'])) {
          $definitions['data'] = Blazy::merge($data, $this->scopes['data']);
        }
        else {
          $definitions['data'] = $data;
        }
      }
      else {
        if (is_bool($value)) {
          $group = strpos($key, '_form') === FALSE ? 'is' : 'form';
          $key = str_replace('_form', '', $key);
          $definitions[$group][$key] = $value;
        }
        else {
          if (strpos($key, 'field_') !== FALSE) {
            $key = str_replace('field_', '', $key);
            $definitions['field'][$key] = $value;
          }
          elseif (strpos($key, 'entity_') !== FALSE) {
            $key = str_replace('entity_', '', $key);
            $definitions['entity'][$key] = $value;
          }
          else {
            $definitions[$key] = $value;
          }
        }
      }
    }
    return new BlazySettings($definitions);
  }

  /**
   * Modifies the specific plugin settings.
   */
  protected function pluginSettings(&$blazies, array &$settings): void {
    if ($settings['namespace'] == 'blazy') {
      $id = 'blazy';

      $blazies->set('item.id', $id)
        ->set('is.blazy', TRUE)
        ->set('lazy.id', $id)
        ->set('namespace', $id);
    }
  }

}
