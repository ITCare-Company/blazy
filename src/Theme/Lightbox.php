<?php

namespace Drupal\blazy\Theme;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Utility\Xss;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Utility\Sanitize;

/**
 * Provides lightbox utilities.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class Lightbox {

  /**
   * Provides lightbox libraries.
   */
  public static function attach(array &$load, array &$attach = []): void {
    $blazies = $attach['blazies'];

    if ($name = $blazies->get('lightbox.name')) {
      $load['library'][] = 'blazy/lightbox';

      // Built-in lightboxes.
      if ($name == 'colorbox') {
        self::attachColorbox($load);
      }
      foreach (['colorbox', 'mfp', 'photobox'] as $key) {
        if ($name == $key) {
          $blazies->set('libs.' . $key, TRUE);
        }
      }
    }
  }

  /**
   * Gets media switch elements: all lightboxes, not content, nor iframe.
   *
   * @param array $element
   *   The element being modified.
   */
  public static function build(array &$element = []): void {
    $manager    = Blazy::service('blazy.manager');
    $item       = $element['#item'];
    $settings   = &$element['#settings'];
    $blazies    = $settings['blazies'];
    $uri        = $blazies->get('image.uri') ?: $settings['uri'] ?? '';
    $switch     = $blazies->get('lightbox.name');
    $switch_css = str_replace('_', '-', $switch);
    $valid      = BlazyFile::isValidUri($uri);
    $_box_style = $settings['box_style'] ?? NULL;
    $box_style  = $blazies->get('box.style');
    $box_url    = $url = Blazy::transformRelative($uri);
    $colorbox   = $blazies->get('colorbox');
    $gallery_id = $blazies->get('lightbox.gallery_id');
    $box_id     = $blazies->is('gallery') ? $gallery_id : NULL;
    $box_width  = $blazies->get('image.original.width') ?: $item->width ?? NULL;
    $box_height = $blazies->get('image.original.height') ?: $item->height ?? NULL;
    $count      = $blazies->get('count', 1);
    $delta      = $blazies->get('delta', 0);
    $multimedia = $blazies->is('multimedia') ?: $blazies->is('local_media');
    $svg        = $blazies->is('unstyled');
    $styleable  = $valid && !$svg;
    $_escaped   = $blazies->get('media.escaped');
    $_fullsize  = $_box_style && $styleable;
    $_resimage  = FALSE;

    // Provide relevant URL if it is a lightbox.
    $url_attributes = &$element['#url_attributes'];
    $url_attributes['class'][] = 'blazy__' . $switch_css . ' litebox';
    $url_attributes['data-' . $switch_css . '-trigger'] = TRUE;

    $dimensions = [
      'width' => $box_width,
      'height' => $box_height,
      'uri' => $uri,
    ];

    // Might not be present from BlazyFilter.
    $json = ['id' => $switch_css, 'count' => $count, 'boxType' => 'image'];
    foreach (['bundle', 'type'] as $key) {
      if ($value = $blazies->get('media.' . $key)) {
        $json[$key] = $value;
      }
    }

    // If multimendia with remote or local videos.
    if ($multimedia) {
      $box_width = 640;
      $box_height = 360;

      if ($embed = $blazies->get('media.embed_url')) {
        // Force autoplay for media URL on lightboxes, saving another click.
        // BC for non-oembed such as Video Embed Field without Media migration.
        $url = Blazy::autoplay($embed, !$_escaped);
        $url_attributes['data-oembed-url'] = $url;
        $json['boxType'] = 'iframe';
      }

      // This allows PhotoSwipe with videos still swipable.
      if ($styleable && $box_media_style = $blazies->get('box_media.style')) {
        $dimensions = array_merge(
          $dimensions,
          BlazyImage::transformDimensions($box_media_style, $dimensions)
        );

        $box_url = Blazy::transformRelative($uri, $box_media_style);
        $box_width = $dimensions['width'] ?: $box_width;
        $box_height = $dimensions['height'] ?: $box_height;

        $blazies->set('lightbox.media_preview_url', $box_url);
        $data_box_url = TRUE;
      }

      if ($blazies->get('photobox')) {
        $url_attributes['rel'] = 'video';
      }
    }
    else {
      // Supports local and remote videos, also legacy VEF which has no bundles.
      // See https://drupal.org/node/3210636#comment-14097266.
      // If image with valid URI, box image style, and not SVG, APNG, etc.
      // The lightbox full sized image can be plain or responsive images.
      if ($_fullsize) {
        // Use responsive image if so-configured, unless rich content is given.
        if ($blazies->is('resimage') && empty($element['#lightbox_html'])) {
          $options = [
            'uri' => $uri,
            'box_style' => $_box_style,
          ];
          $_resimage = self::responsiveImage($element, $options, $manager);
        }

        // Use non-responsive image if so-configured.
        if (!$_resimage && $box_style) {
          $dimensions = array_merge($dimensions, BlazyImage::transformDimensions($box_style, $dimensions));
          $box_url = $url = Blazy::transformRelative($uri, $box_style);
        }
      }
    }

    // Can be original, or styled dimensions.
    $box_width = $dimensions['width'];
    $box_height = $dimensions['height'];
    $box_url = UrlHelper::stripDangerousProtocols($box_url);

    // Only needed by videos, the rest can just use $url set into HREF.
    if (isset($data_box_url)) {
      $url_attributes['data-box-url'] = $box_url;
    }

    // @todo remove after sub-modules.
    $settings['box_url'] = $box_url;
    $blazies->set('lightbox.url', $box_url)
      ->set('lightbox.width', (int) $box_width)
      ->set('lightbox.height', (int) $box_height);

    // @todo recheck $count given views gallery vs formatters vs formatters
    // inside views gallery, and add: && $count > 1.
    if ($box_id) {
      // Adds persistent delta, help fix for slide clones which screw up deltas.
      // This is useless for views gallery, though.
      $url_attributes['data-b-delta'] = $delta;

      // @todo make Blazy Grid without Blazy Views fields support multiple
      // fields and entities as a gallery group, likely via a class at Views UI.
      // Must use consistent key for multiple entities, hence cannot use id.
      // We do not have option for this like colorbox, as it is only limited
      // to the known Blazy formatters, or Blazy Views style plugins for now.
      // The hustle is Colorbox wants rel on individual item to group, unlike
      // other lightbox library which provides a way to just use a container.
      if ($colorbox) {
        $json['rel'] = $box_id;
      }
    }

    // Provides the content and its attributes.
    $options = [
      'url' => $url,
      'item' => $item,
      'box_width' => $box_width,
      'box_height' => $box_height,
      '_escaped' => $_escaped,
      '_resimage' => $_resimage,
    ];

    self::content(
      $element,
      $json,
      $url_attributes,
      $options,
      $settings,
      $manager
    );
  }

  /**
   * Attaches Colorbox if so configured.
   */
  private static function attachColorbox(array &$load): void {
    if ($service = Blazy::service('colorbox.attachment')) {
      $dummy = [];
      $service->attach($dummy);

      $load = Blazy::merge($load, $dummy, '#attached');

      unset($dummy);
    }
  }

  /**
   * Provides html content for lightboxes.
   */
  private static function content(
    array &$element,
    array &$json,
    array &$url_attributes,
    array $options,
    array $settings,
    $manager
  ): void {
    [
      'url' => $url,
      'item' => $item,
      'box_width' => $box_width,
      'box_height' => $box_height,
      '_escaped' => $_escaped,
      '_resimage' => $_resimage,
    ] = $options;

    $blazies = $settings['blazies'];

    // Do not output NULL dimensions.
    $has_dim = !empty($box_width) && !empty($box_height);
    // (Responsive) image, local video or iframe must have dimensions.
    if ($has_dim) {
      $json['width'] = (int) $box_width;
      $json['height'] = (int) $box_height;
    }

    // Currently: Responsive/Picture image, not plain, and Local video.
    if ($box_html = ($element['#lightbox_html'] ?? [])) {
      // Local video ($html) is wrapped, but not Responsive image ($box_html).
      // Reasons: video displayed as is, image is disassembled for zoom, etc.,
      // or just dumped as is, depending on the supportive lightbox capability.
      $html = [
        '#theme' => 'container',
        '#children' => $box_html,
        '#attributes' => [
          // @todo make it flexible for regular non-media HTML.
          'class' => ['media', 'media--ratio'],
        ],
      ];

      // Only video needs help, responsive image is taken care of by lightbox.
      $style = '';
      if ($has_dim) {
        $pad = round((($json['height'] / $json['width']) * 100), 2);
        $style = 'width:' . $json['width'] . 'px; padding-bottom: ' . $pad . '%;';
        $html['#attributes']['style'] = $style;
      }

      // Responsive image is unwrapped. Local videos wrapped.
      $content = $_resimage ? $box_html : $html;
      $content = trim($manager->renderer()->renderPlain($content));

      // @todo merge with BlazyDefault::TAGS when mixed contents supported.
      // Lightbox Responsive|Picture image will be broken when filtered out.
      $content = $_resimage ? $content : Xss::filter($content, BlazyDefault::MEDIA_TAGS);

      // See https://www.drupal.org/project/drupal/issues/3109650.
      $unstrips = [
        'prestyle' => 'ratio"',
        'style' => $style,
      ];

      $json['html'] = Sanitize::unstrip($content, $unstrips);

      if ($_resimage) {
        $json['type'] = 'rich';
        $json['boxType'] = strpos($content, '<picture') !== FALSE
          ? 'picture' : 'responsiveImage';
      }
      else {
        if (strpos($content, '<video') !== FALSE) {
          $json['type'] = 'rich';
          $json['boxType'] = 'video';
        }
      }

      unset($element['#lightbox_html']);
    }

    // Provides captions if so configured.
    if (!empty($settings['box_caption'])) {
      $element['#captions']['lightbox'] = self::buildCaptions($item, $settings);
    }

    // Do not show icon for local video file unless supported.
    $is_local = $blazies->is('local_media');
    $show_icon = !$is_local || $is_local && $blazies->is('richbox');
    if ($show_icon) {
      $icon = '<span class="media__icon media__icon--litebox"></span>';
      $element['#icon']['lightbox']['#markup'] = $icon;
    }

    // @todo re-check to completely remove data URI.
    if ($url && $blazies->is('unsafe')) {
      $_escaped = $blazies->filter('use_data_uri');
      $url = Sanitize::url($url, $_escaped);
    }

    // Only strip if not already.
    $element['#url'] = $_escaped ? $url : UrlHelper::stripDangerousProtocols($url);
    $url_attributes['data-media'] = Json::encode($json);
  }

  /**
   * Provides responsive image for lightboxes.
   */
  private static function responsiveImage(array &$element, array $options, $manager): bool {
    [
      'uri' => $uri,
      'box_style' => $box_style,
    ] = $options;

    // The _responsive_image_build_source_attributes is WSOD if missing.
    $_resimage = FALSE;
    try {
      if ($resimage = $manager->load($box_style, 'responsive_image_style')) {
        $_resimage = TRUE;
        $element['#lightbox_html'] = [
          '#theme' => 'responsive_image',
          '#responsive_image_style_id' => $resimage->id(),
          '#uri' => $uri,
        ];
      }
    }
    catch (\Exception $e) {
      // Silently failed like regular images when missing rather than WSOD.
    }
    return $_resimage;
  }

  /**
   * Builds lightbox captions.
   *
   * @param object|mixed $item
   *   The \Drupal\image\Plugin\Field\FieldType\ImageItem item.
   * @param array $settings
   *   The settings to work with.
   *
   * @return array
   *   The renderable array of caption, or empty array.
   */
  private static function buildCaptions($item, array $settings = []): array {
    $blazies = $settings['blazies'];
    $title   = $blazies->get('image.title');
    $alt     = $blazies->get('image.alt');
    $delta   = $blazies->get('delta', 0);
    $object  = NULL;
    $caption = '';

    // @todo re-check this if any issues, might be a fake stdClass image item.
    if ($item) {
      $object = method_exists($item, 'getEntity')
        ? $item->getEntity() : ($item->entity ?? NULL);
    }

    $entity = $blazies->get('entity.instance') ?: $object;

    switch ($settings['box_caption']) {
      case 'auto':
        $caption = $alt ?: $title;
        break;

      case 'alt':
        $caption = $alt;
        break;

      case 'title':
        $caption = $title;
        break;

      case 'alt_title':
      case 'title_alt':
        $alt     = $alt ? '<p>' . $alt . '</p>' : '';
        $title   = $title ? '<h2>' . $title . '</h2>' : '';
        $caption = $settings['box_caption'] == 'alt_title' ? $alt . $title : $title . $alt;
        break;

      case 'entity_title':
        $caption = $entity && method_exists($entity, 'label')
          ? $entity->label() : '';
        break;

      case 'custom':
        $caption = '';

        if (!empty($settings['box_caption_custom']) && $object) {
          $options = ['clear' => TRUE];
          $caption = \Drupal::token()->replace($settings['box_caption_custom'], [
            $object->getEntityTypeId() => $object,
          ], $options);

          // Checks for multi-value text fields, and maps its delta to image.
          if (!empty($caption) && strpos($caption, ", <p>") !== FALSE) {
            $caption = str_replace(", <p>", '| <p>', $caption);
            $captions = explode("|", $caption);
            $caption = $captions[$delta] ?? '';
          }
        }
        break;

      default:
        $caption = $settings['box_caption'] == 'inline' ? '' : $settings['box_caption'];
    }

    return empty($caption)
      ? []
      : ['#markup' => Xss::filter($caption, BlazyDefault::TAGS)];
  }

}
