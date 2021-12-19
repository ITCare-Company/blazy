/**
 * @file
 * Provides Photobox integration for Image and Media fields.
 */

(function ($, Drupal, once, _db) {

  'use strict';

  Drupal.blazy = Drupal.blazy || {};

  Drupal.behaviors.blazyPhotobox = {
    attach: function (context) {

      context = _db.context(context);

      once('blazy-photobox', '[data-photobox-gallery]', context).forEach(function (item) {
        $(item).photobox('a[data-photobox-trigger]', {thumb: '> [data-thumb]', thumbAttr: 'data-thumb'}, Drupal.blazy.photobox);
      });
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        once.filter('blazy-photobox').remove();
      }
    }
  };

  /**
   * Callback for custom captions.
   */
  Drupal.blazy.photobox = function () {
    var $elm = $('.litebox[href="' + $('.pbWrapper img').attr('src') + '"]');
    var $caption = $elm.next('.litebox-caption');

    if ($caption.length) {
      $('#pbCaption .title').html($caption.html());
    }
  };

}(jQuery, Drupal, once, dBlazy));
