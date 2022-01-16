/**
 * @file
 * Provides compat methods between Native and lazyload script.
 *
 * This file is not loaded if all below are not enabled.
 *
 * Mostly to fix for lost module features due to lazyload script being ditched:
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
  var _data = 'data-';
  var _dataAnimation = _data + 'animation';
  var _isAnimated = 'is-b-animated';
  var _media = 'media';
  var _elMedia = '.' + _media;
  var _opts = {};
  var _winData = {};
  var _roObserve = false;
  var roObserver = false;
  var roRaf = false;

  /**
   * Blazy public compat methods.
   *
   * @namespace
   */
  Drupal.blazy = $.extend(Drupal.blazy || {}, {

    clearCompat: function (el) {
      var me = this;
      var cn = $.closest(el, _elMedia) || el;

      var check = function () {
        // Only applies to aspect ratio fluid.
        if (me.isFluid(el, cn)) {
          updatePicture.call(me, el, cn);
        }

        animate(el);
      };

      // Fixed for effect Blur messes up Aspect ratio Fluid calculation.
      setTimeout(check);
    },

    winData: function () {
      return $.winData(_opts.mobileFirst);
    },

    checkResize: function (items, cb, root, onDone) {
      var me = this;
      var resizer = function (entries) {
        $.checkViewport(_opts.offset || 100);
        _winData = me.winData();

        me.resizeTick++;
        return cb(entries);
      };

      // IE11 not supported, we'll provide a fallback.
      // @see https://caniuse.com/resizeobserver
      _roObserve = function () {
        return $.isRo ? new ResizeObserver(resizer) : resizer(items);
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
        me.rebind(root, onDone, roObserver);
      }
      return _winData;
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

  // Private non-reusable functions.
  function updatePicture(el, cn) {
    var pad = Math.round(((el.naturalHeight / el.naturalWidth) * 100), 2);

    cn.style.paddingBottom = pad + '%';
  }

  /**
   * Callback function to animate blur, or any animated, element, if any.
   *
   * @param {Element} el
   *   The DIV or image element.
   */
  function animate(el) {
    // Blur, animate.css, for CSS background, picture, image, media.
    var an = $.closest(el, '[' + _dataAnimation + ']');
    if ($.hasAttr(el, _dataAnimation) && !$.isElm(an)) {
      an = el;
    }

    // Animate if any.
    if ($.isElm(an) && !$.hasClass(an, _isAnimated)) {
      setTimeout(function () {
        $.animate(an);
      }, 200);
    }
  }

  /**
   * Processes DOM observations.
   */
  function process() {
    var me = this;

    me.items = $.findAll(me.context, _elItem);

    // Mount extensions.
    me.mount(true);
    _opts = me.options;

    // @todo figure out potential conflict of interests, harmless, just useless.
    if (!_opts.loader || _opts.compat) {
      me.init = me.run(_opts);
    }
  }

  /**
   * Attaches blazy behavior to HTML elements.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyCompat = {
    attach: function (context) {

      var me = Drupal.blazy;
      me.context = $.context(context);

      $.once(process.call(me));

    },
    detach: function (context, settings, trigger) {
      if (trigger === 'unload') {
        var me = Drupal.blazy;
        var ro = 'ro' in me ? me.ro() : false;

        if (ro) {
          ro.unload();
        }
      }
    }
  };

}(dBlazy, Drupal, this));
