<?php

namespace Drupal\blazy\Utility;

use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazyEntity;
use Drupal\blazy\Media\Preloader;
use Drupal\blazy\Theme\BlazyViews;
use Drupal\blazy\Theme\Grid;
use Drupal\blazy\Theme\Lightbox;

/**
 * Provides feature check methods.
 */
class Check {

  /**
   * The AMP page.
   *
   * @var bool
   */
  private static $isAmp;

  /**
   * The preview mode to disable Blazy where JS is not available, or useless.
   *
   * @var bool
   */
  private static $isPreview;

  /**
   * The preview mode to disable interactive elements.
   *
   * @var bool
   */
  private static $isSandboxed;

  /**
   * Modifies asset attachments.
   *
   * @todo move it out of here for all attachments, what folder, Asset?
   */
  public static function attachments(array &$load, array &$attach = []): void {
    Blazy::postSettings($attach);

    $manager = Blazy::service('blazy.manager');
    $blazies = $attach['blazies'];
    $unblazy = $blazies->is('unblazy', FALSE);
    $unload  = $blazies->get('ui.nojs.lazy', FALSE);

    if ($blazies->is('lightbox')) {
      Lightbox::attach($load, $attach);
    }

    // Always keep Drupal UI config to support dynamic compat features.
    $config = $manager->configLoad('blazy');
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
    $load['drupalSettings']['blazyIo'] = $manager->getIoSettings($attach);

    foreach (BlazyDefault::components() as $component) {
      $key = str_replace('.', '_', $component);
      if ($blazies->get('libs.' . $key, FALSE)) {
        $load['library'][] = 'blazy/' . $component;
      }
    }

    // Adds AJAX helper to revalidate Blazy/ IO, if using VIS, or alike.
    // @todo remove when VIS detaches behaviors properly like IO.
    if ($blazies->get('use.ajax', FALSE)) {
      $load['library'][] = 'blazy/bio.ajax';
    }

    // Preload.
    if (!empty($attach['preload'])) {
      Preloader::preload($load, $attach);
    }
  }

  /**
   * Checks for global libraries.
   */
  public static function basics(array &$settings = []): void {
    $blazies      = $settings['blazies'];
    $ui           = $blazies->get('ui');
    $namespace    = $blazies->get('namespace', $settings['namespace'] ?? 'blazy');
    $item_id      = $blazies->get('item.id', $settings['item_id'] ?? 'blazy');
    $_loading     = $settings['loading'] ?? '';
    $loading      = $settings['loading'] = $_loading ?: 'lazy';
    $bundle       = $blazies->get('media.bundle', $settings['bundle'] ?? '');
    $is_preview   = $settings['is_preview'] = self::isPreview();
    $is_amp       = self::isAmp();
    $is_sandboxed = self::isSandboxed();
    $is_bg        = !empty($settings['background']);
    $is_unload    = !empty($ui['nojs']['lazy']);
    $is_slider    = $loading == 'slider';
    $is_unloading = $loading == 'unlazy';
    $is_defer     = $loading == 'defer';
    $is_fluid     = ($settings['ratio'] ?? '') == 'fluid';
    $is_static    = $is_preview || $is_amp || $is_sandboxed;
    $is_undata    = $is_static || $is_unloading;
    $is_nojs      = $is_unload || $is_undata;
    $is_video     = $bundle == 'video'
      || in_array('video', $blazies->get('bundles', []));

    // When `defer` is chosen, overrides global `No JavaScript: lazy`, ensures
    // to not affect AMP, CKEditor, or other preview pages where nojs is a must.
    if ($is_nojs && $is_defer) {
      $is_nojs = $is_undata;
    }

    // Compat is anything that Native lazy doesn't support.
    $is_compat = $is_bg
      || $is_fluid
      || $is_video
      || $is_defer
      || $blazies->get('fx')
      || $blazies->get('libs.compat');

    // Some should be refined per item against potential mixed media items.
    // @todo move some into Blazy::prepare() as might be called per item.
    $blazies->set('is.amp', $is_amp)
      ->set('is.bg', $is_bg)
      ->set('is.fluid', $is_fluid)
      ->set('is.nojs', $is_nojs)
      ->set('is.preview', $is_preview)
      ->set('is.sandboxed', $is_sandboxed)
      ->set('is.slider', $is_slider)
      ->set('is.static', $is_static)
      ->set('is.undata', $is_undata)
      ->set('is.unload', $is_unload)
      ->set('is.unloading', $is_unloading)
      ->set('item.id', $item_id)
      ->set('libs.background', $is_bg)
      ->set('libs.compat', $is_compat)
      ->set('libs.ratio', !empty($settings['ratio']))
      ->set('namespace', $namespace)
      ->set('use.dataset', $is_bg || $is_video);
  }

