<?php

/**
 * @file
 * Hooks and API provided by the Blazy module.
 *
 * Modules may implement any of the available hooks to interact with Blazy.
 * Blazy may be configured using the web interface using formatters, or Views.
 * However below is a few sample coded ones as per Blazy RC2+.
 */

/**
 * A single image sample.
 *
 * @return array
 *   The renderable array of a blazy element.
 *
 * @see \Drupal\blazy\Blazy::buildAttributes()
 * @see \Drupal\blazy\Dejavu\BlazyDefault::imageSettings()
 */
function my_module_render_blazy() {
  $settings = [
    // URI is stored in #settings property so to allow traveling around video
    // and lightboxes before being passed into theme_blazy().
    'uri' => 'public://logo.jpg',

    // Explicitly request for Blazy.
    // This allows Slick lazyLoad to not load Blazy.
    'lazy' => 'blazy',

    // Optionally provide an image style:
    'image_style' => 'thumbnail',
  ];

  $build = [
    '#theme'    => 'blazy',
    '#settings' => $settings,

    // Or below for clarity:
    '#settings' => ['uri' => 'public://logo.jpg', 'lazy' => 'blazy'],

    // Pass custom attributes into the same #item_attributes property as Blazy
    // formatters so to respect external modules like RDF, etc. without extra
    // property. The regular #attributes property is reserved by Blazy container
    // which holds either IMG, icons, or iFrame. Meaning Blazy is not just IMG.
    '#item_attributes' => [
      'alt'   => t('Thumbnail'),
      'title' => t('Thumbnail title'),
      'width' => 120,
    ],

    // Finally load the library, or include it into a parent container.
    '#attached' => ['library' => ['blazy/load']],
  ];

  return $build;
}

/**
 * A multiple image sample.
 *
 * For advanced usages with multiple images, and a few Blazy features such as
 * lightboxes, lazyloaded images, or iframes, including CSS background and
 * aspect ratio, etc.:
 * o Invoke blazy.manager, and or blazy.formatter.manager, services.
 * o Use \Drupal\blazy\BlazyManager::getImage() method to work with images and
 *   pass relevant settings which request for particular Blazy features
 *   accordingly.
 * o Use \Drupal\blazy\BlazyManager::attach() to load relevant libraries.
 *
 * @return array
 *   The renderable array of multiple blazy elements.
 *
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatterTrait::buildElements()
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyVideoFormatter::buildElements()
 * @see \Drupal\gridstack\Plugin\Field\FieldFormatter\GridStackFileFormatterBase::buildElements()
 * @see \Drupal\slick\Plugin\Field\FieldFormatter\SlickFileFormatterBase::buildElements()
 * @see \Drupal\blazy\BlazyManager::getImage()
 * @see \Drupal\blazy\Dejavu\BlazyDefault::imageSettings()
 */
function my_module_render_blazy_multiple() {
  // Invoke the plugin class, or use a DI service container accordingly.
  $manager = \Drupal::service('blazy.manager');

  $settings = [
    // Explicitly request for Blazy library.
    // This allows Slick lazyLoad, or text formatter, to not load Blazy.
    'blazy' => TRUE,

    // Supported media switcher options dependent on available modules:
    // colorbox, media (Image to iframe), photobox.
    'media_switch' => 'media',
  ];

  // Build images.
  $build = [
    // Load images via $manager->getImage().
    // See above ...Formatter::buildElements() for consistent samples.
  ];

  // Finally attach libraries as requested via $settings.
  $build['#attached'] = $manager->attach($settings);

  return $build;
}

/**
 * Implements hook_blazy_lightboxes_alter().
 *
 * Registers a custom lightbox to be part of Media switch option for Blazy UI.
 *
 * @see https://www.drupal.org/project/blazy_photoswipe
 */
function my_module_blazy_lightboxes_alter(array &$lightboxes) {
  $lightboxes[] = 'photoswipe';
}

/**
 * Implements hook_blazy_alter().
 *
 * Modifies Blazy output to support a custom lightbox.
 */
function my_module_blazy_alter(array &$image, $settings = []) {
  if (!empty($settings['media_switch']) && $settings['media_switch'] == 'photoswipe') {
    $image['#pre_render'][] = 'my_module_pre_render';
  }
}

/**
 * Do whatever needed to modify/ extend Blazy output.
 */
function my_module_pre_render($image) {
  $settings = isset($image['#settings']) ? $image['#settings'] : [];

  // Video's HREF points to external site, adds URL to local image.
  if (!empty($settings['box_url']) && !empty($settings['embed_url'])) {
    $image['#url_attributes']['data-box-url'] = $settings['box_url'];
  }

  return $image;
}

/**
 * Implements hook_blazy_attach_alter().
 *
 * Includes a custom library along with own drupalSettings, and JS template.
 */
function my_module_blazy_attach_alter(array &$load, $settings = []) {
  if (!empty($settings['photoswipe'])) {
    $load['library'][] = 'my_module/load';

    $manager = \Drupal::service('blazy.manager');
    $template = ['#theme' => 'photoswipe_container'];
    $load['drupalSettings']['photoswipe'] = [
      'options' => $manager->configLoad('options', 'photoswipe.settings'),
      'container' => $manager->getRenderer()->renderPlain($template),
    ];
  }
}
