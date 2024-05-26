/**
 * @file
 * Provides CSS3 Native Grid treated as Masonry based on Grid Layout.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Grid_Layout
 * The two-dimensional Native Grid does not use JS until treated as a Masonry.
 * If you need GridStack kind, avoid inputting numeric value for Grid.
 * Below is the cheap version of GridStack.
 *
 * @credit: https://css-tricks.com/a-lightweight-masonry-solution/
 */

(function ($, Drupal) {

  'use strict';

  $.masonry = $.masonry || {};

  var ENGINE = 'nativegrid';
  var NICK = 'masonry';
  var ID = 'b-' + ENGINE;
  var ID_ONCE = 'b-' + NICK;
  var C_IS_MASONRY = 'is-' + ID_ONCE;
  var C_MOUNTED = C_IS_MASONRY + '-mounted';
  var S_BASE = '.' + ID;
  var S_ELEMENT = S_BASE + '.' + C_IS_MASONRY;
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
   * Attaches Blazy behavior to HTML element identified by .b-nativegrid.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyNativeGridMasonry = {
    attach: function (context) {

      var grids = $.once(process, ID_ONCE, S_ELEMENT, context);

      if (grids.length
          && getComputedStyle(grids[0]).gridTemplateRows !== 'masonry') {
        var opts = {
          nick: NICK,
          engine: ENGINE,
          unload: UNLOAD
        };
        $.masonry.init(grids, opts);
      }

    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        UNLOAD = true;
        var els = $.once.removeSafely(ID_ONCE, S_BASE, context);
        if (els && els.length) {
          $.removeClass(els[0], C_MOUNTED);
        }
      }
    }

  };

}(dBlazy, Drupal));
