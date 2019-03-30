/**
 * @file
 * Provides Intersection Observer API loader.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
 * @see https://developers.google.com/web/updates/2016/04/intersectionobserver
 */

/* global window, document, define, module */
(function (root, factory) {

  'use strict';

  // Inspired by https://github.com/addyosmani/memoize.js/blob/master/memoize.js
  if (typeof define === 'function' && define.amd) {
    // AMD. Register as an anonymous module.
    define([window.dBlazy], factory);
  }
  else if (typeof exports === 'object') {
    // Node. Does not work with strict CommonJS, but only CommonJS-like
    // environments that support module.exports, like Node.
    module.exports = factory(window.dBlazy);
  }
  else {
    // Browser globals (root is window).
    root.Bio = factory(window.dBlazy);
  }
})(this, function (dBlazy) {

  'use strict';

  /**
   * Private variables.
   */
  var _win = window;
  var _doc = document;
  var _db = dBlazy;
  var _bioTick = 0;
  var _revTick = 0;

  // PolyFill `isIntersecting` for Microsoft Edge 15 isIntersecting property.
  // https://github.com/WICG/IntersectionObserver/issues/211#issuecomment-309144669
  if ('IntersectionObserver' in _win &&
    'IntersectionObserverEntry' in _win &&
    'intersectionRatio' in _win.IntersectionObserverEntry.prototype &&
    !('isIntersecting' in IntersectionObserverEntry.prototype)) {

    Object.defineProperty(_win.IntersectionObserverEntry.prototype, 'isIntersecting', {
      get: function () {
        return this.intersectionRatio > 0;
      }
    });
  }

  /**
   * Constructor for Bio, Blazy IntersectionObserver.
   *
   * @param {object} options
   *   The Bio options.
   *
   * @namespace
   */
  function Bio(options) {
    var me = this;
    var defaults = {
      root: null,
      disconnect: false,
      error: false,
      success: false,
      observing: false,
      successClass: 'b-loaded',
      selector: '.b-lazy',
      errorClass: 'b-error',
      bgClass: 'b-bg',
      rootMargin: '0px',
      threshold: [0]
    };

    me.opts = _db.extend({}, defaults, options || {});
    me.opts.selector = me.opts.selector + ':not(.' + me.opts.successClass + ')';
    me.elms = (me.opts.root || _doc).querySelectorAll(me.opts.selector);
    me.count = me.elms.length;
    me.counted = 0;
    me._er = -1;
    me._ok = 1;
    me.windowWidth = _win.innerWidth || _doc.documentElement.clientWidth || _doc.body.clientWidth || _win.screen.width;

    me.prepare();

    // Initialize Blazy IntersectionObserver.
    init(me);
  }

  // Cache our prototype.
  var _proto = Bio.prototype;
  _proto.constructor = Bio;

  // BC for interchanging with bLazy with Slick slidesToShow > 1 clones.
  _proto.load = function (elms) {
    var me = this;

    if (me.isValid(elms)) {
      me.intersecting(elms);
    }
    else {
      _db.forEach(elms, function (el) {
        if (me.isValid(el)) {
          me.intersecting(el);
        }
      });
    }
  };

  _proto.isValid = function (el) {
    return typeof el === 'object' && typeof el.length === 'undefined' && !el.classList.contains(this.opts.successClass);
  };

  _proto.prepare = function () {
    // Do nothing, let extenders do their jobs.
  };

  _proto.revalidate = function (force) {
    var me = this;

    // No need to execute unless required such as by Slick slide changes.
    // Prevents from too many revalidations due to always-rebuilt slick-clones.
    if (((typeof force === 'undefined' && me.count !== me.counted) || force === true) && (_revTick < me.counted)) {
      me.observe(true);

      _revTick++;
    }
  };

  _proto.intersecting = function (el) {
    var me = this;

    me.lazyLoad(el);
    me.observer.unobserve(el);
    me.counted++;
  };

  _proto.lazyLoad = function (el) {
    // Do nothing, let extenders do their own lazy, can be images, AJAX, etc.
  };

  _proto.success = function (el) {
    var me = this;

    if (typeof me.opts.success === 'function') {
      me.opts.success(el, me.opts);
    }
  };

  _proto.error = function (el) {
    var me = this;

    if (typeof me.opts.error === 'function') {
      me.opts.error(el, me.opts);
    }
  };

  _proto.loaded = function (el, status) {
    var me = this;

    me[status === me._ok ? 'success' : 'error'](el);
    el.classList.add(status === me._ok ? me.opts.successClass : me.opts.errorClass);
  };

  _proto.removeAttrs = function (el, attrs) {
    _db.forEach(attrs, function (attr) {
      el.removeAttribute('data-' + attr);
    });
  };

  _proto.setAttrs = function (el, attrs, tag, tagSrc) {
    var me = this;

    _db.forEach(attrs, function (src) {
      me.setAttr(el, src, tag, tagSrc);
    });
  };

  _proto.setAttr = function (el, attr, tag, tagSrc) {
    if (el.hasAttribute('data-' + attr)) {
      el.setAttribute(attr, el.getAttribute('data-' + attr));
      if (typeof tag !== 'undefined') {
        tag.src = el.getAttribute(tagSrc);
      }
    }
  };

  _proto.equal = function (el, str) {
    return el.nodeName.toLowerCase() === str;
  };

  _proto.observe = function (revalidate) {
    var me = this;

    _bioTick = me.elms.length;
    _db.forEach(me.elms, function (entry) {
      // Only observes if not already loaded.
      if (!entry.classList.contains(me.opts.successClass) || revalidate === true) {
        me.instance.observe(entry);
      }
    });
  };

  _proto.observing = function (entries, observer) {
    var me = this;

    me.entries = entries;
    me.observer = observer;

    // Load each on entering viewport.
    _db.forEach(entries, function (entry) {
      if (typeof me.opts.observing === 'function') {
        me.opts.observing(entry, observer, me.opts);
      }

      if (entry.isIntersecting) {
        if (!entry.target.classList.contains(me.opts.successClass)) {
          me.intersecting(entry.target);
        }

        _bioTick--;
      }
    });

    // Disconnect when all entries are loaded, if so configured.
    if ((_bioTick === 0 || me.count === me.counted) && me.opts.disconnect) {
      observer.disconnect();
    }
  };

  function init(me) {
    var config = {
      rootMargin: me.opts.rootMargin,
      threshold: me.opts.threshold
    };

    // Initialize the IO.
    me.instance = new IntersectionObserver(function (entries, observer) {
      me.observing(entries, observer);
    }, config);

    // Start observing entries.
    me.observe(false);
  }

  return Bio;

});
