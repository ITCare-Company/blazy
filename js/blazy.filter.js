/**
 * @file
 * Provides Filter module integration.
 */

(function (Drupal, _d) {

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
    var cn = _d.closest(elm, '.text-formatted');
    if (cn === null) {
      cn = _d.closest(elm, '.field');
    }

    if (cn === null || _d.hasClass(cn, 'blazy')) {
      return;
    }

    _d.addClass(cn, 'blazy');
    _d.attr(cn, 'data-blazy', '');

    // Not using elm is fine since this should be executed once.
    var box = _d.find(cn, '.litebox');
    if (!_d.isNull(box)) {
      var media = _d.parse(_d.attr(box, 'data-media'));
      if ('id' in media) {
        var id = media.id;
        _d.addClass(cn, 'blazy--' + id);
        _d.attr(cn, 'data-' + id + '-gallery', '');
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

      context = _d.context(context);

      _d.once(blazyFilter, _element, context);
    }
  };

})(Drupal, dBlazy);
