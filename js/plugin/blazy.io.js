/**
 * @file
 * Provides IntersectionObserver with fallback extension for Drupal.blazy.
 *
 * @bigtodo merge with [Bio]Media to minimize dups, and more reliable events.
 */

(function ($, Drupal, _win) {

  'use strict';

  var _b = Drupal.blazy || {};
  var ns = 'bcompat';
  var _id = 'blazy';
  var _data = 'data';
  var _dataAnimation = _data + '-animation';
  var _dataDimensions = _data + '-dimensions';
  var _dataBg = _data + '-b-bg';
  var _media = 'media';
  var _picture = 'picture';
  var _bloaded = 'b-loaded';
  var _isAnimated = 'is-b-animated';
  var _isLoaded = 'is-' + _bloaded;
  var _isVisible = 'is-b-visible';
  var _elMedia = '.' + _media;
  var _scrollEvent = 'scroll.' + ns;
  var _ioObserve = false;
  var ioObserver = false;
  var ioRaf = false;
  var ioQueue = [];

  // @todo re-use and move it into bio.js instead, unreliable with large images.
  function onIntersecting(el, cn) {
    var me = this;
    var done = false;
    var isFluid;

    if (!$.isElm(el)) {
      return done;
    }

    isFluid = $.equal(el.parentNode, _picture) && $.hasAttr(cn, _dataDimensions);

    // Makes multi-breakpoint BG work for Native, IO or bLazy once.
    // Native doesn't support lazyloading DIV as of this writing (22/1).
    // The load/error events are applicable to IMG, IFRAME, VIDEO, not DIV.
    // The minimal of the library IO/bLazy without preloading, decoding, etc.
    if ($.hasAttr(cn, _dataBg) && $.isFun($.bg)) {
      $.bg(cn, me.winData());
      onLoaded(cn);
      done = true;
    }

    var check = function () {
      if (me.isLoaded(el) || me.isLoaded(cn)) {
        // Adds context for effetcs: blur, etc. considering BG, or just media.
        $.addClass($.hasClass(cn, _media) ? cn : el, _isLoaded);

        // Only applies to aspect ratio fluid.
        if (isFluid) {
          updatePicture.call(me, el, cn);
        }

        onVisible(el);
        done = true;
      }
    };

    // Fixed for effect Blur messes up Aspect ratio Fluid calculation.
    setTimeout(check);
    return done;
  }

  /**
   * Callback function when the element is loaded.
   *
   * Native may trigger onload event before the element is visible on viewport,
   * do not do anything relying on viewport visibility here: animate, etc.
   *
   * @param {Element|Event} e
   *   The .b-bg element (DIV), or event (IMG) if onload|onerror triggered.
   *
   * @todo re-use and move it into bio.js instead, unreliable with large images.
   */
  function onLoaded(e) {
    var target = e.target;
    var el = target || e;

    // Fallback without libraries.
    if (!$.hasClass(el, _bloaded)) {
      $.addClass(el, _bloaded);
    }
  }

  /**
   * Callback function to animate blur, or any animated, element, if any.
   *
   * @param {Element} el
   *   The DIV or image element.
   *
   * @todo re-use and move it into bio.js instead, unreliable with large images.
   */
  function onVisible(el) {
    // Blur, animate.css, for CSS background, picture, image, media.
    var an = $.closest(el, '[' + _dataAnimation + ']');
    if ($.hasAttr(el, _dataAnimation) && !$.isElm(an)) {
      an = el;
    }

    // Animate if any.
    if ($.isElm(an) && !$.hasClass(an, _isAnimated)) {
      var animate = function () {
        $.animate(an);
      };

      // Good: Regular image has onload event.
      // Bad: Background image without libraries has no success event, this is
      // assumption, else blank.
      // @todo a more reliable one for large images, or re-use bio.media.
      setTimeout(animate, 200);
    }
  }

  // Private non-reusable functions.
  function updatePicture(el, cn) {
    var me = this;
    var pad = Math.round(((el.naturalHeight / el.naturalWidth) * 100), 2);
    var elms = me.instances;
    var isResized = me.resizeTick > 1;

    cn.style.paddingBottom = pad + '%';

    // Swap all aspect ratio once to reduce abrupt ratio changes for the rest.
    // This triggers a one time event to apply fixes at each .blazy container
    // once after the first resizeTick is emitted.
    // @todo remove to not support resizing to minimize complication.
    // @todo move it into ResizeObserver if not, and doable.
    if (elms.length && isResized) {
      var picture = function (root) {
        if (root.dblazy && root.dbuniform) {
          if ((root.dblazy === cn.dblazy) && !root.dbpicture) {
            $.trigger(root, _id + '.uniform.' + root.dblazy, {
              pad: pad
            });
            root.dbpicture = true;
          }
        }
      };

      // Uniform sizes must apply to each instance, not globally.
      $.each(elms, function (elm) {
        $.debounce(picture(elm));
      }, me);
    }
  }

  /**
   * Processes intersection.
   *
   * @param {Object} entries
   *   The intersected elements.
   *
   * @return {bool}
   *   Returns false to identify IO is not supported by default.
   *
   * @todo re-use and move it into bio.js instead, unreliable with large images.
   */
  function intersect(entries) {
    var me = this;
    var viewport = me.viewport;

    entries.map(function (entry) {
      var target = entry.target;
      var el = target || entry;
      var cn = $.closest(el, _elMedia) || el;
      var done = false;

      var visible = target ? (entry.isIntersecting || entry.intersectionRatio > 0) : $.isVisible(el, viewport);

      // To make efficient blur filter via CSS, etc.
      $[visible ? 'addClass' : 'removeClass'](cn, _isVisible);
      if (visible) {
        done = onIntersecting.call(me, el, cn);
      }

      // Stop watching when no longer needed.
      if ($.hasClass(cn, _isAnimated) || $.hasClass(cn, _isLoaded) || done) {
        $.removeClass(cn, _isVisible);

        if (ioObserver) {
          ioObserver.unobserve(el);
        }
      }
    });
    return false;
  }

  /**
   * Processes blur elements, if any.
   *
   * @return {Object}
   *   Returns public methods.
   *
   * @todo re-use and move it into bio.js instead, unreliable with large images.
   */
  function io() {
    var me = this;
    var items = me.items;

    function _intersect(entries) {
      if (!ioQueue.length) {
        ioRaf = requestAnimationFrame(_enqueue);
      }

      ioQueue.push(entries);

      return false;
    }

    function _enqueue() {
      $.enqueue(ioQueue, intersect, me);
    }

    // IE11 not supported, we'll provide a fallback.
    // @see https://caniuse.com/IntersectionObserver
    _ioObserve = function () {
      return $.isIo ? new IntersectionObserver(_intersect) : _intersect(items);
    };

    // Uses IntersectionObserver for modern browsers, else degrades.
    ioObserver = _ioObserve();

    if (items.length) {
      if (ioObserver) {
        $.each(items, function (item) {
          ioObserver.observe(item);

          // moObserver.observe(item, configMutation);
        });
      }
      else {
        $.bindEvent(_win, _scrollEvent, $.debounce(_ioObserve));
      }

      // @todo hook into Bio to DRY.
      // me.init = me.run(me.options);
    }

    return {
      unload: function () {
        if (!_ioObserve) {
          $.unbindEvent(_win, _scrollEvent, _ioObserve);
        }
        if (ioRaf) {
          cancelAnimationFrame(ioRaf);
        }
      }
    };
  }

  $.onIntersecting = onIntersecting;
  _b.extend({
    io: io
  });

}(dBlazy, Drupal, this));
