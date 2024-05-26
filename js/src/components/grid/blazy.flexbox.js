/**
 * @file
 * Provides CSS3 flex based on Flexbox layout.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/CSS/flex
 */

(function ($, Drupal) {

  'use strict';

  $.dyGrid = $.dyGrid || {};

  var NICK = 'flexbox';
  var ID = 'b-' + NICK;
  var ID_ONCE = ID;
  var IS_NAME = 'is-' + ID_ONCE;
  var C_MOUNTED = IS_NAME + '-mounted';
  var DATA_ID = 'data-b-' + NICK;
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
   * Attaches Blazy behavior to HTML element identified by .b-flexbox.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyFlexbox = {
    attach: function (context) {

      var roots = $.once(process, ID_ONCE, S_ELEMENT, context);
      if (roots.length) {
        var opts = {
          nick: NICK,
          md: '--bfb-md',
          lg: '--bfb-lg',
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
