/**
 * @file
 * Provides Intersection Observer API loader.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
 * @see https://developers.google.com/web/updates/2016/04/intersectionobserver
 */

/* global define, module */
(function (root, factory) {

  'use strict';

  // Inspired by https://github.com/addyosmani/memoize.js/blob/master/memoize.js
  if (typeof define === 'function' && define.amd) {
    // AMD. Register as an anonymous module.
    define([root.dBlazy], factory);
  }
  else if (typeof exports === 'object') {
    // Node. Does not work with strict CommonJS, but only CommonJS-like
    // environments that support module.exports, like Node.
    module.exports = factory(root.dBlazy);
  }
  else {
    // Browser globals (root is window).
    root.Bio = factory(root.dBlazy);
  }
})(this, function (dBlazy) {

  'use strict';

  /**
   * Private variables.
   */
  var _doc = document;
  var $ = dBlazy;
  var _bioTick = 0;
  var _revTick = 0;
  var _counted = 0;
  var _erCounted = 0;
  var _disconnected = false;
  var _observed = false;

  /**
   * Constructor for Bio, Blazy IntersectionObserver.
   *
   * @param {object} options
   *   The Bio options.
   *
   * @return {Bio}
   *   The Bio instance.
   *
   * @namespace
   */
  function Bio(options) {
    var me = this;

    if (arguments.length && 'selector' in arguments[0]) {
      me.options = $.extend({}, me.defaults, arguments[0] || {});

      // Initializes Blazy IntersectionObserver.
      _disconnected = false;
      _observed = false;
      return init(me);
    }

    return me;
  }

  // Cache our prototype.
  var _proto = Bio.prototype;
  _proto.constructor = Bio;

  // Prepare prototype to interchange with Blazy as fallback.
  _proto.count = 0;
  _proto._er = -1;
  _proto._ok = 1;
  _proto.defaults = {
    root: null,
    decode: false,
    disconnect: false,
    error: false,
    success: false,
    intersecting: false,
    observing: false,
    successClass: 'b-loaded',
    selector: '.b-lazy',
    errorClass: 'b-error',
    bgClass: 'b-bg',
    rootMargin: '0px',
    threshold: [0]
  };

  // BC for interchanging with bLazy.
  _proto.load = function (elms) {
    var me = this;

    // Manually load elements regardless of being disconnected, or not, relevant
    // for Slick slidesToShow > 1 which rebuilds clones of unloaded elements.
    if (me.isValid(elms)) {
      me.intersecting(elms);
    }
    else {
      $(elms).each(function (el) {
        if (me.isValid(el)) {
          me.intersecting(el);
        }
      });
    }

    if (!_disconnected) {
      me.disconnect();
    }
  };

  _proto.selector = function (suffix) {
    suffix = suffix || '';
    var opts = this.options;
    return opts.selector + suffix + ':not(.' + opts.successClass + ')';
  };

  _proto.isLoaded = function (el) {
    return $.hasClass(el, this.options.successClass);
  };

  _proto.isValid = function (el) {
    return $.isElm(el) && $.isUnd(el.length) && !this.isLoaded(el);
  };

  _proto.prepare = function () {
    // Do nothing, let extenders do their jobs.
  };

  _proto.revalidate = function (force) {
    var me = this;

    // Prevents from too many revalidations unless needed.
    if ((force === true || me.count !== _counted) && (_revTick < _counted)) {
      _disconnected = false;
      me.elms = $(me.options.root || _doc).findAll(me.selector());
      if (me.elms.length) {
        me.observe();

        _revTick++;
      }
    }
  };

  _proto.intersecting = function (el) {
    var me = this;
    var opts = me.options;

    // If not extending/ overriding, at least provide the option.
    if ($.isFun(opts.intersecting)) {
      opts.intersecting(el, opts);
    }

    // Be sure to throttle, or debounce your method when calling this.
    $.trigger(el, 'bio.intersecting', {
      options: opts
    });

    me.lazyLoad(el);
    _counted++;

    if (!_disconnected) {
      me.observer.unobserve(el);
    }
  };

  _proto.lazyLoad = function (el) {
    // Do nothing, let extenders do their own lazy, can be images, AJAX, etc.
  };

  _proto.success = function (el, status, parent) {
    var me = this;
    var opts = me.options;

    if ($.isFun(opts.success)) {
      opts.success(el, status, parent, opts);
    }

    if (_erCounted > 0) {
      _erCounted--;
    }
  };

  _proto.error = function (el, status, parent) {
    var me = this;
    var opts = me.options;

    if ($.isFun(opts.error)) {
      opts.error(el, status, parent, opts);
    }

    _erCounted++;
  };

  _proto.loaded = function (el, status, parent) {
    var me = this;
    var opts = me.options;

    $.addClass(el, status === me._ok ? opts.successClass : opts.errorClass);
    me[status === me._ok ? 'success' : 'error'](el, status, parent);
  };

  _proto.observe = function () {
    var me = this;

    _bioTick = me.elms.length;
    $.each(me.elms, function (entry) {
      // Only observes if not already loaded.
      if (!me.isLoaded(entry)) {
        me.observer.observe(entry);
      }
    });
  };

  _proto.observing = function (entries, observer) {
    var me = this;
    var opts = me.options;

    me.check();

    me.entries = entries;
    // Stop watching if already disconnected.
    if (_disconnected) {
      return;
    }

    // Load each on entering viewport.
    $.each(entries, function (entry) {
      // Provides option such as to animate bg or elements regardless position.
      if ($.isFun(opts.observing)) {
        opts.observing(entry, observer, opts);
      }

      // The element is being intersected.
      if (entry.isIntersecting || entry.intersectionRatio > 0) {
        if (!me.isLoaded(entry.target)) {
          me.intersecting(entry.target);
        }

        _bioTick--;
      }
    });

    // Disconnect when all is done.
    me.disconnect();
  };

  _proto.disconnect = function (force) {
    var me = this;

    // Do not disconnect if any error found.
    if (_erCounted > 0 && !force) {
      return;
    }

    // Disconnect when all entries are loaded, if so configured.
    if (((_bioTick === 0 || me.count === _counted) && me.options.disconnect) || force) {
      me.observer.disconnect();
      me.count = 0;
      me.elms = [];
      _disconnected = true;
    }
  };

  _proto.check = function () {
    var me = this;

    if (!_disconnected) {
      var check = $.find(me.options.root || _doc, me.selector());
      if ($.isNull(check) || check.length === 0) {
        me.destroy(true);
      }
    }
  };

  _proto.destroy = function (force) {
    var me = this;
    me.disconnect(force);
    me.observer = null;
  };

  _proto.disconnected = function () {
    return _disconnected;
  };

  _proto.reinit = function () {
    _disconnected = false;
    _observed = false;
    init(this);
  };

  function init(me) {
    var opts = me.options;
    var config = {
      rootMargin: opts.rootMargin,
      threshold: opts.threshold
    };

    me.elms = $.findAll(opts.root || _doc, me.selector());
    me.count = me.elms.length;
    me.windowWidth = $.windowWidth();

    me.prepare();

    // Initializes the IO.
    me.observer = new IntersectionObserver(me.observing.bind(me), config);

    // Observes once on the page load regardless multiple observer instances.
    // Possible as we nullify the root option to allow querying the DOM once.
    // Should you need to re-validate, or re-observe, just call ::observe().
    if (!_observed) {
      me.observe();
      _observed = true;
    }
    return me;
  }

  return Bio;

});
