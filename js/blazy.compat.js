/**
 * @file
 * Provides compat methods between Native and lazyload script.
 *
 * This is to fix for lost module features due to lazyload script being ditched:
 *   - Blur or animation in general with animate.css.
 *   - Multiple-breakpoint CSS background (DIV).
 *   - Multiple-breakpoint dynamic, or named Fluid, aspect ratio.
 *   - Local video.
 *   - Extra features: sub-module requirements. If using Slick/ Splide, etc., be
 *     sure to disable loading their loaders globally at their UIs as needed.
 */

(function ($, Drupal, _win) {

  'use strict';

  var ns = 'bcompat';
  var _elItem = '.b-lazy:not(.b-blur)';
  var _resizeEvent = 'resize.' + ns;
  var _roObserve = false;
  var roObserver = false;
  var roRaf = false;

  /**
   * Blazy public compat methods.
   *
   * @namespace
   */
  Drupal.blazy = $.extend(Drupal.blazy || {}, {

    checkResize: function (items, cb, root, onDone) {
      var me = this;
      var resizer = function (entries) {
        me.checkViewport();

        me.resizeTick++;
        return cb(entries);
      };

      // IE11 not supported, we'll provide a fallback.
      // @see https://caniuse.com/resizeobserver
      _roObserve = function () {
        return me._isRo ? new ResizeObserver(resizer) : resizer(items);
      };

      // Checks for aspect ratio, onload event is a bit later.
      // Uses ResizeObserver for modern browsers, else degrades.
      roObserver = _roObserve();
      if (items.length) {
        if (roObserver) {
          $.each(items, function (item) {
            roObserver.observe(item);
          });
        }
        else {
          $.bindEvent(_win, _resizeEvent, $.debounce(_roObserve));
        }
      }
      else {
        // At least provides viewport for other observers to detect visibility.
        $.debounce(resizer);
      }

      // When images are loaded, Flexbox or Native Grid as Masonry might need
      // info about the loaded image dimensions to calculate gaps or positions.
      if (onDone && $.isFun(onDone)) {
        me.onLoaded(root, onDone, roObserver);
      }
    },

    unresize: function () {
      if (!_roObserve) {
        $.unbindEvent(_win, _resizeEvent, _roObserve);
      }
      if (roRaf) {
        cancelAnimationFrame(roRaf);
      }
    }
  });

  /**
   * Processes DOM observations.
   */
  function process() {
    var me = this;

    me.items = $.findAll(me.context, _elItem);

    // Mount extensions.
    me.mount(true);
  }

  /**
   * Attaches blazy behavior to HTML elements.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyObserver = {
    attach: function (context) {

      var me = Drupal.blazy;
      me.context = $.context(context);

      $.once(process.call(me));

    },
    detach: function (context, settings, trigger) {
      if (trigger === 'unload') {
        var me = Drupal.blazy;
        var io = 'io' in me ? me.io() : false;
        var ro = 'ro' in me ? me.ro() : false;

        if (io) {
          io.unload();
        }
        if (ro) {
          ro.unload();
        }
      }
    }
  };

}(dBlazy, Drupal, this));
