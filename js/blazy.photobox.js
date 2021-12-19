/**
 * @file
 * Provides Photobox integration for Image and Media fields.
 */

(function ($, Drupal, once, _db) {

  'use strict';

  var _idOnce = 'blazy-photobox';
  var _element = '[data-photobox-gallery]';

  Drupal.blazy = Drupal.blazy || {};

  Drupal.behaviors.blazyPhotobox = {
    attach: function (context) {

      context = _db.context(context);

      once(_idOnce, _element, context).forEach(function (item) {
        $(item).photobox('a[data-photobox-trigger]', {thumb: '> [data-thumb]', thumbAttr: 'data-thumb'}, Drupal.blazy.photobox);
      });
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        once.remove(_idOnce, _element, context);
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
