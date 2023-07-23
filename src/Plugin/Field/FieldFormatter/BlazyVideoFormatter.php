<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\blazy\Dejavu\BlazyVideoBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyVideoFormatter is deprecated in blazy:8.x-2.0 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatter instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);

/**
 * Plugin implementation of the 'Blazy Video' to get VEF videos.
 *
 * This file is no longer used nor needed, and will be removed at 3.x.
 * VEF will continue working via BlazyOEmbed instead.
 *
 * BVEF can take over this file to be compat with Blazy 3.x rather than keeping
 * 1.x debris. Also to adopt core OEmbed security features at ease.
 *
 * @todo remove prior to full release. This means Slick Video which depends
 * on VEF is deprecated for main Slick at Blazy 8.2.x with core Media only.
 * @todo make is useful for local video instead?
 */
class BlazyVideoFormatter extends BlazyVideoBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    return self::injectServices($instance, $container, 'entity');
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Early opt-out if the field is empty.
    if ($items->isEmpty()) {
      return [];
    }

    return $this->commonViewElements($items, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->getType() === 'video_embed_field';
  }

  /**
   * Build the blazy elements.
   */
  protected function buildElements(array &$build, $items, $langcode) {
    $settings = $this->formatter->toHashtag($build);
    $blazies  = $settings['blazies'];
    $entity   = $items->getEntity();

    if (!($vef = $this->vefProviderManager())) {
      return;
    }

    // Update the settings, hard-coded, terracota.
    $blazies->set('media.bundle', 'remote_video')
      ->set('media.source', 'video_embed_field');

    foreach ($items as $delta => $item) {
      $input = strip_tags($item->value ?: '');

      if (empty($input)
        || !($provider = $vef->loadProviderFromInput($input))) {
        continue;
      }

      // Ensures thumbnail is available.
      $provider->downloadThumbnail();
      $uri = $provider->getLocalThumbnailUri();

      $sets = $settings;
      $blazy = $sets['blazies']->reset($sets);
      $blazy->set('delta', $delta)
        ->set('image.uri', $uri)
        ->set('media.input_url', $input)
        ->set('media.type', 'video');

      /*
      // Too risky, but if you got lucky.
      // if ($medias = $this->blazyManager->loadByProperties([
      // 'field_media_oembed_video.value' => $input,
      // ], 'media', TRUE)) {
      // if ($media = reset($medias)) {
      // $entity = $media;
      // }
      // }
       */
      $data = [
        '#entity' => $entity,
        '#settings' => $sets,
        '#item' => NULL,
      ];

      $this->blazyOembed->build($data);

      // Image with responsive image, lazyLoad, and lightbox supports.
      $build[$delta] = $this->formatter->getBlazy($data);
      unset($data);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    return [
      'fieldable_form' => TRUE,
      'multimedia'     => TRUE,
    ] + parent::getPluginScopes();
  }

}