  /**
   * Checks for Blazy formatter such as from within a Views style plugin.
   *
   * @see \Drupal\blazy\Blazy::preserve()
   * @see \Drupal\blazy\BlazyManagerInterface::isBlazy()
   */
  public static function blazyOrNot(array &$settings, array $data = []): void {
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

    // No longer needed once extracted above, remove.
    $blazies->unset('first.data');
  }

  /**
   * Checks for field formatter settings.
   *
   * @todo remove fallback settings after migration and sub-modules.
   */
  public static function fields(array &$build, $items): void {
    $settings = &$build['settings'];
    $entity   = $items->getEntity();

    BlazyEntity::settings($settings, $entity);

    $blazies        = $settings['blazies'];
    $field          = $items->getFieldDefinition();
    $field_name     = $field->getName();
    $count          = $items->count();
    $field_clean    = str_replace("field_", '', $field_name);
    $entity_type_id = $blazies->get('entity.type_id');
    $entity_id      = $blazies->get('entity.id');
    $bundle         = $blazies->get('entity.bundle');
    $view_mode      = $blazies->get('field.view_mode', 'default');
    $namespace      = $blazies->get('namespace');
    $id             = $settings['id'] ?? '';
    $gallery_id     = "{$namespace}-{$entity_type_id}-{$bundle}-{$field_clean}-{$view_mode}";
    $id             = Blazy::getHtmlId("{$gallery_id}-{$entity_id}", $id);
    $switch         = $settings['media_switch'] ?? '';

    // When alignment is mismatched, split them to satisfy linter.
    // Respects linked_field.module expectation.
    $linked    = $blazies->get('field.third_party.linked_field.linked');
    $use_field = !$blazies->is('lightbox') && $linked;

    if ($switch && $blazies->is('lightbox')) {
      $gallery_id = str_replace('_', '-', $gallery_id . '-' . $switch);
      $blazies->set('lightbox.gallery_id', $gallery_id);
    }

    $blazies->set('count', $count)
      ->set('css.id', $id)
      ->set('use.theme_field', $use_field || !empty($settings['use_theme_field']));

    $blazies->set('cache.keys', [$id, $count], TRUE);
    $blazies->set('cache.tags', [$entity_type_id . ':' . $entity_id], TRUE);

    // @todo remove.
    $settings['count'] = $count;
    $settings['id'] = $id;
  }

  /**
   * Checks for grids.
   */
  public static function grids(array &$settings = []): void {
    $blazies  = $settings['blazies'];
    $has_grid = !empty($settings['grid']);
    $is_grid  = $has_grid && !empty($settings['visible_items']);
    $style    = $settings['style'] ?? NULL;
    $style    = $style ?: ($is_grid ? 'grid' : NULL);
    $is_grid  = $is_grid ?: ($style && $has_grid);
    $is_grid  = $blazies->is('grid', $settings['_grid'] ?? $is_grid);

    $blazies->set('is.grid', $is_grid);

    if ($is_grid && $style) {
      foreach (BlazyDefault::grids() as $grid) {
        if ($style == $grid) {
          $blazies->set('libs.' . $style, $grid);
        }
      }

      // Formatters, Views style, not Filters.
      Grid::toNativeGrid($settings);
    }
  }

  /**
   * Checks if Blazy is in CKEditor preview mode where no JS assets are loaded.
   */
  public static function isPreview(): bool {
    if (!isset(static::$isPreview)) {
      static::$isPreview = self::isAmp() || self::isSandboxed();
    }
    return static::$isPreview;
  }

