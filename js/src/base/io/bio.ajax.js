/**
 * @file
 * Provides Intersection Observer API AJAX helper.
 *
 * Blazy IO works fine with AJAX, until using VIS, or alike. Adds a helper.
 * Required to fix for what Native lazy doesn't support Blur, Video, BG.
 * Similar to core responsive_image/ajax fix, only different approach.
 *
 * @todo remove once bio.js plays nice for media, VIS, blocks.
 */

(function ($, jq, Drupal, _doc) {

  'use strict';

  var VARS = {
    id: 'b-ajax',
    bRoot: 'b-root',
    selector: 'body',
    eventName: 'ajaxSuccess',
    revTimer: null
  };

  /**
   * Process DOM revalidations with newly added AJAX contents.
   */
  function process() {
    var me = this;

    var revalidate = function (_, response, ajax) {

      if (!$.wwoBigPipeDone() || !response) {
        return;
      }

      // Clear any pending timer.
      clearTimeout(VARS.revTimer);

      // DOM ready fix.
      VARS.revTimer = setTimeout(function () {

        var bio = me.init;

        // Ensure we have Bio loaded.
        if (bio) {
          var opts = me.options;
          var el = $.find(_doc, $.selector(opts, true));
          var dataOnce = $.attr(_doc.body, 'data-once');

          // See blazy.load.js.
          // Ensure we have lazy elements after AJAX.
          if (el && $.contains(dataOnce, VARS.bRoot)) {
            $.once.unload = true;

            Drupal.detachBehaviors(_doc.body);

            $.once.removeSafely(VARS.bRoot, VARS.selector, _doc);

            Drupal.attachBehaviors(_doc.body);

            $.trigger('blazy:ajaxSuccess', [me, response, ajax]);
          }
        }

        // Remove listener.
        jq(_doc).off(VARS.eventName, revalidate);
        $.once.unload = false;

      }, 101);

    };

    // jQuery owned document, cannot use dBlazy.
    jq(_doc).on(VARS.eventName, revalidate);
  }

  /**
   * Attaches blazy AJAX behavior to body.
   *
   * Seperated from blazy.load.js, since blazy.load.js can be disabled, and
   * removed to use blazy.compat.js instead that is when No Javascript option is
   * enabled, but JS is still required beyond iframe or img tags such as by
   * background, local video or audio, or third party HTML lazyloadings.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyAjax = {
    attach: function (context) {

      var me = Drupal.blazy;

      $.once(process.bind(me), VARS.id, VARS.selector, context);

    },
    detach: function (context, _, trigger) {
      if (trigger === 'unload') {
        $.once.removeSafely(VARS.id, VARS.selector, context);
      }
    }
  };

})(dBlazy, jQuery, Drupal, this.document);
