<?php

namespace Drupal\blazy\Theme;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityInterface;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\internals\Internals;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Utility\Arrays;
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
  public static function attach(array &$load, array &$attach, $blazies = NULL): void {
    // @todo remove NULL check at 3.x.
    $blazies = $blazies ?: $attach['blazies'];

    if ($name = $blazies->get('lightbox.name')) {
      $load['library'][] = 'blazy/lightbox';

      // Built-in lightboxes.
      if ($name == 'colorbox') {
        self::attachColorbox($load);
      }
      foreach (['colorbox', 'flybox', 'mfp', 'photobox'] as $key) {
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
  public static function build(array &$element): void {
    $manager    = Internals::service('blazy.manager');
    $settings   = &$element['#settings'];
    $blazies    = $settings['blazies'];
    $switch     = $blazies->get('lightbox.name');
    $switch_css = str_replace('_', '-', $switch);
    $item       = $blazies->get('image.item');
    $uri        = $blazies->get('image.uri');
    $valid      = $blazies->get('image.valid') ?: Blazy::isValidUri($uri);
    $_box_style = $settings['box_style'] ?? NULL;
    $box_style  = $blazies->get('box.style');
    $box_url    = $blazies->get('box.url');
    $box_url    = $url = $box_url ?: Blazy::toUrl($settings, $box_style, $uri);
    $colorbox   = $blazies->get('colorbox');
    $gallery_id = $blazies->get('lightbox.gallery_id');
    $box_id     = $blazies->is('gallery') ? $gallery_id : NULL;
    $box_width  = $blazies->get('image.original.width') ?: $item->width ?? NULL;
    $box_height = $blazies->get('image.original.height') ?: $item->height ?? NULL;
    $count      = Internals::count($blazies);
    $delta      = $blazies->get('delta', 0);
    $multimedia = $blazies->is('multimedia');
    $svg        = $blazies->is('unstyled');
    $styleable  = $valid && !$svg;
    $_fullsize  = $_box_style && $styleable;
    $format1    = 'blazy__%s litebox';
    $format2    = 'blazy__%s litebox litebox--multimedia';
    $_resimage  = FALSE;
    $_trusted   = $blazies->get('media.escaped')
      || $blazies->get('image.trusted')
      || $blazies->is('rendered');

    // Provide relevant URL since it is a lightbox.
    $attrs = &$element['#url_attributes'];
    $attrs['class'][] = sprintf($multimedia ? $format2 : $format1, $switch_css);
    $attrs['data-' . $switch_css . '-trigger'] = TRUE;

    // Might not be present from BlazyFilter.
    $json = ['id' => $switch_css, 'count' => $count, 'boxType' => 'image'];
    foreach (['bundle', 'type'] as $key) {
      if ($value = $blazies->get('media.' . $key)) {
        $json[$key] = $value;
      }
    }

    // If multimendia with remote or local videos.
    $json['token'] = $blazies->get('media.token');
    if ($provider = $blazies->get('media.provider')) {
      $json['provider'] = $provider;
    }

    if ($multimedia) {
      $box_width = 640;
      $box_height = 360;

      $json['playable'] = $blazies->is('playable');
      if ($embed = $blazies->get('media.embed_url')) {
        // Force autoplay for media URL on lightboxes, saving another click.
        // BC for non-oembed such as Video Embed Field without Media migration.
        $url = Blazy::autoplay($embed, !$_trusted);
        $attrs['data-oembed-url'] = $url;
        $json['boxType'] = 'iframe';

        // Supports external URL when hard-coded iframe at BlazyFilter.
        if ($blazies->get('image.url')) {
          $data_box_url = TRUE;
        }
      }
      else {
        $json['boxType'] = 'html';
      }

      // This allows PhotoSwipe with videos still swipable.
      if ($styleable && $check = $blazies->get('box_media.url')) {
        $box_width    = $blazies->get('box_media.width') ?: $box_width;
        $box_height   = $blazies->get('box_media.height') ?: $box_height;
        $box_url      = $check;
        $data_box_url = TRUE;
      }

      if ($blazies->get('photobox')) {
        $attrs['rel'] = 'video';
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
            'blazies' => $blazies,
            'box_style' => $_box_style,
            'uri' => $uri,
          ];
          $_resimage = self::responsiveImage($element, $options, $manager);
        }

        // Use non-responsive image if so-configured.
        if (!$_resimage && $check = $blazies->get('box.url')) {
          $box_width  = $blazies->get('box.width') ?: $box_width;
          $box_height = $blazies->get('box.height') ?: $box_height;
          $box_url    = $url = $check;
        }
      }
    }

    // @todo recheck if $valid tweakable.
    // if (!$valid) {
    $box_url = UrlHelper::stripDangerousProtocols($box_url);
    // }
    // Only needed by videos, the rest can just use $url set into HREF.
    if (isset($data_box_url)) {
      $attrs['data-box-url'] = $box_url;
      $blazies->set('lightbox.media_preview_url', $box_url);
    }

    $blazies->set('lightbox.url', $box_url)
      ->set('lightbox.width', (int) $box_width)
      ->set('lightbox.height', (int) $box_height);

    // @todo recheck $count given views gallery vs formatters vs formatters
    // inside views gallery, and add: && $count > 1.
    if ($box_id && $count > 1) {
      // Always 0 when embedded inside a view since it is not aware of it,
      // unless using blazy formatter for the images within Splide, Slick, etc.
      // Adds persistent delta, help fix for slide clones which screw up deltas.
      if ($blazies->is('gallery')) {
        $attrs['data-b-delta'] = $delta;
      }

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
      'box_url' => $box_url,
      'url' => $url,
      'item' => $item,
      'box_width' => $box_width,
      'box_height' => $box_height,
      '_trusted' => $_trusted,
      '_resimage' => $_resimage,
    ];

    self::content(
      $element,
      $json,
      $attrs,
      $options,
      $settings,
      $manager
    );
  }

  /**
   * Attaches Colorbox if so configured.
   */
  private static function attachColorbox(array &$load): void {
    if ($service = Internals::service('colorbox.attachment')) {
      $dummy = [];
      $service->attach($dummy);

      $load = Arrays::merge($load, $dummy, '#attached');

      unset($dummy);
    }
  }

  /**
   * Provides html content for lightboxes.
   */
  private static function content(
    array &$element,
    array &$json,
    array &$attrs,
    array $options,
    array $settings,
    $manager
  ): void {
    [
      'box_url' => $box_url,
      'url' => $url,
      'item' => $item,
      'box_width' => $box_width,
      'box_height' => $box_height,
      '_trusted' => $_trusted,
      '_resimage' => $_resimage,
    ] = $options;

    $is_html = FALSE;
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
      $is_html = TRUE;
      $type = str_replace('_', '-', $json['boxType']);
      // Local video ($html) is wrapped, but not Responsive image ($box_html).
      // Reasons: video displayed as is, image is disassembled for zoom, etc.,
      // or just dumped as is, depending on the supportive lightbox capability.
      $html = [
        '#theme' => 'container',
        '#children' => $box_html,
        '#attributes' => [
          // @todo make it flexible for regular non-media HTML.
          'class' => ['media', 'media--boxtype-' . $type],
        ],
      ];

      // Only video needs help, responsive image is taken care of by lightbox.
      $style = '';
      if ($has_dim) {
        $pad = round((($json['height'] / $json['width']) * 100), 2);
        $style .= 'width:' . $json['width'] . 'px; padding-bottom: ' . $pad . '%;';
      }

      // Currently only audio with background cover.
      if ($box_url && $blazies->is('multicontent')) {
        $style .= 'background-image: url(' . $box_url . ');';
        $html['#attributes']['class'][] = 'b-bg-static';
      }

      if ($style) {
        $html['#attributes']['style'] = $style;
      }

      // Do not add more classes after media--ratio. This is the only style
      // identifier/ prefix, must come last, else inline style is removed.
      $html['#attributes']['class'][] = 'media--ratio';

      // Responsive image is unwrapped. Local videos wrapped.
      $content = $_resimage ? $box_html : $html;
      $content = trim($manager->renderer()->renderPlain($content));

      // @todo merge with BlazyDefault::TAGS when mixed contents supported.
      // Lightbox Responsive|Picture image will be broken when filtered out.
      $content = $_resimage || $_trusted
        ? $content : Xss::filter($content, BlazyDefault::MEDIA_TAGS);

      // See https://www.drupal.org/project/drupal/issues/3109650.
      $unstrips = [
        'prestyle' => 'ratio"',
        'style' => $style,
      ];

      $json['html'] = Sanitize::unstrip($content, $unstrips);

      // @todo refine type as needed, no longer relevant for boxType.
      $json['type'] = 'rich';
      if ($_resimage) {
        $json['boxType'] = Blazy::has($content, '<picture')
          ? 'picture' : 'responsiveImage';
      }
      else {
        if ($blazies->is('local_audio')) {
          $json['boxType'] = 'audio';
        }
        elseif ($blazies->is('local_video')) {
          $json['boxType'] = 'video';
        }
        else {
          $json['boxType'] = 'html';
        }
      }

      unset($element['#lightbox_html']);
    }

    // Provides captions if so configured.
    if (!empty($settings['box_caption'])) {
      $element['#captions']['lightbox'] = self::getCaptions($settings, $item, $manager);
    }

    // Do not show icon for local video file unless supported.
    $is_local = $blazies->is('local_media');
    $show_icon = !$is_local || $is_local && $blazies->is('richbox');
    if ($show_icon) {
      $icon = '<span class="media__icon media__icon--litebox"></span>';
      $element['#icon']['lightbox']['#markup'] = $icon;
    }

    // Only strip if not already.
    $element['#url'] = $_trusted ? $url : UrlHelper::stripDangerousProtocols($url);
    // @todo replace with data-b-media at 3.x to avoid potential conflicts.
    $attrs['data-media'] = Json::encode($json);

    if ($is_html) {
      $attrs['class'][] = 'litebox--html';
    }
  }

  /**
   * Provides responsive image for lightboxes.
   */
  private static function responsiveImage(array &$element, array $options, $manager): bool {
    [
      'blazies' => $blazies,
      'box_style' => $box_style,
      'uri' => $uri,
    ] = $options;

    // The _responsive_image_build_source_attributes is WSOD if missing.
    $_resimage = FALSE;
    try {
      if ($resimage = $manager->load($box_style, 'responsive_image_style')) {
        $_resimage = TRUE;
        $alt = $blazies->get('image.alt');

        // Check for image.escaped to avoid unecessary double escapes.
        if (!$blazies->get('image.escaped')) {
          $alt = $alt ? Html::escape(strip_tags($alt)) : t('Preview');
        }

        $attrs = ['alt' => $alt];

        $element['#lightbox_html'] = [
          '#theme' => 'responsive_image',
          '#responsive_image_style_id' => $resimage->id(),
          '#uri' => $uri,
          '#attributes' => $attrs,
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
   * @param array $settings
   *   The settings to work with.
   * @param object $item
   *   The \Drupal\image\Plugin\Field\FieldType\ImageItem item or \stdClass.
   * @param object $manager
   *   The \Drupal\blazy\BlazyManager service.
   *
   * @return array
   *   The renderable array of caption, or empty array.
   */
  private static function getCaptions(array $settings, $item, $manager): array {
    $blazies = $settings['blazies'];
    $title   = $blazies->get('image.raw.title');
    $alt     = $blazies->get('image.raw.alt');
    $delta   = $blazies->get('delta', 0);
    $object  = $blazies->get('media.instance');
    $node    = $blazies->get('entity.instance');
    $file    = $blazies->get('image.entity');
    $option  = $settings['box_caption'];
    $custom  = trim($settings['box_caption_custom'] ?? '');
    $caption = '';

    // @todo re-check this if any issues, might be a fake stdClass image item.
    // @todo remove all ImageItem references for blazies as object at 3.x.
    if ($item) {
      $file = $item->entity ?? $file;
      if (!$object) {
        $object = method_exists($item, 'getEntity')
          ? $item->getEntity() : $file;
      }
    }

    $entity = $node ?: $object;

    switch ($option) {
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
        $caption = $option == 'alt_title' ? $alt . $title : $title . $alt;
        break;

      case 'entity_title':
        $caption = $entity && method_exists($entity, 'label')
          ? $entity->label() : '';
        break;

      case 'custom':
        $caption = '';

        // $object can be file or media for plain images, or media entities.
        if ($custom && $object instanceof EntityInterface) {
          $options = ['clear' => TRUE];
          $params  = [$object->getEntityTypeId() => $manager->getTranslatedEntity($object)];

          if (BlazyFile::isFile($file) && $file != $object) {
            $params += ['file' => $manager->getTranslatedEntity($file)];
          }
          if ($node && $node != $object) {
            $params += [$node->getEntityTypeId() => $manager->getTranslatedEntity($node)];
          }

          $caption = \Drupal::token()->replace($custom, $params, $options);

          // Checks for multi-value text fields, and maps its delta to image.
          if (Blazy::has($caption, ", <p>")) {
            $caption = str_replace(", <p>", '| <p>', $caption);
            $captions = explode("|", $caption);
            $caption = $captions[$delta] ?? '';
          }
        }
        break;

      default:
        // Inline is using [data-caption] filter at Blazy Filter. If equals to
        // inline an sich, no captions available, otherwise print it as is.
        // See \Drupal\blazy\Plugin\Filter\BlazyFilterBase\buildImageCaption().
        $caption = $option == 'inline' ? '' : $option;
    }

    return empty($caption)
      ? []
      : ['#markup' => Sanitize::caption($caption)];
  }

}