  /**
   * Checks if Blazy is in AMP pages.
   */
  public static function isAmp(): bool {
    if (!isset(static::$isAmp)) {
      $stack = Blazy::requestStack();
      static::$isAmp = $stack && $stack->getCurrentRequest()->query->get('amp');
    }
    return static::$isAmp;
  }

  /**
   * In CKEditor without JS assets, interactive elements must be sandboxed.
   */
  public static function isSandboxed(): bool {
    if (!isset(static::$isSandboxed)) {
      $check = FALSE;
      if ($router = Blazy::routeMatch()) {
        $route = $router->getRouteName();

        // @todo remove after regression fixes, or keep it due to thumbnail sizes.
        $edits = ['entity_browser.', 'edit_form', 'add_form', '.preview'];
        foreach ($edits as $key) {
          if ($route && mb_strpos($route, $key) !== FALSE) {
            $check = TRUE;
            break;
          }
        }
      }

      static::$isSandboxed = $check;
    }
    return static::$isSandboxed;
  }

  /**
   * Checks lazy insanity given various features/ media types + loading option.
   *
   * To address mixed media, and various options which also affect individual
   * items, see self::prepare().
   */
  public static function lazyOrNot(array &$settings): void {
    $blazies = $settings['blazies'];

    // Lazy load types: blazy, and slick: ondemand, anticipated, progressive.
    $is_blazy = $blazies->is('blazy', !empty($settings['blazy']));
    $is_blazy = $is_blazy || $blazies->is('bg') || $blazies->get('resimage.id');
    $lazy = $is_blazy ? 'blazy' : $settings['lazy'] ?? 'blazy';
    $lazy = $blazies->get('lazy.id', $lazy ?: 'blazy');
    $lazy = $blazies->is('nojs') ? '' : $lazy;

    // @todo re-check after sub-modules which were only aware of `is_preview`.
    // Basically tricking overrides by the reversed name due to sub-modules are
    // not updated to the new options `No JavaScript` + `Loading priority`, yet.
    // As known, Splide/ Slick have their own lazy, but might break till further
    // updates. Choosing Blazy as their lazyload method is the solution to be
    // compatible with the mentioned options. Better than sacrificing Native.
    $is_unlazy = empty($lazy);

    $blazies->set('is.blazy', $is_blazy)
      ->set('is.unlazy', $is_unlazy)
      ->set('lazy.id', $lazy)
      ->set('lazy.attribute', $settings['lazy_attribute'] ?? 'src')
      ->set('lazy.class', $settings['lazy_class'] ?? 'b-lazy');
  }

  /**
   * Checks for lightboxes.
   */
  public static function lightboxes(array &$settings = []): void {
    $blazies    = $settings['blazies'];
    $switch     = $settings['media_switch'] ?? '';
    $lightboxes = $blazies->get('lightbox.plugins', []);
    $lightbox   = ($switch && in_array($switch, $lightboxes)) ? $switch : FALSE;
    $optionset  = '';
    $_richbox   = $blazies->is('richbox', !empty($settings['_richbox']));
    $richbox    = $blazies->get('colorbox') || $blazies->get('mfp') || $_richbox;

    // Allows lightboxes to provide its own optionsets, e.g.: ElevateZoomPlus.
    if ($switch) {
      $optionset = empty($settings[$switch]) ? $switch : $settings[$switch];

      // Lightbox is unique, safe to reserve top level key:
      if ($lightbox) {
        // @todo remove settings after migration and sub-modules.
        $settings[$switch] = $optionset;

        // With an optionset: `elevetazoomplus:responsive`.
        // Without an optionset: `colorbox:colorbox`, etc.
        $blazies->set($switch, $optionset)
          ->set('lightbox.name', $lightbox)
          ->set('lightbox.optionset', $optionset);
      }
    }

    // (Non-)lightboxes: media player, link to content, image rendered, etc.
    $blazies->set('switch', $switch);
    $blazies->set('libs.media', $switch == 'media');

    // @todo remove settings after migration and sub-modules.
    $settings['lightbox'] = $lightbox;
    $blazies->set('is.lightbox', !empty($lightbox))
      ->set('is.richbox', $richbox);
  }

}
