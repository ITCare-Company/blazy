<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\IFrameUrlHelper;
use Drupal\media\OEmbed\Resource;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Drupal\media\OEmbed\UrlResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides OEmbed integration.
 */
class BlazyOEmbed {

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
   * Returns the Media oEmbed resource fecther.
   */
  public function getResourceFetcher() {
    return $this->resourceFetcher;
  }

  /**
   * Returns the Media oEmbed url resolver fecthers.
   */
  public function getUrlResolver() {
    return $this->urlResolver;
  }

  /**
   * Returns the Media oEmbed url resolver fecthers.
   */
  public function getIframeUrlHelper() {
    return $this->iframeUrlHelper;
  }

  /**
   * Returns the blazy manager.
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * Constructs a BlazyManager object.
   */
  public function __construct(RequestStack $request, ResourceFetcherInterface $resource_fetcher, UrlResolverInterface $url_resolver, IFrameUrlHelper $iframe_url_helper, BlazyManagerInterface $blazy_manager) {
    $this->request = $request;
    $this->resourceFetcher = $resource_fetcher;
    $this->urlResolver = $url_resolver;
    $this->iframeUrlHelper = $iframe_url_helper;
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
      $container->get('blazy.manager')
    );
  }

  /**
   * Returns the oEmbed Resource.
   *
   * @param string $input_url
   *   The video url.
   *
   * @return Drupal\media\OEmbed\Resource
   *   The oEmbed resource.
   */
  public function getResource($input_url) {
    if (!isset($this->resource)) {
      $resource_url = $this->urlResolver->getResourceUrl($input_url, 0, 0);
      $this->resource = $this->resourceFetcher->fetchResource($resource_url);
    }

    return $this->resource;
  }

  /**
   * Builds media-related settings based on the given media url.
   *
   * Need internet, else `Could not retrieve the oEmbed provider database from
   * //oembed.com/providers.json in Drupal\media\OEmbed\ProviderRepository.
   *
   * @param array $settings
   *   The settings array being modified.
   *
   * @return Drupal\media\OEmbed\Resource
   *   The oEmbed resource.
   */
  public function build(array &$settings = []) {
    $resource = NULL;
    try {
      $resource = $this->getResource($settings['input_url']);

      // @todo support other types (link, photo), if reasonable for Blazy.
      if ($resource && ($resource->getType() === Resource::TYPE_VIDEO || $resource->getType() === Resource::TYPE_RICH)) {
        $width = empty($settings['width']) ? $resource->getWidth() : $settings['width'];
        $height = empty($settings['height']) ? $resource->getHeight() : $settings['height'];
        $url = Url::fromRoute('media.oembed_iframe', [], [
          'query' => [
            'url' => $settings['input_url'],
            'max_width' => $width,
            'max_height' => $height,
            'hash' => $this->iframeUrlHelper->getHash($settings['input_url'], $width, $height),
            'blazy' => 1,
            'autoplay' => empty($settings['media_switch']) ? 0 : 1,
          ],
        ]);

        $this->buildUrl($settings, $url, $resource);
      }
    }
    catch (\Exception $e) {
      // Silently do nothing, likely local work without internet.
    }

    return $resource;
  }

  /**
   * Returns the oEmbed top level iframe url.
   *
   * @param array $settings
   *   The settings array being modified.
   * @param Drupal\Core\Url $url
   *   A video URL.
   * @param Drupal\media\OEmbed\Resource $resource
   *   The oEmbed resource.
   */
  public function buildUrl(array &$settings, Url $url, Resource $resource) {
    if ($domain = $this->blazyManager->configLoad('iframe_domain', 'media.settings')) {
      $url->setOption('base_url', $domain);
    }

    // The top level iframe url relative to the current site, or iframe_domain.
    $settings['embed_url'] = $url->toString();

    // Extracts the actual video url from html, and provides autoplay url.
    $settings = array_merge($settings, $this->getAutoPlayUrl($resource));

    // Only applies when Image style is empty, no file API, no $item,
    // with unmanaged VEF/ WYSIWG/ filter image without image_style.
    // Prevents 404 warning when video thumbnail missing for a reason.
    if (empty($settings['image_style']) && !empty($settings['uri'])) {
      if ($data = @getimagesize($settings['uri'])) {
        list($settings['width'], $settings['height']) = $data;
      }
    }
  }

  /**
   * Provides the autoplay url suitable for lightboxes, or custom video trigger.
   *
   * @param Drupal\media\OEmbed\Resource $resource
   *   The oEmbed resource.
   *
   * @return array
   *   The settings array.
   */
  public function getAutoPlayUrl(Resource $resource) {
    $data = [];
    if (empty($resource->getHtml())) {
      return $data;
    }

    $dom = Html::load($resource->getHtml());
    $url = $dom->getElementsByTagName('iframe')->item(0)->getAttribute('src');

    if (!empty($url)) {
      $data['oembed_url'] = $url;
      $data['scheme']     = mb_strtolower($resource->getProvider()->getName());
      $data['type']       = $resource->getType();

      // @todo local uri without file API, such as ckeditor iframe + media.
      // This points to external youtube thumbnail, not local.
      if (empty($settings['image_url'])) {
        $settings['image_url'] = $resource->getThumbnailUrl()->getUri();
      }

      // Adds autoplay for media URL on lightboxes, saving another click.
      if (strpos($url, 'autoplay') === FALSE || strpos($url, 'autoplay=0') !== FALSE) {
        $data['autoplay_url'] = strpos($url, '?') === FALSE ? $url . '?autoplay=1' : $url . '&autoplay=1';
      }
    }

    return $data;
  }

  /**
   * Gets the Media item thumbnail, or re-associate the file entity to ME.
   *
   * @param array $data
   *   The modified array containing settings, and to be video thumbnail item.
   * @param object $media
   *   The core Media entity.
   */
  public function getMediaItem(array &$data = [], $media = NULL) {
    // Only proceed if we do have Media.
    if ($media->getEntityTypeId() != 'media') {
      return;
    }

    $item     = NULL;
    $settings = $data['settings'];

    $settings['bundle']       = $media->bundle();
    $settings['source_field'] = $media->getSource()->getConfiguration()['source_field'];
    $settings['media_url']    = $media->url();
    $settings['media_id']     = $media->id();
    $settings['view_mode']    = empty($settings['view_mode']) ? 'default' : $settings['view_mode'];

    // If Media has a defined thumbnail, add it to data item, not all has this.
    if (isset($media->getFields()['thumbnail'])) {
      /** @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      $item = $media->get('thumbnail')->first();
      $settings['file_tags'] = ['file:' . $item->target_id];

      // Provides thumbnail URI for EB selection with various Media entities.
      if (empty($settings['uri'])) {
        try {
          // Without internet, this screwed up the site.
          $settings['uri'] = $media->getSource()->getMetadata($media, 'thumbnail_uri');
        }
        catch (\Exception $ignore) {
          $settings['uri'] = File::load($item->target_id)->getFileUri();
        }
      }

      if (!empty($settings['uri'])) {
        $settings['image_url'] = file_url_transform_relative(file_create_url($settings['uri']));
      }
    }

    if ($settings['source_field'] && isset($media->{$settings['source_field']})) {
      // @todo support local video/ audio file.
      switch ($media->getSource()->getPluginId()) {
        case 'file':
        case 'audio_file':
        case 'video_file':
          // @todo or not @todo. @tobe or not @tobe. @o...bedo...bedo.
          break;

        case 'oembed':
        case 'oembed:video':
          // Input URL != embed url. For Youtube, /watch != /embed.
          $input_url = $media->getSource()->getSourceFieldValue($media);
          $input_url = trim(strip_tags($input_url));
          if ($input_url) {
            $settings['input_url'] = $input_url;

            $this->build($settings);
          }
          break;

        case 'image':
          $settings['type'] = 'image';
          break;

        default:
          break;
      }

      // Do not proceed if it has type, already managed by theme_blazy().
      // Supports other Media entities: Facebook, Instagram, Twitter, etc.
      // @todo recheck against core Media with Resource::TYPE_RICH.
      $content = [];
      if (empty($settings['type']) && ($build = BlazyMedia::build($media, $settings))) {
        $content[] = $build;
      }
    }

    // Collect what's needed for clarity.
    $data['item'] = $item;
    $data['settings'] = $settings;
    $data['content'] = $content;
  }

  /**
   * Overrides variables for media-oembed-iframe.html.twig templates.
   */
  public function preprocessMediaOembedIframe(array &$variables) {
    // Without internet, this may be empty, bail out.
    if (empty($variables['media'])) {
      return;
    }

    // Only needed to autoplay video, and make responsive iframe.
    try {
      // Blazy formatters with oEmbed provide contextual params to the query.
      $request = $this->request->getCurrentRequest();
      $is_blazy = $request->query->getInt('blazy', NULL);
      $is_autoplay = $request->query->getInt('autoplay', NULL);
      $url = $request->query->get('url');

      // Only replace url if it is required by Blazy.
      if ($url && $is_blazy == 1) {
        // Load iframe string as a DOMDocument as alternative to regex.
        $dom = Html::load($variables['media']);
        $iframe = $dom->getElementsByTagName('iframe')->item(0);
        $resource = $this->getResource($url);

        // Fetches autoplay_url.
        $settings = $this->getAutoPlayUrl($resource);

        // Replace old oEmbed url with autoplay support, and save the DOM.
        if ($iframe) {
          // Only replace if autoplay == 1 for Image to iframe, or lightboxes.
          if ($is_autoplay == 1 && !empty($settings['autoplay_url'])) {
            $iframe->setAttribute('src', $settings['autoplay_url']);
          }

          // Make responsive iframe with/ without autoplay.
          $iframe->setAttribute('width', '100%');
          $iframe->setAttribute('height', '100%');
          $iframe->setAttribute('style', 'display: block; max-width: 100%; overflow: hidden; width: 100%; height: 100vh;');
          $variables['media'] = $dom->saveHTML();
        }
      }
    }
    catch (\Exception $e) {
      // Silently do nothing, likely local work without internet.
    }
  }

}
