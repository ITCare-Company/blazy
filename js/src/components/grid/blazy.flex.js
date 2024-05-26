/**
 * @file
 * Provides CSS3 flex based on Flexbox layout.
 *
 * Credit: https://fjolt.com/article/css-grthis loader id-masonry
 *
 * @requires aspect ratio fluid in the least to layout correctly.
 * @todo deprecated this is worse than NativeGrid Masonry. We can't compete
 * against the fully tested Outlayer or GridStack library.
 */

(function ($, Drupal) {

  'use strict';

  $.masonry = $.masonry || {};

  var NICK = 'flex';
  var ID = 'b-' + NICK;
  var ID_ONCE = ID;
  var C_MOUNTED = 'is-' + ID_ONCE;
  var S_BASE = '.' + ID;
  // @fixme with lock masonry broken after AJAX unless class removed at detach.
  // drupalSettings.blazy.useAjax ? '.' + ID :
  var S_ELEMENT = S_BASE + ':not(.' + C_MOUNTED + ')';
  var UNLOAD;

  /**
   * Processes a grid masonry.
   *
   * @param {HTMLElement} elm
   *   The container HTML element.
   */
  function process(elm) {
    $.addClass(elm, C_MOUNTED);
  }

  /**
   * Attaches Blazy behavior to HTML element identified by .b-flex.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyFlex = {
    attach: function (context) {

      var grids = $.once(process, ID_ONCE, S_ELEMENT, context);
      if (grids.length) {
        var opts = {
          nick: NICK,
          engine: NICK,
          gap: '--bf-col-gap',
          unload: UNLOAD
        };
        $.masonry.init(grids, opts);
      }

    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        UNLOAD = true;

        setTimeout(function () {
          var els = $.once.removeSafely(ID_ONCE, S_BASE, context);
          if (els && els.length) {
            $.removeClass(els[0], C_MOUNTED);
          }
        });
      }
    }
  };

}(dBlazy, Drupal));
