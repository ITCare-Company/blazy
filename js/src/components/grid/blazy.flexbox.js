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
  var ID_ONCE = 'b-' + NICK;
  var C_NAME = 'is-' + ID_ONCE;
  var DATA_ID = 'data-b-' + NICK;
  var S_ELEMENT = '[' + DATA_ID + ']';
  var UNLOAD;

  /**
   * Attaches Blazy behavior to HTML element identified by .b-flexbox.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyFlexbox = {
    attach: function (context) {

      $.wwoBigPipe(function () {
        var roots = $.once(ID_ONCE, S_ELEMENT, context);

        if (roots.length) {
          var opts = {
            nick: NICK,
            cName: C_NAME,
            dataId: DATA_ID,
            md: '--bfb-md',
            lg: '--bfb-lg',
            selector: S_ELEMENT,
            unload: UNLOAD
          };

          $.dyGrid.init(roots, opts);
        }
      });
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        // Prevents from BigPipe problematic multiple invocations.
        $.wwoBigPipe(function () {
          $.once.removeSafely(ID_ONCE, S_ELEMENT, context);
          UNLOAD = $.once.unload;
        });
      }
    }

  };

}(dBlazy, Drupal));
