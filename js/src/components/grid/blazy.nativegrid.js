/**
 * @file
 * Provides CSS3 Native Grid for dynamic multi-breakpoint grids.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Grid_Layout
 */

(function ($, Drupal) {

  'use strict';

  $.dyGrid = $.dyGrid || {};

  var NICK = 'nativegrid';
  var ID = 'b-' + NICK;
  var ID_ONCE = ID;
  var IS_NAME = 'is-' + ID_ONCE;
  var C_MOUNTED = IS_NAME + '-mounted';
  var DATA_ID = 'data-b-nativegrid';
  var S_BASE = '[' + DATA_ID + ']';
  var S_ELEMENT = '.' + ID + S_BASE;
  var UNLOAD;

  /**
   * Processes a grid native.
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
  Drupal.behaviors.blazyNativeGrid = {
    attach: function (context) {

      var roots = $.once(process, ID_ONCE, S_ELEMENT, context);
      if (roots.length) {
        var opts = {
          nick: NICK,
          md: '--bn-md',
          lg: '--bn-lg',
          unload: UNLOAD
        };

        $.dyGrid.init(roots, opts);
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
