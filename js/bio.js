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
  var _disconnected = false;
  var _observed = false;

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

    // Initializes Blazy IntersectionObserver.
    init(me);
  }

  // Cache our prototype.
  var _proto = Bio.prototype;
  _proto.constructor = Bio;

  // BC for interchanging with bLazy.
  _proto.load = function (elms) {
    var me = this;

    // Manually load elements regardless of being disconnected, or not, relevant
    // for Slick slidesToShow > 1 which rebuilds clones of unloaded elements.
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

    if (!_disconnected) {
      me.disconnect();
    }
  };

  _proto.isLoaded = function (el) {
    return el.classList.contains(this.opts.successClass);
  };

  _proto.isValid = function (el) {
    return typeof el === 'object' && typeof el.length === 'undefined' && !this.isLoaded(el);
  };

  _proto.prepare = function () {
    // Do nothing, let extenders do their jobs.
  };

  _proto.revalidate = function (force) {
    var me = this;

    // Prevents from too many revalidations unless needed.
    if ((me.count !== me.counted || force === true) && (_revTick < me.counted)) {
      me.observe();

      _revTick++;
    }
  };

  _proto.intersecting = function (el) {
    var me = this;

    me.lazyLoad(el);
    me.counted++;

    if (!_disconnected) {
      me.observer.unobserve(el);
    }
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

  _proto.setAttrs = function (el, attrs) {
    var me = this;

    _db.forEach(attrs, function (src) {
      me.setAttr(el, src);
    });
  };

  _proto.setAttr = function (el, attr, remove) {
    if (el.hasAttribute('data-' + attr)) {
      el.setAttribute(attr, el.getAttribute('data-' + attr));
      if (remove) {
        el.removeAttribute('data-' + attr);
      }
    }
  };

  _proto.equal = function (el, str) {
    return el.nodeName.toLowerCase() === str;
  };

  _proto.observe = function () {
    var me = this;

    _bioTick = me.elms.length;
    _db.forEach(me.elms, function (entry) {
      // Only observes if not already loaded.
      if (!me.isLoaded(entry)) {
        me.observer.observe(entry);
      }
    });
  };

  _proto.observing = function (entries, observer) {
    var me = this;

    me.entries = entries;
    if (_disconnected) {
      return;
    }

    // Load each on entering viewport.
    _db.forEach(entries, function (entry) {
      if (typeof me.opts.observing === 'function') {
        me.opts.observing(entry, observer, me.opts);
      }

      if (entry.intersectionRatio > 0 || entry.isIntersecting) {
        if (!me.isLoaded(entry.target)) {
          me.intersecting(entry.target);
        }

        _bioTick--;
      }
    });

    me.disconnect();
  };

  _proto.disconnect = function () {
    var me = this;

    // Disconnect when all entries are loaded, if so configured.
    if ((_bioTick === 0 || me.count === me.counted) && me.opts.disconnect) {
      me.observer.disconnect();
      _disconnected = true;
    }
  };

  function init(me) {
    var config = {
      rootMargin: me.opts.rootMargin,
      threshold: me.opts.threshold
    };

    // Initializes the IO.
    me.observer = new IntersectionObserver(me.observing.bind(me), config);

    // Observes once on the page load regardless multiple observer instances.
    // Possible as we nullify the root option to allow querying the DOM once.
    if (!_observed) {
      me.observe();

      _observed = true;
    }
  }

  return Bio;

});
