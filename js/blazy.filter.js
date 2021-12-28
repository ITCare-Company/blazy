/**
 * @file
 * Provides Filter module integration.
 */

(function (Drupal, once, _db) {

  'use strict';

  var _id = 'blazy-filter';
  var _element = '.media-wrapper--blazy:not(.grid .media-wrapper--blazy)';

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
    var box = _db.find(cn, '.litebox');
    if (!_db.isNull(box)) {
      var media = _db.parse(box.getAttribute('data-media'));
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

      context = _db.context(context);

      once(_id, _element, context).forEach(blazyFilter);
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        if (once.find(_id, context).length) {
          once.remove(_id, _element, context);
        }
      }
    }
  };

})(Drupal, once, dBlazy);
