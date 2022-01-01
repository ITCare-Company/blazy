/**
 * @file
 * Provides Filter module integration.
 */

(function ($, Drupal) {

  'use strict';

  var _wrapper = 'media-wrapper--blazy';
  var _element = '.' + _wrapper + ':not(.grid .' + _wrapper + ')';

  /**
   * Adds blazy container attributes required for grouping, or by lightboxes.
   *
   * @param {HTMLElement} elm
   *   The .media-wrapper--blazy HTML element.
   */
  function blazyFilter(elm) {
    var cn = $.closest(elm, '.text-formatted');
    if (cn === null) {
      cn = $.closest(elm, '.field');
    }

    if (cn === null || $.hasClass(cn, 'blazy')) {
      return;
    }

    $.addClass(cn, 'blazy');
    $.attr(cn, 'data-blazy', '');

    // Not using elm is fine since this should be executed once.
    var box = $.find(cn, '.litebox');
    if (!$.isNull(box)) {
      var media = $.parse($.attr(box, 'data-media'));
      if ('id' in media) {
        var id = media.id;
        $.addClass(cn, 'blazy--' + id);
        $.attr(cn, 'data-' + id + '-gallery', '');
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

      $.once(blazyFilter, _element, context);
    }
  };

})(dBlazy, Drupal);
