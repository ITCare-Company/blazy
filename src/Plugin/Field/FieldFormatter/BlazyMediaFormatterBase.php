<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Url;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\media\IFrameUrlHelper;
use Drupal\media\OEmbed\Resource;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Drupal\media\OEmbed\UrlResolverInterface;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazyFormatterManager;
use Drupal\blazy\BlazyGrid;
use Drupal\blazy\Dejavu\BlazyVideoTrait;
use Drupal\blazy\Dejavu\BlazyEntityReferenceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for blazy/slick media ER formatters.
 *
 * @see Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatter.
 */
abstract class BlazyMediaFormatterBase extends BlazyEntityReferenceBase implements ContainerFactoryPluginInterface {

  use BlazyFormatterBaseTrait;
  use BlazyVideoTrait;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Core Media oEmbed url resolver.
   *
   * @var \Drupal\media\OEmbed\UrlResolverInterface
   */
  protected $mediaUrlResolver;

  /**
   * Core Media oEmbed resource fetcher.
   *
   * @var \Drupal\media\OEmbed\ResourceFetcherInterface
   */
  protected $mediaResourceFetcher;

  /**
   * Core Media oEmbed iframe url helper.
   *
   * @var \Drupal\media\IFrameUrlHelper
   */
  protected $mediaIframeUrlHelper;

  /**
   * Constructs a BlazyFormatter object.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    BlazyFormatterManager $blazy_manager,
    LoggerChannelFactoryInterface $logger_factory,
    ResourceFetcherInterface $resource_fetcher,
    UrlResolverInterface $url_resolver,
    IFrameUrlHelper $iframe_url_helper) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->blazyManager = $blazy_manager;
    $this->loggerFactory = $logger_factory;
    $this->mediaResourceFetcher = $resource_fetcher;
    $this->mediaUrlResolver = $url_resolver;
    $this->mediaIframeUrlHelper = $iframe_url_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('blazy.formatter.manager'),
      $container->get('logger.factory'),
      $container->get('media.oembed.resource_fetcher'),
      $container->get('media.oembed.url_resolver'),
      $container->get('media.oembed.iframe_url_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return BlazyDefault::extendedSettings() + BlazyDefault::gridSettings();
  }

  /**
   * Returns the overridable blazy field formatter service.
   */
  public function formatter() {
    return $this->blazyManager;
  }

  /**
   * Returns the overridable blazy manager service.
   */
  public function manager() {
    return $this->blazyManager;
  }

  /**
   * Returns the Media oEmbed resource fecther.
   */
  public function getMediaResourceFetcher() {
    return $this->mediaResourceFetcher;
  }

  /**
   * Returns the Media oEmbed url resolver fecthers.
   */
  public function getMediaUrlResolver() {
    return $this->mediaUrlResolver;
  }

  /**
   * Returns the Media oEmbed url resolver fecthers.
   */
  public function getMediaIframeUrlHelper() {
    return $this->mediaIframeUrlHelper;
  }

  /**
   * Builds relevant video embed field settings based on the given media url.
   *
   * @param array $settings
   *   The settings array being modified.
   * @param string $external_url
   *   A video url.
   */
  public function buildOembed(array &$settings = [], $external_url = '') {
    try {
      $resource_url = $this->mediaUrlResolver->getResourceUrl($external_url, 0, 0);
      $resource = $this->mediaResourceFetcher->fetchResource($resource_url);

      // @todo support other types (link, photo), if reasonable for Blazy.
      if ($resource->getType() === Resource::TYPE_VIDEO || $resource->getType() === Resource::TYPE_RICH) {
        $width = empty($settings['width']) ? $resource->getWidth() : $settings['width'];
        $height = empty($settings['height']) ? $resource->getHeight() : $settings['height'];
        $url = Url::fromRoute('media.oembed_iframe', [], [
          'query' => [
            'url' => $external_url,
            'max_width' => $width,
            'max_height' => $height,
            'hash' => $this->mediaIframeUrlHelper->getHash($external_url, $width, $height),
          ],
        ]);

        $this->buildOembedUrl($settings, $url, $resource);
      }
    }
    catch (\Exception $e) {
      // Silently do nothing, likely local work without internet.
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $media = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($media)) {
      return [];
    }

    // Collects specific settings to this formatter.
    $settings              = $this->buildSettings();
    $settings['blazy']     = TRUE;
    $settings['namespace'] = $settings['item_id'] = $settings['lazy'] = 'blazy';
    $settings['_grid']     = !empty($settings['style']) && !empty($settings['grid']);

    // Sets dimensions once to reduce method ::transformDimensions() calls.
    // @todo: A more flexible way to also support paragraphs at one go.
    $media = array_values($media);
    if (!empty($settings['image_style']) && ($media[0]->getEntityTypeId() == 'media')) {
      $fields = $media[0]->getFields();

      if (isset($fields['thumbnail'])) {
        $item             = $fields['thumbnail']->get(0);
        $settings['item'] = $item;
        $settings['uri']  = $item->entity->getFileUri();
      }
    }

    // Build the settings.
    $build = ['settings' => $settings];

    // Modifies settings.
    $this->blazyManager->buildSettings($build, $items);

    // Build the elements.
    $this->buildElements($build, $media, $langcode);

    // Updates settings.
    $settings = $build['settings'];
    unset($build['settings']);

    // With pass by reference, we hardly modify base classes, just re-arrange.
    // As opposed to file/ image formatters with direct indices, blazy-formatted
    // entities are stored within `items` with extra usages like thumbnail navs.
    if (empty($settings['_grid'])) {
      $build = $build['items'];
      $build['#blazy'] = $settings;
    }
    else {
      // Build grid if provided.
      $build = BlazyGrid::build($build['items'], $settings);
      unset($build['items']);
    }

    $build['#attached'] = $this->blazyManager->attach($settings);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildElement(array &$build, $entity, $langcode) {
    parent::buildElement($build, $entity, $langcode);

    $settings = $build['settings'];
    $delta = isset($settings['delta']) ? $settings['delta'] : 0;
    $element = $build['items'][$delta];
    $item_id = $settings['item_id'] = empty($settings['item_id']) ? 'box' : $settings['item_id'];

    // Blazy can just collect items directly without further themeing.
    if (!empty($element[$item_id])) {
      $build['items'][$delta] = $element[$item_id];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getScopedFormElements() {
    $field = $this->fieldDefinition;
    $multiple = $field->getFieldStorageDefinition()->isMultiple();

    return [
      'fieldable_form' => FALSE,
      'grid_form' => $multiple,
      'layouts' => [],
      'settings' => $this->buildSettings(),
      'style' => $multiple,
      'vanilla' => FALSE,
    ] + parent::getScopedFormElements();
  }

}
