<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\Element;
use Drupal\blazy\Media\BlazyResponsiveImage;
use Drupal\blazy\Media\BlazyMedia;
use Drupal\blazy\Media\BlazyOEmbedInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides common entity utilities to work with field details.
 */
class BlazyEntity implements BlazyEntityInterface {

  /**
   * The blazy oembed service.
   *
   * @var object
   */
  protected $oembed;

  /**
   * The blazy manager service.
   *
   * @var object
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
   * Returns the blazy oembed service.
   */
  public function oembed() {
    return $this->oembed;
  }

  /**
   * Returns the blazy manager service.
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * {@inheritdoc}
   */
  public function build(array &$data, $entity, $fallback = '') {
    if (!$entity instanceof EntityInterface) {
      return [];
    }

    // Supports core Media via Drupal\blazy\Media\BlazyOEmbed::build().
    $manager = $this->blazyManager;
    $settings = &$data['settings'];

    // Common settings.
    $manager->preSettings($settings);
    $manager->prepareData($data, $entity);
    $manager->postSettings($settings);

    // Entity settings.
    self::settings($settings, $entity);

    // Build the Media item.
    $this->oembed->build($data, $entity);

    $settings = &$data['settings'];
    $blazies = $settings['blazies'];

    // Made Responsive image also available outside formatters here.
    if ($blazies->get('resimage.style')) {
      BlazyResponsiveImage::dimensionsAndSources($settings, FALSE);
    }

    // Only pass to Blazy for known entities related to File or Media.
    if (in_array($entity->getEntityTypeId(), ['file', 'media'])) {
      /** @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      if (empty($data['item'])) {
        $data['content'][] = $this->getEntityView($entity, $settings, $fallback);
      }

      // Pass it to Blazy for consistent markups.
      $build = $manager->getBlazy($data);

      // Allows top level elements to load Blazy once rather than per field.
      // This is still here for non-supported Views style plugins, etc.
      if (empty($settings['_detached'])) {
        $load = $manager->attach($settings);
        $build['#attached'] = empty($build['#attached']) ? $load : NestedArray::mergeDeep($build['#attached'], $load);
      }
    }
    else {
      $build = $this->getEntityView($entity, $settings, $fallback);
    }

    $manager->getModuleHandler()->alter('blazy_build_entity', $build, $entity, $settings);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityView($entity, array $settings = [], $fallback = '') {
    if ($entity instanceof EntityInterface) {
      $manager        = $this->blazyManager;
      $entity_type_id = $entity->getEntityTypeId();
      $view_mode      = $settings['view_mode'] = empty($settings['view_mode']) ? 'default' : $settings['view_mode'];
      $langcode       = $entity->language()->getId();
      $fallback       = $fallback && is_string($fallback) ? ['#markup' => '<div class="is-fallback">' . $fallback . '</div>'] : $fallback;

      // If entity has view_builder handler.
      if ($manager->getEntityTypeManager()->hasHandler($entity_type_id, 'view_builder')) {
        $build = $manager->getEntityTypeManager()->getViewBuilder($entity_type_id)->view($entity, $view_mode, $langcode);

        // @todo figure out why video_file empty, this is blatant assumption.
        if ($entity_type_id == 'file') {
          try {
            $build = $this->getFileOrMedia($entity, $settings) ?: $build;
          }
          catch (\Exception $ignore) {
            // Do nothing, no need to be chatty in mischievous deeds.
          }
        }
        return $build ?: $fallback;
      }
      else {
        // If module implements own {entity_type}_view.
        // @todo remove due to being deprecated at D8.7.
        // See https://www.drupal.org/node/3033656
        $view_hook = $entity_type_id . '_view';
        if (is_callable($view_hook)) {
          return $view_hook($entity, $view_mode, $langcode);
        }
      }
    }
    return $fallback;
  }

  /**
   * Returns file view or media due to being empty returned by view builder.
   *
   * @todo make it usable for other file-related entities.
   */
  public function getFileOrMedia($file, array $settings, $rendered = TRUE) {
    $blazies = $settings['blazies'];
    [$type] = explode('/', $file->getMimeType(), 2);

    if ($type == 'video') {
      // As long as you are not being too creative by renaming or changing
      // fields provided by core, this should be your good friend.
      $blazies->set('media.source', 'video_file');
      $blazies->set('media.source_field', 'field_media_video_file');
    }

    $source_field = $blazies->get('media.source_field');
    if ($blazies->get('media.source')
      && $source_field
      && $media = $this->blazyManager->loadByProperties([
        $source_field => ['fid' => $file->id()],
      ], 'media')) {
      if ($media = reset($media)) {
        return $rendered ? BlazyMedia::build($media, $settings) : $media;
      }
    }
    return [];
  }

  /**
   * Returns the string value of the fields: link, or text.
   */
  public function getFieldValue($entity, $field_name, $langcode) {
    if ($entity->hasField($field_name)) {
      $entity = Blazy::translated($entity, $langcode);

      // Entity doesn't have translation, fetch original value.
      return $entity->get($field_name)->getValue();
    }
    return NULL;
  }

  /**
   * Returns the string value of the fields: link, or text.
   */
  public function getFieldString($entity, $field_name, $langcode, $clean = TRUE) {
    if ($entity->hasField($field_name)) {
      $values = $this->getFieldValue($entity, $field_name, $langcode);

      // Can be text, or link field.
      $string = $values[0]['uri'] ?? ($values[0]['value'] ?? '');

      if ($string && is_string($string)) {
        $string = $clean ? strip_tags($string, '<a><strong><em><span><small>') : Xss::filter($string, BlazyDefault::TAGS);
        return trim($string);
      }
    }
    return '';
  }

  /**
   * Returns the formatted renderable array of the field.
   */
  public function getFieldRenderable($entity, $field_name, $view_mode, $multiple = TRUE) {
    if ($entity->hasField($field_name)) {
      $view = $entity->get($field_name)->view($view_mode);

      if (empty($view[0])) {
        return [];
      }

      // Prevents quickedit to operate here as otherwise JS error.
      // @see 2314185, 2284917, 2160321.
      // @see quickedit_preprocess_field().
      // @todo Remove when it respects plugin annotation.
      $view['#view_mode'] = '_custom';
      $weight = $view['#weight'] ?? 0;

      // Intentionally clean markups as this is not meant for vanilla.
      if ($multiple) {
        $items = [];
        foreach (Element::children($view) as $key) {
          $items[$key] = $view[$key];
        }

        $items['#weight'] = $weight;
        return $items;
      }
      return $view[0];
    }

    return [];
  }

  /**
   * Returns the text or link value of the fields: link, or text.
   */
  public function getFieldTextOrLink($entity, $field_name, $settings, $multiple = TRUE) {
    if ($entity->hasField($field_name)) {
      $blazies  = $settings['blazies'];
      $langcode = $settings['langcode'] ?? '';
      $langcode = $blazies->get('current_language', $langcode);

      if ($text = $this->getFieldValue($entity, $field_name, $langcode)) {
        if (!empty($text[0]['value']) && !isset($text[0]['uri'])) {
          // Prevents HTML-filter-enabled text from having bad markups (h2 > p),
          // except for a few reasonable tags acceptable within H2 tag.
          $text = $this->getFieldString($entity, $field_name, $langcode, FALSE);
        }
        elseif (isset($text[0]['uri']) && !empty($text[0]['title'])) {
          $text = $this->getFieldRenderable($entity, $field_name, $settings['view_mode'], $multiple);
        }

        // Prevents HTML-filter-enabled text from having bad markups
        // (h2 > p), save for few reasonable tags acceptable within H2 tag.
        return is_string($text)
          ? ['#markup' => strip_tags($text, '<a><strong><em><span><small>')]
          : $text;
      }
    }
    return [];
  }

  /**
   * Modifies the common settings extracted from the given entity.
   */
  public static function settings(array &$settings, $entity) {
    $blazies = $settings['blazies'];
    $internal_path = $absolute_path = NULL;
    $langcode = $blazies->get('current_language');

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
      'type_id' => $entity->getEntityTypeId(),
      'url' => $absolute_path,
      'path' => $internal_path,
    ];

    $blazies->set('entity', $info);

    $settings['bundle'] = $entity->bundle();

    // @todo remove after migration and sub-modules.
    // foreach ($info as $key => $value) {
    // $key = $key == 'url' ? 'content_' . $key : $key;
    // $key = in_array($key, ['id', 'type_id']) ? 'entity_' . $key : $key;
    // $settings[$key] = $value;
    // }
  }

}
