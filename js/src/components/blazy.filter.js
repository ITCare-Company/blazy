/**
 * @file
 * Provides Filter module integration.
 */

(function ($, Drupal, _doc) {

  'use strict';

  var _id = 'blazy';
  var _idOnce = 'b-filter';
  var _wrapper = 'media-wrapper--' + _id;
  var _mounted = 'is-' + _idOnce;
  var _element = '.' + _wrapper + ':not(.grid .' + _wrapper + '):not(.' + _mounted + ')';
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

    var $cn = $(cn);
    var $nonShortcode = $cn.find('.media-wrapper--blazy');

    // Only enable for non-shotcode due to not having a container. With
    // shortcodes, the required .blazy container is there.
    if (!$.isElm($nonShortcode)) {
      return;
    }

    $cn.addClass(_id)
      .attr(_data + _id, '');

    // Not using elm is fine since this should be executed once.
    // Basicallly this makes the lightbox gallery available at inline images
    // by taking the first found `data-media` to determine the lightbox id.
    // Originally using PHP loop over filters, but more efficient with client.
    var box = $cn.find('.litebox');
    if ($.isElm(box)) {
      var media = $.parse($.attr(box, _data + 'media'));
      if ('id' in media) {
        var mid = media.id;
        $cn.addClass(_id + '--' + mid)
          .attr(_data + mid + '-gallery', '');
      }
    }

    $.addClass(elm, _mounted);
  }

  /**
   * Attaches Blazy filter behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyFilter = {
    attach: function (context) {
      $.once(process, _idOnce, _element, context);
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        $.once.removeSafely(_idOnce, _element, context);
      }
    }
  };

})(dBlazy, Drupal, this.document);
