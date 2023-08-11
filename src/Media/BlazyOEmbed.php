<?php

namespace Drupal\blazy\Media;

use Drupal\media\MediaInterface;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Drupal\media\OEmbed\UrlResolverInterface;
use Drupal\blazy\Blazy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides OEmbed integration.
 */
class BlazyOEmbed implements BlazyOEmbedInterface {

  /**
   * Core Media oEmbed url resolver.
   *
   * @var \Drupal\media\OEmbed\UrlResolverInterface
   */
  protected $urlResolver;

  /**
   * Core Media oEmbed resource fetcher.
   *
   * @var \Drupal\media\OEmbed\ResourceFetcherInterface
   */
  protected $resourceFetcher;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\Media\BlazyMediaInterface
   */
  protected $blazyMedia;

  /**
   * The Media oEmbed Resource.
   *
   * @var \Drupal\media\OEmbed\Resource
   */
  protected $resource;

  /**
   * Constructs a Blazy oEmbed object.
   */
  public function __construct(
    BlazyMediaInterface $blazy_media,
    ResourceFetcherInterface $resource_fetcher,
    UrlResolverInterface $url_resolver
  ) {
    $this->blazyMedia = $blazy_media;
    $this->resourceFetcher = $resource_fetcher;
    $this->urlResolver = $url_resolver;
    $this->blazyManager = $blazy_media->manager();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('blazy.media'),
      $container->get('media.oembed.resource_fetcher'),
      $container->get('media.oembed.url_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceFetcher() {
    return $this->resourceFetcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlResolver() {
    return $this->urlResolver;
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
  public function blazyMedia() {
    return $this->blazyMedia;
  }

  /**
   * {@inheritdoc}
   */
  public function getResource($input_url) {
    $resource_url = $this->urlResolver->getResourceUrl($input_url, 0, 0);
    return $this->resourceFetcher->fetchResource($resource_url);
  }

  /**
   * {@inheritdoc}
   */
  public function build(array &$build, $entity = NULL): void {
    // @todo remove old approach at 3.x after old VEF BlazyVideoTrait removed.
    if (isset($build['input_url'])) {
      $this->blazyManager->verifySafely($build);
      $this->toEmbed($build);
      return;
    }

    // Extracts image item from Media, File entity, ER, FieldItemList, etc.
    $build['#entity'] = $build['#entity'] ?? $entity;
    $this->fromMediaOrAny($build);
  }

  /**
   * {@inheritdoc}
   */
  public function checkInputUrl(array &$settings, $input): ?string {
    $blazies = $settings['blazies'];
    $input = Blazy::sanitizeInputUrl($input);
    $blazies->set('media.input_url', $input);
    return $input;
  }

  /**
   * {@inheritdoc}
   */
  public function getThumbnail(array &$settings): ?object {
    $blazies = $settings['blazies'];
    $input   = $blazies->get('media.input_url', $settings['input_url'] ?? NULL);
    $uri     = $blazies->get('image.uri', $settings['uri'] ?? NULL);
    $height  = $blazies->get('image.height');
    $width   = $blazies->get('image.width');
    $label   = $blazies->get('media.label');
    $title   = $blazies->get('image.title') ?: $label;
    $type    = $blazies->get('media.type', $settings['type'] ?? NULL);

    // Failsafe, BlazyFilter/ VEF without file upload [data-entity-uuid].
    try {
      // Iframe URL may be valid, but not stored as a Media entity.
      if ($input && $resource = $this->getResource($input)) {
        // PHP-stan always assumes it an array.
        if (is_object($resource)) {
          $title = $resource->getTitle() ?: $title;

          // VEF has valid URI, other hard-coded unmanaged files might not.
          if (!BlazyFile::isValidUri($uri)) {
            $type = $resource->getType();
            // All we have here is external images. URI validity is not crucial.
            if (!empty($resource->getThumbnailUrl())) {
              $uri = $resource->getThumbnailUrl()->getUri();
            }
          }

          // Respect hard-coded width and height since no UI for all these here.
          if (!$width || !$height) {
            $width = $resource->getThumbnailWidth() ?: $resource->getWidth();
            $height = $resource->getThumbnailHeight() ?: $resource->getHeight();
          }
        }
      }
    }
    catch (\Exception $ignore) {
      // Silently failed likely local works without internet.
    }

    // Redefines for sure.
    $blazies->set('media.input_url', $input)
      ->set('media.label', $title)
      ->set('media.type', $type);

    // VEF has just URI, the rest are fetched from resource.
    $dims = [
      'width'  => $width,
      'height' => $height,
    ];
    $data = [
      'uri'   => $uri,
      'alt'   => $title,
      'title' => $label ?: $title,
    ] + $dims;

    if ($uri) {
      // We are here from BlazyFilter, VEF, or where no File API available.
      $blazies->set('image', $data, TRUE);
      $item = BlazyImage::fakeFromSettings($blazies);
      $blazies->set('image.item', $item)
        ->set('image.original', $dims);

      return $item;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function toEmbedUrl($blazies, $input, array $autoplay = []): string {
    $iframe_domain = $blazies->get('iframe_domain');

    return $this->blazyMedia->toEmbedUrl($input, $iframe_domain, $autoplay);
  }

  /**
   * Temporary method to be compatible with old approach pre 2.10.
   *
   * @todo move it directly into ::build() after sub-modules.
   */
  private function fromMediaOrAny(array &$build): void {
    $this->blazyManager->hashtag($build);

    $access   = $build['#access'] ?? FALSE;
    $entity   = $build['#entity'] ?? NULL;
    $settings = &$build['#settings'];
    $blazies  = $settings['blazies'];
    $valid    = $entity instanceof MediaInterface;
    $stage    = $settings['image'] ?? NULL;
    $media    = $valid ? $entity : NULL;

    // Two designated types of $stage: MediaInterface and FileInterface.
    // Since 2.10, Main stage is usable as the main display of a Paragraphs,
    // only if the stage is a Media entity and Overlay is left empty. Basically
    // render the Media and replace its parent $entity. This way if it is a
    // video, Media switch will kick in as a Media player or simply an iframe.
    // Old behavior is intact if Overlay is provided as previously designed.
    // Before 2.10, the stage was always made an Image, and required Overlay
    // to have a video player or iframe on top of the stage as an Image.
    if (!$valid && $entity && $stage && empty($settings['overlay'])) {
      if ($object = $this->blazyMedia->fromField($entity, $stage)) {
        $media = $object;
        $valid = TRUE;
      }
    }

    // Provides image url earlier for file_video at ::fromMedia to have posters.
    if (!BlazyImage::isValidItem($build)) {
      $entity = $valid ? $media : $entity;
      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $entity */
      if ($item = BlazyImage::fromAny($entity, $settings)) {
        $build['#item'] = $item;
      }
    }

    // Checks for access.
    if (!$access && $denied = $this->blazyManager->denied($entity)) {
      $build['content'][] = $denied;
      return;
    }

    /** @var \Drupal\media\Entity\Media $entity */
    if ($valid) {
      $build['#entity'] = $media;
      $this->fromMedia($build);
    }
    else {
      // Attempts to get image data directly from oEmbed resource.
      // Called by BlazyFilter or deprecated VEF, run after data populated.
      if (!$entity || !$blazies->get('media.embed_url')) {
        $this->toEmbed($settings);
      }
    }

    // Marks a hires if valid and so configured, normally field_media_image.
    if (BlazyImage::isValidItem($build)) {
      $blazies->set('is.hires', !empty($stage));
    }
    else {
      // BlazyFilter/ VEF without file upload [data-entity-uuid], nor File API.
      $build['#item'] = $this->getThumbnail($settings);
    }
  }

  /**
   * Modifies data to provide Media item thumbnail, embed URL, or rich content.
   *
   * @param array $build
   *   The modified array containing: settings, and candidate video thumbnail.
   */
  private function fromMedia(array &$build): void {
    // Prepare Media needed settings, and extract Media thumbnail, except type.
    $media    = $this->blazyMedia->prepare($build);
    $settings = &$build['#settings'];
    $blazies  = $settings['blazies'];
    $input    = $blazies->get('media.value');
    $source   = $blazies->get('media.source');

    // Overrides entity with the translated version.
    $build['#entity'] = $media;

    // Local video/ audio file were fully supported since 2.17.
    // @todo support other media sources: Resource::TYPE_PHOTO,
    // Resource::TYPE_RICH, etc.
    switch ($source) {
      case 'oembed':
      case 'oembed:video':
      case 'video_embed_field':
        // Input url != embed url. For Youtube, /watch != /embed.
        if ($input) {
          $blazies->set('media.input_url', $input);
          $this->toEmbed($settings);
        }
        break;

      case 'image':
      case 'svg':
        // Let's keep it for switch purposes.
        $blazies->set('media.type', 'image');
        break;

      default:
        // Local audio/video has numeric value, skip.
        if ($input && !is_numeric($input)) {
          $blazies->set('media.input_url', $input);
          $this->toEmbed($settings);
        }

        // Supports other Media entities: Facebook, Instagram, local media, etc.
        // Attempts to enter the unknown here fearlessly.
        if ($result = $this->blazyMedia->view($build)) {
          // Update with the processed settings.
          $newbies  = $build['#settings'];
          $settings = $this->blazyManager->mergeSettings('blazies', $settings, $newbies);
          $blazies  = $settings['blazies'];

          $build['#settings'] = $settings;

          // Iframe, like image, can be handled by theme_blazy(). The rest
          // that Blazy doesn't understand should be respected as is as content.
          if (!$blazies->is('iframeable')) {
            $build['content'][] = $result;
          }
        }
        break;
    }
  }

  /**
   * Converts input URL into embed URL, run after ::prepare() populated.
   *
   * @param array $settings
   *   The settings array being modified.
   */
  private function toEmbed(array &$settings): void {
    $blazies = $settings['blazies'];
    $input   = $blazies->get('media.input_url', $settings['input_url'] ?? NULL);
    $switch  = $settings['media_switch'] ?? NULL;

    if (empty($input)) {
      return;
    }

    $input = $this->checkInputUrl($settings, $input);
    $autoplay = $switch ? ['autoplay' => 1] : [];

    // Should be oembed_url, but embed_url is a fine legacy video_embed_field.
    $embed_url = $this->toEmbedUrl($blazies, $input, $autoplay);
    $blazies->set('media.embed_url', $embed_url)
      ->set('media.escaped', TRUE);
  }

  /**
   * Deprecated method ::imageFactory().
   *
   * @deprecated in blazy:8.x-2.6 and is removed from blazy:3.0.0. Use none
   *   instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function imageFactory() {
    @trigger_error('imageFactory is deprecated in blazy:8.x-2.6 and is removed from blazy:3.0.0. Use none instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);
    return Blazy::service('image.factory');
  }

  /**
   * Deprecated method ::getIframeUrlHelper().
   *
   * @deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use none
   *   instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getIframeUrlHelper() {
    @trigger_error('getIframeUrlHelper is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use none instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);
    return Blazy::service('media.oembed.iframe_url_helper');
  }

}
