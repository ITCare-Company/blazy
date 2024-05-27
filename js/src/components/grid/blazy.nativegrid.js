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
  var DATA_ID = 'data-b-' + NICK;
  var S_BASE = '[' + DATA_ID + ']';
  var S_ELEMENT = '.' + ID + S_BASE + ':not(.' + C_MOUNTED + ')';
  var BIG_PIPE = $.isBigPipe();
  var INITIAL = true;
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
        // Prevents from BigPipe problematic multiple invocations, 6+.
        UNLOAD = BIG_PIPE ? $.isBigPipeDone() : true;
        if (UNLOAD && !INITIAL) {
          $.once.removeSafely(ID_ONCE, S_BASE, context, C_MOUNTED);
        }

        INITIAL = BIG_PIPE ? !$.isBigPipeDone() : false;
      }
    }

  };

}(dBlazy, Drupal));
