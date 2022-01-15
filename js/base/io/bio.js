/**
 * @file
 * Provides Intersection Observer API loader.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
 * @see https://developers.google.com/web/updates/2016/04/intersectionobserver
 * @todo refactor to fallback to native right here, not on the loaders, to avoid
 * all or nothing, and degrades gracefully. Or use polyfill.
 * @see https://www.npmjs.com/package/intersection-observer
 * @see https://github.com/w3c/IntersectionObserver
 */

/* global define, module */
(function (root, factory) {

  'use strict';

  var ns = 'Bio';
  var db = root.dBlazy;

  // Inspired by https://github.com/addyosmani/memoize.js/blob/master/memoize.js
  if (db.isAmd) {
    // AMD. Register as an anonymous module.
    define([ns, db, root], factory);
  }
  else if (typeof exports === 'object') {
    // Node. Does not work with strict CommonJS, but only CommonJS-like
    // environments that support module.exports, like Node.
    module.exports = factory(ns, db, root);
  }
  else {
    // Browser globals (root is window).
    root[ns] = factory(ns, db, root);
  }

}((this || module || {}), function (ns, $, root) {

  'use strict';

  if ($.isAmd) {
    root = window;
  }

  /**
   * Private variables.
   */
  var _doc = document;
  var _bioTick = 0;
  var _revTick = 0;
  var _counted = 0;
  var _erCounted = 0;
  var _disconnected = false;
  var _observed = false;
  var _noop = function () {};
  var _opts = {};
  var _elms = [];
  var _observer = null;
  var _successClass = 'b-loaded';
  var _errorClass = 'b-error';
  var _root = _doc;
  var _defaults = {
    root: null,
    decode: false,
    disconnect: false,
    error: false,
    success: false,
    intersecting: false,
    observing: false,
    mobileFirst: false,
    bgClass: 'b-bg',
    successClass: _successClass,
    errorClass: _errorClass,
    selector: '.b-lazy',
    offset: 100,
    rootMargin: '0px',
    threshold: [0]
  };

  // Cache our prototype.
  var fn = Bio.prototype;
  fn.constructor = Bio;

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
    var me = $.map(fn, this);

    me.name = ns;
    me.options = _opts = $.extend({}, _defaults, options || {});

    _successClass = _opts.successClass || _successClass;
    _errorClass = _opts.errorClass || _errorClass;
    _root = _opts.root || _root;

    return me.reinit();
  }

  // Prepare prototype to interchange with Blazy as fallback.
  fn.count = 0;
  fn.ww = 0;
  fn.vp = {};
  fn.prepare = _noop;
  fn.lazyLoad = _noop;

  // BC for interchanging with bLazy.
  fn.load = function (elms) {
    var me = this;

    // Manually load elements regardless of being disconnected, or not, relevant
    // for Slick slidesToShow > 1 which rebuilds clones of unloaded elements.
    $.each($.toArray(elms), function (el) {
      if (me.isValid(el)) {
        intersecting.call(me, el);
      }
    });

    me.check();
  };

  fn.selector = function (suffix) {
    suffix = suffix || '';
    return _opts.selector + suffix + ':not(.' + _successClass + ')';
  };

  fn.isLoaded = function (el) {
    return $.hasClass(el, _successClass);
  };

  fn.isValid = function (el) {
    return $.isElm(el) && !this.isLoaded(el);
  };

  fn.success = function (el, status, parent) {
    if ($.isFun(_opts.success)) {
      _opts.success(el, status, parent, _opts);
    }

    if (_erCounted > 0) {
      _erCounted--;
    }
  };

  fn.error = function (el, status, parent) {
    if ($.isFun(_opts.error)) {
      _opts.error(el, status, parent, _opts);
    }

    _erCounted++;
  };

  fn.revalidate = function (force) {
    var me = this;

    // Prevents from too many revalidations unless needed.
    if ((force === true || me.count !== _counted) && (_revTick < _counted)) {
      _disconnected = false;
      me.elms = _elms = $.findAll(_root, me.selector());
      if (_elms.length) {
        me.observe();

        _revTick++;
      }
    }
  };

  fn.loaded = function (el, status, parent) {
    var me = this;

    $.addClass(el, status === $._ok ? _successClass : _errorClass);
    me[status === $._ok ? 'success' : 'error'](el, status, parent);

    $.trigger(el, 'bio.loaded', {
      status: status
    });
  };

  fn.viewport = function (data) {
    var me = this;
    me.vp = data.vp || {};
    me.ww = data.ww || {};
  };

  fn.observe = function () {
    var me = this;

    _bioTick = _elms.length;

    if (_observer) {
      $.each(_elms, function (entry) {
        _observer.observe(entry);
      });
    }
  };

  fn.check = function (force) {
    var me = this;

    // Infinite pager like IO wants to keep monitoring infinite contents.
    if (!_disconnected && (force || $.isUnd(Drupal.io))) {
      var check = $.find(_root, me.selector());

      if (!$.isElm(check)) {
        me.destroy(true);
      }
    }
  };

  fn.destroy = function (force) {
    var me = this;
    disconnect.call(me, force);
    me.observer = _observer = null;
  };

  fn.reinit = function () {
    var me = this;

    _disconnected = false;
    _observed = false;

    return init(this);
  };

  function disconnect(force) {
    var me = this;

    // Do not disconnect if any error found.
    if (_erCounted > 0 && !force) {
      return;
    }

    // Disconnect when all entries are loaded, if so configured.
    var done = ((_bioTick === 0 || me.count === _counted) && _opts.disconnect);
    if (done || force) {
      if (_observer) {
        _observer.disconnect();
      }

      me.count = 0;
      me.elms = _elms = [];
      _disconnected = true;
    }
  }

  function intersecting(el) {
    var me = this;

    // Makes sure to have media loaded beforehand.
    me.lazyLoad(el);

    // If not extending/ overriding, at least provide the option.
    if ($.isFun(_opts.intersecting)) {
      _opts.intersecting(el, opts);
    }

    // If not extending/ overriding, also allows to listen to.
    $.trigger(el, 'bio.intersecting', {
      options: me.options
    });

    if (_observer) {
      _observer.unobserve(el);
    }

    _counted++;
  }

  function observing(entries, observer) {
    var me = this;

    me.check();

    // Stop watching if already disconnected.
    if (_disconnected) {
      return;
    }

    // Load each on entering viewport.
    $.each(entries, function (entry) {
      var el = entry.target || entry;
      var visible = entry.isIntersecting || entry.intersectionRatio > 0;

      // Provides option such as to animate bg or elements regardless position.
      if ($.isFun(_opts.observing)) {
        _opts.observing(entry, observer, _opts);
      }

      // The element is being intersected.
      if (visible) {
        if (!me.isLoaded(el)) {
          intersecting.call(me, el);
        }

        _bioTick--;
      }
    });
  }

  function init(me) {
    var config = {
      rootMargin: _opts.rootMargin,
      threshold: _opts.threshold
    };

    me.elms = _elms = $.findAll(_root, me.selector());
    me.count = _elms.length;

    me.prepare();

    // Initializes the IO.
    if ($.isIo) {
      me.observer = _observer = new IntersectionObserver(observing.bind(me), config);

      // Observes once on the page load regardless multiple observer instances.
      // Possible as we nullify the root option to allow querying the DOM once.
      // Should you need to re-validate, or re-observe, just call ::observe().
      if (!_observed) {
        me.observe();
        _observed = true;
      }
    }
    else {
      // @todo refine, since old bLazy was not designed for Native.
      if ('Blazy' in root) {
        me.blazy = new Blazy(_opts);
      }
      else {
        // @todo refine, too harsh and cheap like them. At least no error.
        me.load(_elms);
      }
    }

    return me;
  }

  root.bio = function (options) {
    return new Bio(options);
  };

  return Bio;

}));
