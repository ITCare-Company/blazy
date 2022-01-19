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
  var _root = _doc;
  var _winData = {};
  var _bioTick = 0;
  var _ww = 0;
  var _revTick = 0;
  var _counted = 0;
  var _erCounted = 0;
  var _disconnected = false;
  var _observed = false;
  var _noop = function () {};
  var _opts = {};
  var _elms = [];
  var _successClass = 'b-loaded';
  var _errorClass = 'b-error';
  var _bgClass = 'b-bg';
  var _isVisible = 'is-b-visible';
  var _isLoaded = 'is-' + _successClass;
  var _isError = 'is-' + _errorClass;
  var _media = 'media';
  var _parent = '.' + _media;
  var _data = 'data-';
  var _src = 'src';
  var _srcSet = 'srcset';
  var _dataSrc = _data + _src;
  var _dataSrcset = _data + _srcSet;
  var _bgSources = [_src];
  var _imgSources = [_srcSet, _src];
  var _resizing = false;
  var _validateDelay = 25;
  var _ioObserver = null;
  var _raf = false;
  var _queue = [];
  var _defaults = {
    root: null,
    decode: false,
    disconnect: false,
    error: false,
    success: false,
    intersecting: false,
    observing: false,
    resizing: false,
    mobileFirst: false,
    bgClass: _bgClass,
    successClass: _successClass,
    errorClass: _errorClass,
    selector: '.b-lazy',
    parent: _parent,
    offset: 100,
    validateDelay: _validateDelay,
    unblazy: false,
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
    var me = $.extend(fn, this);

    me.name = ns;
    me.options = _opts = $.extend({}, _defaults, options || {});

    _bgClass = _opts.bgClass || _bgClass;
    _successClass = _opts.successClass || _successClass;
    _errorClass = _opts.errorClass || _errorClass;
    _parent = _opts.parent || _parent;
    _validateDelay = _opts.validateDelay || _validateDelay;
    _root = _opts.root || _root;

    // DOM ready fix.
    setTimeout(function () {
      me.reinit();
    });

    return me;
  }

  // Prepare prototype to interchange with Blazy as fallback.
  fn.prepare = _noop;
  fn.count = 0;
  fn.resizeTick = 0;
  fn.winData = function () {
    return $.checkWindow(_opts.offset);
  };

  fn.lazyLoad = function (el) {
    var me = this;
    var parent = el.parentNode;
    var isBg = $.isBg(el);
    var isPicture = $.equal(parent, 'picture');
    var isImage = $.equal(el, 'img') && !isPicture;
    var isVideo = $.equal(el, 'video');
    var isDataset = $.hasAttr(el, _dataSrc);

    // PICTURE elements.
    if (isPicture) {
      if (isDataset) {
        $.mapSource(el, _srcSet, true);

        // Tiny controller image inside picture element won't get preloaded.
        $.mapAttr(el, _src, true);
      }

      me.status(el, true);
    }
    // VIDEO elements.
    else if (isVideo) {
      me.video(el, true);
    }
    else {
      // IMG or DIV/ block elements got preloaded for better UX with loading.
      // Native doesn't support DIV, fix it.
      if (isImage || isBg) {
        me.setImage(el, isBg);
      }
      // IFRAME elements, etc.
      else {
        if ($.hasAttr(el, _src)) {
          if ($.attr(el, _dataSrc)) {
            $.mapAttr(el, _src, true);
          }

          me.status(el, true);
        }
      }
    }
  };

  fn.video = function (el, ok) {
    // Native doesn't support video, fix it.
    $.mapSource(el, _src, true);
    el.load();
    this.status(el, ok);
  };

  // Compatibility between Native and old data-[SRC|SRSET] approaches.
  fn.setImage = function (el, isBg) {
    var me = this;
    var img = new Image();
    var isResimage = $.hasAttr(el, _srcSet);
    var isDataset = $.hasAttr(el, _dataSrc);
    var currSrc = isDataset ? _dataSrc : _src;
    var currSrcset = isDataset ? _dataSrcset : _srcSet;

    var applyAttrs = function () {
      if (isBg && $.isFun($.bgUrl)) {
        img.src = $.bgUrl(el, _winData);
      }
      else {
        img.src = $.attr(el, currSrc);
        if (isDataset) {
          $.mapAttr(el, _imgSources, false);
        }
      }

      if (isResimage) {
        img.srcset = $.attr(el, currSrcset);
      }
    };

    var load = function (el, ok) {
      if (isBg && $.isFun($.bg)) {
        $.bg(el, _winData);
      }

      me.status(el, ok);
      if (ok) {
        $.removeAttr(el, isBg ? _bgSources : _imgSources, _data);
      }
    };

    applyAttrs();

    // Preload `img` to have correct event handlers.
    $.decode(img)
      .then(function () {
        load(el, true);
      })
      .catch(function () {
        load(el, isResimage);

        // Allows to re-observe.
        if (!isResimage) {
          el.bhit = false;
        }
      });
  };

  // BC for interchanging with bLazy.
  fn.load = function (elms, revalidate) {
    var me = this;

    // Manually load elements regardless of being disconnected, or not, relevant
    // for Slick slidesToShow > 1 which rebuilds clones of unloaded elements.
    $.each($.toArray(elms), function (el) {
      if (me.isValid(el) || ($.isElm(el) && revalidate)) {
        intersecting.call(me, el, revalidate);
      }
    });
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

  fn.status = function (el, ok) {
    // Image decode fails with Responsive image, assumes ok, no side effects.
    this.loaded(el, ok ? $._ok : $._er);
  };

  fn.loaded = function (el, status, parent) {
    var me = this;
    var cn = $.closest(el, _parent) || el;
    var ok = status === $._ok;

    parent = parent || cn;
    $.addClass(el, ok ? _successClass : _errorClass);
    me[ok ? 'success' : 'error'](el, status, parent);

    // Adds context for effetcs: blur, etc. considering BG, or just media.
    $.addClass(cn, ok ? _isLoaded : _isError);
    $.removeClass(cn, _isVisible);

    // @todo remove, not compat with old bLazy which provides no events.
    $.trigger(el, 'bio.loaded', {
      status: status
    });
  };

  // Since bLazy, which has no supports for Native, is a fallback, it is easier
  // now to work with Native. No more need to hook into load event seperately,
  // no deferred invocation till one loaded, no hijacking.
  // No more fights under a single source of truth. It is a total swap.
  fn.natively = function () {
    var me = this;

    if (!$.isNative) {
      return;
    }

    // ::findAll is already optimized with a single null check, no extra checks.
    var dataset = me.selector('[data-src][loading]:not(.b-blur)');
    var els = $.findAll(_root, dataset);

    if (els.length) {
      // Reset attributes, and let supportive browsers lazy load natively.
      $(els).mapAttr(['srcset', 'src'], true)
        // Also supports PICTURE which contains SOURCEs. Excluding VIDEO.
        .mapSource(false, true, false);
    }
  };

  fn.destroyQuietly = function (force) {
    var me = this;

    // Infinite pager like IO wants to keep monitoring infinite contents.
    // Multi-breakpoint BG/ ratio may want to update during resizing.
    if (!_disconnected && (force || $.isUnd(Drupal.io))) {
      var el = $.find(_root, me.selector());

      if (!$.isElm(el)) {
        me.destroy(force);
      }
    }
  };

  fn.observe = function () {
    var me = this;

    _bioTick = _elms.length;

    $.observe(me, _elms, true, _opts.unblazy);
  };

  fn.destroy = function (force) {
    var me = this;

    // Do not disconnect if any error found.
    if (_erCounted > 0 && !force) {
      return;
    }

    // Disconnect when all entries are loaded, if so configured.
    var done = ((_bioTick === 0 || me.count === _counted) && _opts.disconnect);
    if (done || force) {
      if (_ioObserver) {
        _ioObserver.disconnect();
      }

      $.unload(me);
      me.count = 0;
      me.elms = _elms = [];
      me.ioObserver = _ioObserver = null;
      me.roObserver = null;
      _disconnected = true;
    }
  };

  fn.reinit = function () {
    _disconnected = false;
    _observed = false;

    init(this);
  };

  function intersecting(el, revalidate) {
    var me = this;

    // Unlike ResizeObserver, IntersectionObserver is done.
    if (_ioObserver && me.isLoaded(el) && !el.bloaded) {
      _ioObserver.unobserve(el);
      el.bloaded = true;
      _bioTick--;
    }

    // Image may take time to load after being hit, and it may be intersected
    // several times till marked loaded. Ensures it is hit once regardless
    // of being loaded, or not. No real issue with normal images on the page,
    // until having VIS alike which may spit out new images on AJAX request.
    if (!el.bhit || revalidate) {
      // Makes sure to have media loaded beforehand.
      me.lazyLoad(el);

      // If not extending/ overriding, at least provide the option.
      if ($.isFun(_opts.intersecting)) {
        _opts.intersecting(el, _opts);
      }

      // If not extending/ overriding, also allows to listen to.
      $.trigger(el, 'bio.intersecting', {
        options: _opts
      });

      _counted++;

      // Marks it hit/ requested. Not necessarily loaded.
      el.bhit = true;
      revalidate = false;
    }
  }

  function interact(entries) {
    var me = this;
    var vp = $.vp;
    var ww = $.ww;
    var update = false;

    if (_resizing) {
      _winData = $.checkWindow(_opts.offset);
      ww = _winData.ww;
    }
    else {
      // Disconnect if necessary.
      me.destroyQuietly(_opts.disconnect);

      // Stop watching if already disconnected.
      if (_disconnected) {
        return;
      }
    }

    // Load each on entering viewport.
    $.each(entries, function (e) {
      var target = e.target;
      var el = target || e;
      var resized = $.isResized(me, e);
      var visible = $.isVisible(e, vp);
      var cn = $.closest(el, _parent) || el;
      var loaded = me.isLoaded(el);

      // To make efficient blur filter via CSS, etc. Blur filter is expensive.
      $[visible && !loaded ? 'addClass' : 'removeClass'](cn, _isVisible);

      // The element is being intersected.
      if (visible) {
        intersecting.call(me, el);
      }

      // The element is being resized.
      _resizing = resized && _ww > 0;
      if (_resizing) {
        // Ensures only before settled, or if any different from previous size.
        if (_ww !== ww) {
          update = true;
          intersecting.call(me, el, resized);
        }
        me.resizeTick++;
      }

      // Provides option such as to animate bg or elements regardless position.
      if ($.isFun(_opts.observing)) {
        _opts.observing(el, visible, _opts);
      }
    });

    // Resizing may happen after disconnection.
    if (update) {
      if ($.isFun(_opts.resizing)) {
        _opts.resizing(entries, _winData, _opts);
      }

      // If not extending/ overriding, also allows to listen to.
      $.trigger(root, 'bio.resizing', {
        entries: entries,
        old: _ww,
        new: ww,
        winData: _winData
      });
    }

    _ww = ww;
  }

  // Initializes the IO.
  function init(me) {
    me.natively();

    me.elms = _elms = $.findAll(_root, me.selector());
    me.count = _elms.length;
    me._raf = _raf;
    me._queue = _queue;

    me.prepare();

    $.interact(me, interact, _elms, true);
    _ioObserver = me.ioObserver;

    // Observes once on the page load regardless multiple observer instances.
    // Possible as we nullify the root option to allow querying the DOM once.
    // Should you need to re-validate, or re-observe, just call ::observe().
    if (_elms.length && !_observed) {
      me.observe();
      _observed = true;
    }
  }

  return Bio;

}));
