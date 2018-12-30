<?php

namespace Drupal\blazy\Dejavu;

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\OEmbed\Resource;
use Drupal\blazy\BlazyMedia;

/**
 * A Trait common for optional Media Entity and Video Embed Media integration.
 *
 * The basic idea is to display videos along with images even within core Image,
 * and to re-associate VEF/ME video thumbnails beyond their own entity display.
 * For editors, use Slick Browser, or Blazy Views field.
 * For client-side, Blazy Views field, BlazyFileFormatter, SlickFileFormatter.
 * Why bother? This addresses a mix of images/videos beyond field formatters
 * or when ME and VEM integration is optional.
 *
 * For more robust VEM/ME integration, use Slick Media instead.
 *
 * @see Drupal\blazy\Plugin\views\field\BlazyViewsFieldPluginBase
 * @see Drupal\slick_browser\SlickBrowser::widgetEntityBrowserFileFormAlter()
 * @see Drupal\slick_browser\Plugin\EntityBrowser\FieldWidgetDisplay\...
 */
trait BlazyVideoTrait {

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
   * Core Media oEmbed url resolver.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory = NULL;

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
   * Returns the image factory.
   */
  public function imageFactory() {
    if (is_null($this->imageFactory)) {
      $this->imageFactory = \Drupal::service('image.factory');
    }
    return $this->imageFactory;
  }

