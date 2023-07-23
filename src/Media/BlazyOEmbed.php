<?php

namespace Drupal\blazy\Media;

// @todo revert use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\Core\Image\ImageFactory;
use Drupal\media\IFrameUrlHelper;
use Drupal\media\MediaInterface;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Drupal\media\OEmbed\UrlResolverInterface;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyManager;
use Symfony\Component\HttpFoundation\RequestStack;
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
   * Core Media oEmbed iframe url helper.
   *
   * @var \Drupal\media\IFrameUrlHelper
   */
  protected $iframeUrlHelper;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * The Media oEmbed Resource.
   *
   * @var \Drupal\media\OEmbed\Resource
   */
  protected $resource;

  /**
   * The request service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The image factory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Constructs a BlazyManager object.
   *
   * @todo remove ::imageFactory (was for UGC), not used anywhere since 2.6.
   */
  public function __construct(
    RequestStack $request,
    ResourceFetcherInterface $resource_fetcher,
    UrlResolverInterface $url_resolver,
    IFrameUrlHelper $iframe_url_helper,
    ImageFactory $image_factory,
    BlazyManager $blazy_manager
  ) {
    $this->request = $request;
    $this->resourceFetcher = $resource_fetcher;
    $this->urlResolver = $url_resolver;
    $this->iframeUrlHelper = $iframe_url_helper;
    // @todo remove before 3.x, no longer in use.
    $this->imageFactory = $image_factory;
    $this->blazyManager = $blazy_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('media.oembed.resource_fetcher'),
      $container->get('media.oembed.url_resolver'),
      $container->get('media.oembed.iframe_url_helper'),
      $container->get('image.factory'),
      $container->get('blazy.manager')
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
  public function getIframeUrlHelper() {
    return $this->iframeUrlHelper;
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
      Blazy::verify($build);
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
    if ($input) {
      // OEmbed Resource doesn't accept `/embed`, provides a conversion helper,
      // normally seen at BlazyFilter with youtube embed copy/paste, without
      // creating media entities.
      if (strpos($input, 'youtube.com/embed') !== FALSE) {
        $search = '/youtube\.com\/embed\/([a-zA-Z0-9]+)/smi';
        $replace = "youtube.com/watch?v=$1";
        $input = preg_replace($search, $replace, $input);
      }
    }
    // @todo recheck if any side effect/ double escape to cdn/ valid input.
    $input = UrlHelper::stripDangerousProtocols($input);
    $blazies->set('media.input_url', $input);
    return $input;
  }

  /**
   * {@inheritdoc}
   */
  public function toEmbedUrl($blazies, $input, array $autoplay = []): string {
    $query = [
      'url' => $input,
      'max_width' => 0,
      'max_height' => 0,
      'hash' => $this->iframeUrlHelper->getHash($input, 0, 0),
      'blazy' => 1,
    ] + $autoplay;

    // @todo revisit if any issue with other resource types.
    $url = Url::fromRoute('media.oembed_iframe', [], [
      'query' => $query,
    ]);

    // The top level iframe url relative to the site, or iframe_domain.
    if ($iframe_domain = $blazies->get('iframe_domain')) {
      $url->setOption('base_url', $iframe_domain);
    }

    return $url->toString();
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
      if ($object = BlazyMedia::fromField($entity, $stage)) {
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
      // Failsafe, BlazyFilter/ VEF without file upload [data-entity-uuid].
      try {
        $build['#item'] = $this->getExternalImageItem($settings);
      }
      catch (\Exception $ignore) {
        // Silently failed likely local works without internet.
      }
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
    $media    = BlazyMedia::prepare($build);
    $settings = &$build['#settings'];
    $blazies  = $settings['blazies'];
    $input    = $blazies->get('media.value');
    $source   = $blazies->get('media.source');

    // @todo support local video/ audio file, and other media sources.
    // @todo check for Resource::TYPE_PHOTO, Resource::TYPE_RICH, etc.
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
        $blazies->set('media.type', 'image');
        break;

      default:
        // Local audio/video has numeric value, skip.
        if ($input && !is_numeric($input)) {
          $blazies->set('media.input_url', $input);
          $this->toEmbed($settings);
        }

        // Supports other Media entities: Facebook, Instagram, local video, etc.
        // Attempts to enter the unknown here fearlessly.
        if ($result = BlazyMedia::view($media, $settings)) {
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
   * Returns external image item from resource for BlazyFilter or VEF.
   *
   * The settings fallbacks are preserved for minimal BVEF compat.
   */
  private function getExternalImageItem(array &$settings): ?object {
    $blazies = $settings['blazies'];
    $input   = $blazies->get('media.input_url', $settings['input_url'] ?? NULL);
    $uri     = $blazies->get('image.uri', $settings['uri'] ?? NULL);
    $height  = $blazies->get('image.height');
    $width   = $blazies->get('image.width');
    $label   = $blazies->get('media.label');
    $title   = $blazies->get('image.title') ?: $label;
    $type    = $blazies->get('media.type', $settings['type'] ?? NULL);

    // Iframe URL may be valid, but not stored as a Media entity.
    if ($input && $resource = $this->getResource($input)) {
      // PHP-stan always assumes it an array.
      if (is_object($resource)) {
        $title = $resource->getTitle() ?: $title;

        // VEF has valid local URI, other hard-coded unmanaged files might not.
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

    $blazies->set('media.label', $title)
      ->set('media.type', $type);

    // VEF has just URI, the rest are fetched from resource.
    $data = [
      'uri'    => $uri,
      'width'  => $width,
      'height' => $height,
      'alt'    => $title,
      'title'  => $label ?: $title,
    ];

    if ($uri) {
      $blazies->set('image', $data, TRUE);
      return BlazyImage::fakeFromSettings($blazies);
    }

    return NULL;
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
   * Returns the image factory.
   *
   * @todo remove ::imageFactory (was for UGC), not used anywhere since 2.6.
   */
  public function imageFactory() {
    return $this->imageFactory;
  }

}
