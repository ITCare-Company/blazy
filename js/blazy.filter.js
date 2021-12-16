/**
 * @file
 * Provides Filter module integration.
 */

(function (Drupal, once, _db) {

  'use strict';

  /**
   * Adds blazy container attributes required for grouping, or by lightboxes.
   *
   * @param {HTMLElement} elm
   *   The .media-wrapper--blazy HTML element.
   */
  function blazyFilter(elm) {
    var cn = _db.closest(elm, '.text-formatted');
    if (cn === null) {
      cn = _db.closest(elm, '.field');
    }

    if (cn === null || cn.classList.contains('blazy')) {
      return;
    }

    cn.classList.add('blazy');
    cn.setAttribute('data-blazy', '');

    // Not using elm is fine since this should be executed once.
    var box = cn.querySelector('.litebox');
    if (box !== null) {
      var media = box.getAttribute('data-media') ? _db.parse(box.getAttribute('data-media')) : {};
      if ('id' in media) {
        var id = media.id;
        cn.classList.add('blazy--' + id);
        cn.setAttribute('data-' + id + '-gallery', '');
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
      if ('length' in context) {
        context = context[0];
      }

      once('blazy-filter', '.media-wrapper--blazy:not(.grid .media-wrapper--blazy)', context).forEach(blazyFilter);
    }
  };

})(Drupal, once, dBlazy);
