/**
 * @file
 * Provides Intersection Observer API loader.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
 * @see https://developers.google.com/web/updates/2016/04/intersectionobserver
 * @see https://www.npmjs.com/package/intersection-observer
 * @see https://github.com/w3c/IntersectionObserver
 * @see https://caniuse.com/?search=visualViewport
 * @todo https://developer.mozilla.org/en-US/docs/Web/API/Visual_Viewport_API
 * @todo remove traces of fallback to be taken care care of by old bLazy fork.
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

}((this || module || {}), function (ns, $, _win) {

  'use strict';

  if ($.isAmd) {
    _win = window;
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
  var _opts = {};
  var _elms = [];
  var _successClass = 'b-loaded';
  var _errorClass = 'b-error';
  var _bgClass = 'b-bg';
  var _isVisible = 'is-b-visible';
  var _media = 'media';
  var _parent = '.' + _media;
  var _data = 'data-';
  var _src = 'src';
  var _srcSet = 'srcset';
  var _dataSrc = _data + _src;
  var _dataSrcset = _data + _srcSet;
  var _imgSources = [_srcSet, _src];
  var _destroyed = false;
  var _initialized = false;
  var _resizing = false;
  var _validateDelay = 25;
  var _ioObserver = null;
  var _isNativeChecked = null;

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
    me.options = _opts = $.extend($._defaults, options || {});

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
  fn.count = 0;
  fn.resizeTick = 0;
  fn.windowData = function () {
    return $.isUnd(_winData.vp) ? $.windowData(_opts, true) : _winData;
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

      _erCounted = me.status(el, true);
    }
    // VIDEO elements.
    else if (isVideo) {
      _erCounted = $.loadVideo(el, true, _opts);
    }
    else {
      // IMG or DIV/ block elements got preloaded for better UX with loading.
      // Native doesn't support DIV, fix it.
      if (isImage || isBg) {
        me.loadImage(el, isBg);
      }
      // IFRAME elements, etc.
      else {
        if ($.hasAttr(el, _src)) {
          if ($.attr(el, _dataSrc)) {
            $.mapAttr(el, _src, true);
          }

          _erCounted = me.status(el, true);
        }
      }
    }
  };

  // Compatibility between Native and old data-[SRC|SRSET] approaches.
  fn.loadImage = function (el, isBg) {
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

      _erCounted = $.status(el, ok, _opts);
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
  // @todo merge wiuth bLazy::load.
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
    // @todo recheck, troubled for onresize: + ':not(.' + _successClass + ')'.
    return _opts.selector + suffix;
  };

  fn.isLoaded = function (el) {
    return $.hasClass(el, _successClass);
  };

  fn.isValid = function (el) {
    return $.isElm(el) && !this.isLoaded(el);
  };

  fn.revalidate = function (force) {
    var me = this;

    // Prevents from too many revalidations unless needed.
    if ((force === true || me.count !== _counted) && (_revTick < _counted)) {
      me.elms = _elms = $.findAll(_root, me.selector());

      if (_elms.length) {
        me.observe(true);

        _revTick++;
      }
    }
  };

  // Since bLazy, which has no supports for Native, is a fallback, it is easier
  // now to work with Native. No more need to hook into load event seperately,
  // no deferred invocation till one loaded, no hijacking.
  // No more fights under a single source of truth. It is a total swap.
  // As mentioned in the doc, Native at least Chrome starts loading images
  // 8000px, hardcoded, before they are entering the viewport. Meaning harsh,
  // makes fancy stuffs like blur useless. And bad because blur filter
  // is very expensive, and when they are triggered before visible, will block.
  // @see /admin/help/blazy_ui# NATIVE LAZY LOADING
  // With bIO as the main loader, the game changed, quoted from:
  // https://developer.mozilla.org/en-US/docs/Learn/HTML/Howto/Author_fast-loading_HTML_pages
  // "Note that lazily-loaded images may not be available when the load event is
  // fired. You can determine if a given image is loaded by checking to see if
  // the value of its Boolean complete property is true."
  // Old bLazy relies on onload, meaning too early loaded decision for Native,
  // the reason for our previous deferred invocation, not decoding like what bIO
  // did which is more precise as suggested by the quote.
  // Assumed, untested, fine with combo IO + decoding checks before blur spits.
  // Shortly we are in the right direction to cope with Native vs. data-[SRC].
  // @todo recheck IF wrong so to put back https://drupal.org/node/3120696.
  fn.natively = function () {
    var me = this;

    if (!$.isNativeLazy || _isNativeChecked) {
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

    _isNativeChecked = true;
  };

  fn.destroyQuietly = function (force) {
    var me = this;

    // Infinite pager like IO wants to keep monitoring infinite contents.
    // Multi-breakpoint BG/ ratio may want to update during resizing.
    if (!_destroyed && (force || $.isUnd(Drupal.io))) {
      var el = $.find(_root, me.selector());

      if (!$.isElm(el)) {
        me.destroy(force);
      }
    }
  };

  fn.destroy = function (force) {
    var me = this;

    // Do not disconnect if any error found.
    if (_destroyed || (_erCounted > 0 && !force)) {
      return;
    }

    // Disconnect when all entries are loaded, if so configured.
    var done = (_bioTick === me.count - 1) && _opts.disconnect;
    if (done || force) {
      if (_ioObserver) {
        _ioObserver.disconnect();
      }

      $.unload(me);
      me.count = 0;
      me.elms = _elms = [];
      me.ioObserver = _ioObserver = null;
      me.destroyed = _destroyed = true;
    }
  };

  fn.observe = function (reobserve) {
    var me = this;

    // Only initialize the observer if destroyed, and IO.
    if ($.isIo && (me.destroyed || reobserve)) {
      _destroyed = false;
      _winData = $.initObserver(me, interact, _elms, true);
      _ioObserver = me.ioObserver;

      me.destroyed = false;
    }

    // Observe as IO, or initialize old bLazy as fallback.
    if (!_initialized || reobserve) {
      $.observe(me, _elms, true);

      _initialized = true;
    }
  };

  fn.reinit = function () {
    var me = this;
    me.destroyed = true;

    init(me);
  };

  function intersecting(el, revalidate) {
    var me = this;
    var count = me.count;

    if (_bioTick === count - 1) {
      me.destroyQuietly();
    }

    // Unlike ResizeObserver, IntersectionObserver is done.
    if (_ioObserver && me.isLoaded(el) && !el.bloaded) {
      _ioObserver.unobserve(el);
      el.bloaded = true;

      _bioTick++;
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

  function resizing(el) {
    var me = this;
    var isBg = $.hasClass(el, _bgClass);

    // Fix dynamic multi-breakpoint background to avoid loaders workarounds.
    if (isBg) {
      me.loadImage(el, isBg);
    }
  }

  // This function is called by two observers: IO and RO.
  function interact(entries) {
    var me = this;
    var vp = $.vp;
    var ww = $.ww;
    var entry = entries[0];
    var isBlur = $.isBlur(entry);
    var isResizing = $.isResized(me, entry);

    // RO is another abserver.
    if (isResizing) {
      _winData = $.updateViewport(_opts);

      // Provides a way to fix dynamic aspect ratio, etc.
      if ($.isFun(_opts.resizing)) {
        _opts.resizing(me, entries, _winData);
      }

      // If not extending/ overriding, also allows to listen to.
      $.trigger(_win, 'blazy.resizing', {
        winData: _winData,
        entries: entries,
        old: _ww
      });
    }
    else {
      // Stop IO watching if already disconnected.
      if (_destroyed) {
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
      if (_resizing && !isBlur) {
        // Ensures only before settled, or if any different from previous size.
        if (_ww !== ww) {
          resizing.call(me, el);
        }
        me.resizeTick++;
      }

      // Provides option such as to animate bg or elements regardless position.
      if ($.isFun(_opts.observing)) {
        _opts.observing(el, visible, _opts);
      }
    });

    _ww = ww;
  }

  // Initializes the IO with fallback to old bLazy.
  function init(me) {
    // Swap data-[SRC|SRCSET] for non-js version once, if not choosing Native.
    me.natively();

    me.elms = _elms = $.findAll(_root, me.selector());
    me.count = _elms.length;
    me._raf = [];
    me._queue = [];

    // Observe elements. Old blazy as fallback is also initialized here.
    // IO will unobserve, or disconnect. Old bLazy will self destroy.
    me.observe();
  }

  return Bio;

}));
