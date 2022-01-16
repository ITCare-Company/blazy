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
  var _isBioMedia = 'BioMedia' in root;
  var _doc = document;
  var _root = _doc;
  var _winData = {};
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
  var _bgClass = 'b-bg';
  var _isVisible = 'is-b-visible';
  var _isLoaded = 'is-' + _successClass;
  var _isError = 'is-' + _errorClass;
  var _media = 'media';
  var _elMedia = '.' + _media;
  var _data = 'data-';
  var _src = 'src';
  var _srcSet = 'srcset';
  var _dataSrc = _data + _src;
  var _dataSrcset = _data + _srcSet;
  var _bgSources = [_src];
  var _imgSources = [_srcSet, _src];
  var _scrollEvent = 'scroll.' + ns;
  var _ioObserve = false;
  var ioRaf = false;
  var ioQueue = [];
  var _defaults = {
    root: null,
    decode: false,
    disconnect: false,
    error: false,
    success: false,
    intersecting: false,
    observing: false,
    mobileFirst: false,
    bgClass: _bgClass,
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
    var me = $.extend(fn, this);

    me.name = ns;
    me.options = _opts = $.extend({}, _defaults, options || {});

    _bgClass = _opts.bgClass || _bgClass;
    _successClass = _opts.successClass || _successClass;
    _errorClass = _opts.errorClass || _errorClass;
    _root = _opts.root || _root;

    return me.reinit();
  }

  // Prepare prototype to interchange with Blazy as fallback.
  fn.count = 0;

  fn.winData = function () {
    return $.winData(_opts.mobileFirst);
  };

  fn.prepare = _noop;

  // @todo minimize dups by BioMedia.
  fn.lazyLoad = function (el, revalidate) {
    var me = this;

    // If we are here, it means `No JavaScript` lazy is enabled, no BioMedia.
    if (_isBioMedia) {
      return;
    }

    if (!el.biohit || revalidate) {
      var isImage = $.equal(el, 'img');
      var isBg = $.isUnd(el.src) && $.hasClass(el, _bgClass);
      var isVideo = $.equal(el, 'video');

      if (isVideo) {
        me.video(el, true);
      }
      else {
        // Native doesn't support DIV. Without BioMedia, we have to fix it.
        if (isImage || isBg) {
          me.setImage(el, isBg);
        }
        else {
          // Iframe, Picture are supported by Native.
          // @todo re-check if anything else todo here.
          me.status(el, true);
        }
      }

      el.biohit = true;
      revalidate = false;
    }
  };

  fn.video = function (el, ok) {
    // Native doesn't support video. Without BioMedia, we have to fix it.
    $.mapSource(el, _src, true);
    el.load();
    this.status(el, ok);
  };

  fn.status = function (el, ok) {
    // Image decode fails with Responsive image, assumes ok, no side effects.
    this.loaded(el, ok ? $._ok : $._er);
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
          el.biohit = false;
        }
      });
  };

  // BC for interchanging with bLazy.
  fn.load = function (elms, revalidate) {
    var me = this;

    // Manually load elements regardless of being disconnected, or not, relevant
    // for Slick slidesToShow > 1 which rebuilds clones of unloaded elements.
    $.each($.toArray(elms), function (el) {
      if (me.isValid(el) || revalidate) {
        intersecting.call(me, el, revalidate);
      }
    });

    me.check();
  };

  fn.unload = function () {
    if (!_ioObserve) {
      $.unbindEvent(root, _scrollEvent, _ioObserve);
    }
    if (ioRaf) {
      cancelAnimationFrame(ioRaf);
    }
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
    var cn = $.closest(el, _elMedia) || el;
    var ok = status === $._ok;

    $.addClass(el, ok ? _successClass : _errorClass);
    me[ok ? 'success' : 'error'](el, status, parent);

    // Adds context for effetcs: blur, etc. considering BG, or just media.
    $.addClass(cn, ok ? _isLoaded : _isError);
    $.removeClass(cn, _isVisible);

    $.trigger(el, 'bio.loaded', {
      status: status
    });
  };

  fn.observe = function () {
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
    me.unload();
    me.observer = _observer = null;
  };

  fn.reinit = function () {
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

  function intersecting(el, revalidate) {
    var me = this;

    // Makes sure to have media loaded beforehand.
    me.lazyLoad(el, revalidate);

    // If not extending/ overriding, at least provide the option.
    if ($.isFun(_opts.intersecting)) {
      _opts.intersecting(el, _opts);
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

  function observing(entries) {
    var me = this;

    me.check();

    // Stop watching if already disconnected.
    if (_disconnected) {
      return;
    }

    // Load each on entering viewport.
    var viewport = $.vp;
    $.each(entries, function (entry) {
      var target = entry.target;
      var el = target || entry;
      var cn = $.closest(el, _elMedia) || el;
      var visible = target ? (entry.isIntersecting || entry.intersectionRatio > 0) : $.isVisible(el, viewport);
      var loaded = me.isLoaded(el);

      // To make efficient blur filter via CSS, etc. Blur filter is expensive.
      $[visible && !loaded ? 'addClass' : 'removeClass'](cn, _isVisible);

      // Provides option such as to animate bg or elements regardless position.
      if ($.isFun(_opts.observing)) {
        _opts.observing(el, visible, _observer, _opts);
      }

      // The element is being intersected.
      if (visible) {
        if (!loaded) {
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

    // @todo hook into ResizeObserver.
    $.checkViewport(_opts.offset);
    _winData = me.winData();

    me.prepare();

    // Initializes the IO.
    function _intersect(entries) {
      if (!ioQueue.length) {
        ioRaf = requestAnimationFrame(_enqueue);
      }

      ioQueue.push(entries);

      // Default to old browsers.
      return false;
    }

    function _enqueue() {
      $.enqueue(ioQueue, observing, me);
    }

    // IE11 not supported, we'll provide a fallback.
    // @see https://caniuse.com/IntersectionObserver
    _ioObserve = function () {
      return $.isIo ? new IntersectionObserver(_intersect, config) : _intersect(_elms);
    };

    // Uses IntersectionObserver for modern browsers, else degrades.
    me.observer = _observer = _ioObserve();

    // Observes once on the page load regardless multiple observer instances.
    // Possible as we nullify the root option to allow querying the DOM once.
    // Should you need to re-validate, or re-observe, just call ::observe().
    if (_elms.length && !_observed) {
      if (_observer) {
        me.observe();
      }
      else {
        // @todo re-check this.
        $.bindEvent(root, _scrollEvent, $.debounce(_ioObserve));
      }

      _observed = true;
    }

    return me;
  }

  root.bio = function (options) {
    return new Bio(options);
  };

  return Bio;

}));
