/**
 * @file
 * Provides [Intersection|Resize]Observer extensions.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 */

(function ($, _win) {

  'use strict';

  $.ww = 0;
  $.vp = {};

  /**
   * Returns element visibility.
   *
   * @private
   *
   * @param {Element} el
   *   The HTML element to test.
   * @param {Object} vp
   *   The window viewport.
   *
   * @return {bool}
   *   Returns true if visible.
   */
  function isVisible(el, vp) {
    var rect = el.getBoundingClientRect();

    return ((rect.top > vp.top || rect.bottom > 0) && rect.top < vp.bottom);
  }

  $.isVisible = function (e, vp) {
    var target = e.target;
    var el = target || e;
    return target ? (e.isIntersecting || e.intersectionRatio > 0) : isVisible(el, vp);
  };

  $.isResized = function (scope, e) {
    return (!!e.contentRect || !!scope.resizeTrigger || false);
  };

  $.winData = function (mobileFirst) {
    var me = this;
    return {
      vp: me.vp,
      ww: me.ww,
      up: mobileFirst || false
    };
  };

  $.checkWindow = function (offset, mobileFirst) {
    var me = this;
    me.vp = $.viewport(offset || 100);
    me.ww = me.vp.right - offset;
    return me.winData(mobileFirst);
  };

  $.interact = function (scope, cb, elms, withIo) {
    var me = this;
    var opts = scope.options || {};
    var queue = scope._queue || [];
    var resizeTrigger;
    var data = {};

    var config = {
      rootMargin: opts.rootMargin || '0px',
      threshold: opts.threshold || 0
    };

    elms = $.toArray(elms);

    function _cb(entries) {
      if (!queue.length) {
        scope._raf = requestAnimationFrame(_enqueue);
      }

      queue.push(entries);

      // Default to old browsers.
      return false;
    }

    function _enqueue() {
      $.enqueue(queue, cb, scope);
    }

    // IntersectionObserver for modern browsers, else degrades for IE11, etc.
    // @see https://caniuse.com/IntersectionObserver
    if (withIo) {
      var _ioObserve = function () {
        return $.isIo ? new IntersectionObserver(_cb, config) : cb.call(scope, elms);
      };

      scope.ioObserver = _ioObserve();
    }

    // IntersectionObserver for modern browsers, else degrades for IE11, etc.
    // @see https://caniuse.com/ResizeObserver
    // @see https://developer.mozilla.org/en-US/docs/Web/API/ResizeObserver
    var _roObserve = function () {
      resizeTrigger = this;

      // Called once during page load, not called during resizing.
      data = me.checkWindow(opts.offset || 100, opts.mobileFirst);
      return $.isRo ? new ResizeObserver(_cb) : cb.call(scope, elms);
    };

    scope.roObserver = _roObserve();
    scope.resizeTrigger = resizeTrigger;

    return data;
  };

  $.observe = function (scope, elms, withIo, unblazy) {
    var ns = scope.name || this.name;
    var opts = scope.options || {};
    var ioObserver = scope.ioObserver;
    var roObserver = scope.roObserver;
    var delay = opts.validateDelay || 200;

    var observe = function (observer) {
      if (observer) {
        $.each(elms, function (entry) {
          observer.observe(entry);
        });
      }
    };

    if (ioObserver || roObserver) {
      // Allows observing resize only.
      if (withIo) {
        observe(ioObserver);
      }

      observe(roObserver);
    }
    else {
      // Blazy was not designed with Native lazy, can be removed via Blazy UI.
      if ('Blazy' in _win && !unblazy) {
        new Blazy(opts);
      }
      else {
        // The best thing we can do other than harsh ::load().
        var bind = function (evt, cb) {
          $.bindEvent(_win, evt, function (e) {
            $.throttle(cb.call(e), delay, scope);
          });
        };
        bind('resize.' + ns, roObserver);
        bind('scroll.' + ns, ioObserver);
      }
    }
    return scope;
  };

  $.unload = function (scope) {
    var ns = scope.name || this.name;
    if (!$.isIo) {
      $.unbindEvent(_win, 'scroll.' + ns, scope.ioObserver);
    }
    if (scope._raf) {
      cancelAnimationFrame(scope._raf);
    }
  };


})(dBlazy, this);
