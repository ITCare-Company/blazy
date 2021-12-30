/**
 * @file
 * Provides Photobox integration for Image and Media fields.
 */

(function ($, Drupal, _d) {

  'use strict';

  var _mounted = 'litebox--on';
  var _element = '[data-photobox-gallery]:not(.' + _mounted + ')';

  Drupal.blazy = Drupal.blazy || {};

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

  Drupal.behaviors.blazyPhotobox = {
    attach: function (context) {

      context = _d.context(context);

      var doPhotobox = function (item) {
        var $box = $(item);

        $box.photobox('a[data-photobox-trigger]', {
          thumb: '> [data-thumb]',
          thumbAttr: 'data-thumb'
        }, Drupal.blazy.photobox);

        $box.addClass(_mounted);
      };

      _d.once(doPhotobox, _element, context);
    }
  };

}(jQuery, Drupal, dBlazy));