  /**
   * Builds relevant video embed field settings based on the given media url.
   *
   * Need internet, else `Could not retrieve the oEmbed provider database from
   * //oembed.com/providers.json in Drupal\media\OEmbed\ProviderRepository.
   *
   * @param array $settings
   *   The settings array being modified.
   * @param string $external_url
   *   A video url.
   *
   * @return Drupal\media\OEmbed\Resource
   *   The oEmbed resource.
   */
  public function buildOembed(array &$settings = [], $external_url = '') {
    $resource = NULL;
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
   *   The resource fetcher service.
   */
  public function buildOembedUrl(array &$settings, Url $url, Resource $resource) {
    if ($domain = $this->blazyManager()->configLoad('iframe_domain', 'media.settings')) {
      $url->setOption('base_url', $domain);
    }

    // The top level iframe url relative to the current site.
    $settings['embed_url'] = $url->toString();
    $settings['scheme'] = mb_strtolower($resource->getProvider()->getName());

    if (!empty($resource->getHtml()) && strpos($resource->getHtml(), 'src') !== FALSE) {
      $dom = new \DOMDocument();
      libxml_use_internal_errors(TRUE);
      $dom->loadHTML($resource->getHtml());
      // The oEmbed url may be empty without internet connection.
      $settings['oembed_url'] = $dom->getElementsByTagName('iframe')->item(0)->getAttribute('src');
      $this->getAutoPlayUrl($settings);
    }

    $settings['type'] = $resource->getType();

    // Only applies when Image style is empty, no file API, no $item,
    // with unmanaged VEF image without image_style.
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
   * @param array $settings
   *   The settings array being modified.
   */
  public function getAutoPlayUrl(array &$settings = []) {
    // The oEmbed url may be empty without internet connection.
    if (!empty($settings['oembed_url'])) {
      $url = $settings['oembed_url'];
      // Adds autoplay for media URL on lightboxes, saving another click.
      if (strpos($url, 'play') === FALSE || strpos($url, 'autoplay=0') !== FALSE) {
        $autoplay = strpos($url, '?') === FALSE ? $url . '?autoplay=1' : $url . '&autoplay=1';
        if ($settings['scheme'] == 'vimeo') {
          $autoplay = strpos($url, '?') === FALSE ? $url . '?auto_play=1' : $url . '&auto_play=1';
        }
        $settings['autoplay_url'] = $autoplay;
      }
    }
  }

  /**
   * Gets the Media item thumbnail, or re-associate the file entity to ME.
   *
   * @param array $data
   *   An array of data containing settings, and potential video thumbnail item.
   * @param object $media
   *   The core Media entity.
   *
   * @todo remove VEF $media->getType() prior to Blazy 8.2.x release.
   */
  public function getMediaItem(array &$data = [], $media = NULL) {
    $settings = $data['settings'];

    // Only proceed if we do have ME.
    if ($media->getEntityTypeId() != 'media') {
      return;
    }

    $bundle = $media->bundle();
    $fields = $media->getFields();
    $config = method_exists($media, 'getSource') ? $media->getSource()->getConfiguration() : $media->getType()->getConfiguration();
    $source = isset($config['source_url_field']) ? $config['source_url_field'] : '';

    $source_field[$bundle]    = isset($config['source_field']) ? $config['source_field'] : $source;
    $settings['bundle']       = $bundle;
    $settings['source_field'] = $source_field[$bundle];
    $settings['media_url']    = $media->url();
    $settings['media_id']     = $media->id();
    $settings['view_mode']    = empty($settings['view_mode']) ? 'default' : $settings['view_mode'];

    // If Media entity has a defined thumbnail, add it to data item.
    if (isset($fields['thumbnail'])) {
      /** @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      $data['item'] = $media->get('thumbnail')->first();
      $settings['file_tags'] = ['file:' . $data['item']->target_id];

      // Provides thumbnail URI for EB selection with various Media entities.
      if (empty($settings['uri'])) {
        try {
          // Without internet, this screwed up the site.
          $settings['uri'] = $media->getSource()->getMetadata($media, 'thumbnail_uri');
        }
        catch (\Exception $ignore) {
          $settings['uri'] = File::load($data['item']->target_id)->getFileUri();
        }
      }
    }

    $source = empty($settings['source_field']) ? '' : $settings['source_field'];
    if ($source && isset($media->{$source})) {
      $value = $media->{$source}->getValue();

      // Input URL != embed url. For Youtube, /watch != /embed.
      $input_url = $media->getSource()->getSourceFieldValue($media);
      $input_url = strip_tags($input_url);
      if ($input_url) {
        $settings['input_url'] = $input_url;

        // Soundcloud has different source_field name: source_url_field.
        if (strpos($input_url, 'soundcloud') === FALSE) {
          $this->buildVideo($settings, $input_url);
        }
      }
      elseif (isset($value[0]['alt']) || is_null($value[0]['alt'])) {
        $settings['type'] = 'image';
      }

      // Do not proceed if it has type, already managed by theme_blazy().
      // Supports other Media entities: Facebook, Instagram, Twitter, etc.
      if (empty($settings['type'])) {
        if ($build = BlazyMedia::build($media, $settings)) {
          $data['content'][] = $build;
        }
      }
    }

    $data['settings'] = $settings;
  }

  /**
   * Gets the faked image item out of file entity, or ER, if applicable.
   *
   * @param object $file
   *   The expected file entity, or ER, to get image item from.
   *
   * @return array
   *   The array of image item and settings if a file image, else empty.
   */
  public function getImageItem($file) {
    $data = [];
    $entity = $file;

    /** @var Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $file */
    if (isset($file->entity) && !isset($file->alt)) {
      $entity = $file->entity;
    }

    if (!$entity instanceof File) {
      return $data;
    }

    /** @var \Drupal\file\Entity\File $entity */
    list($type,) = explode('/', $entity->getMimeType(), 2);
    $uri = $entity->getFileUri();

    if ($type == 'image' && ($image = $this->imageFactory()->get($uri)) && $image->isValid()) {
      $item            = new \stdClass();
      $item->target_id = $entity->id();
      $item->width     = $image->getWidth();
      $item->height    = $image->getHeight();
      $item->alt       = $entity->getFilename();
      $item->title     = $entity->getFilename();
      $item->uri       = $uri;
      $settings        = (array) $item;
      $item->entity    = $entity;

      // Build item and settings.
      $settings['type'] = 'image';
      $settings['uri']  = $uri;
      $data['item']     = $item;
      $data['settings'] = $settings;
      unset($item);
    }

    return $data;
  }

  /**
   * Builds relevant video embed field settings based on the given media url.
   *
   * @param array $settings
   *   An array of settings to be passed into theme_blazy().
   * @param string $external_url
   *   A video URL.
   *
   * @deprecated for Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatterBase::buildOembed().
   * @todo remove prior to Blazy 8.2.x full release. This is still kept to
   * allow changing from video_embed_field into media field without breaking it,
   * and to allow transition from blazy-related modules to depend on media.
   */
  public function buildVideo(array &$settings = [], $external_url = '') {
    // If this file is imported without DI, allows a fallback to not break it.
    // This is to allow transition before Blazy plugins migrate to core Media.
    if (is_null($this->mediaResourceFetcher)) {
      return NULL;
    }
    return $this->buildOembed($settings, $external_url);
  }

}
