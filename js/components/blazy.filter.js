/**
 * @file
 * Provides Filter module integration.
 */

(function ($, Drupal) {

  'use strict';

  var _id = 'blazy';
  var _wrapper = 'media-wrapper--' + _id;
  var _element = '.' + _wrapper + ':not(.grid .' + _wrapper + ')';
  var _data = 'data-';

  /**
   * Adds blazy container attributes required for grouping, or by lightboxes.
   *
   * @param {HTMLElement} elm
   *   The .media-wrapper--blazy HTML element.
   */
  function process(elm) {
    var cn = $.closest(elm, '.text-formatted') || $.closest(elm, '.field');
    if (!$.isElm(cn) || $.hasClass(cn, _id)) {
      return;
    }

    $.addClass(cn, _id)
      .attr(_data + _id, '');

    // Not using elm is fine since this should be executed once.
    var box = $.find(cn, '.litebox');
    if ($.isElm(box)) {
      var media = $.parse($.attr(box, _data + 'media'));
      if ('id' in media) {
        var mid = media.id;
        $.addClass(cn, _id + '--' + mid)
          .attr(_data + mid + '-gallery', '');
      }
    }
  }

  /**
   * Attaches Blazy filter behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyFilter = {
    attach: function (context) {

      context = $.context(context);

      $.once(process, _element, context);
    }
  };

})(dBlazy, Drupal);
