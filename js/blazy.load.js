/**
 * @file
 * Provides Intersection Observer API or bLazy loader.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
 * @see https://developers.google.com/web/updates/2016/04/intersectionobserver
 */

(function (Drupal, drupalSettings, _db, window, document) {

  'use strict';

  // PolyFill `isIntersecting` for Microsoft Edge 15 isIntersecting property.
  // https://github.com/WICG/IntersectionObserver/issues/211#issuecomment-309144669
  if ('IntersectionObserver' in window &&
    'IntersectionObserverEntry' in window &&
    'intersectionRatio' in window.IntersectionObserverEntry.prototype &&
    !('isIntersecting' in IntersectionObserverEntry.prototype)) {

    Object.defineProperty(window.IntersectionObserverEntry.prototype, 'isIntersecting', {
      get: function () {
        return this.intersectionRatio > 0;
      }
    });
  }

  /**
   * Blazy public methods.
   *
   * @namespace
   */
  Drupal.blazy = Drupal.blazy || {
    init: null,
    windowWidth: 0,
    count: 0,
    selector: '.b-lazy:not(.b-loaded)',
    globals: function () {
      var me = this;
      var commons = {
        success: me.clearing,
        error: me.clearing
      };

      return _db.extend(drupalSettings.blazy, commons);
    },

    clearing: function (el, io) {
      var me = Drupal.blazy;
      var ie = el.classList.contains('b-responsive') && el.hasAttribute('data-pfsrc');

      // The .b-lazy element can be attached to IMG, or DIV as CSS background.
      el.className = el.className.replace(/(\S+)loading/, '');

      // The .is-loading can be .grid, .slide__content, .box__content, etc.
      var loaders = [
        _db.closest(el, '.is-loading'),
        _db.closest(el, '[class*="loading"]')
      ];

      // Also cleans up closest containers containing loading class.
      _db.forEach(loaders, function (wrapEl) {
        if (wrapEl !== null) {
          wrapEl.className = wrapEl.className.replace(/(\S+)loading/, '');
        }
      });

      // @see http://scottjehl.github.io/picturefill/
      if (window.picturefill && ie) {
        window.picturefill({
          reevaluate: true,
          elements: [el]
        });
      }

      // @todo IO specific, use onload event accordingly.
      if (typeof io !== 'undefined' || io) {
        el.classList.add('b-loaded');
        me.count--;
      }
    },

    isIo: function () {
      return drupalSettings.blazyIo.enabled && 'IntersectionObserver' in window;
    },

    isBlazy: function () {
      return !this.isIo() && this.init !== null && this.init instanceof Blazy;
    },

    removeAttributes: function (el, attrs) {
      _db.forEach(attrs, function (attr) {
        el.removeAttribute('data-' + attr);
      });
    },

    setAttribute: function (el, attr) {
      if (el.hasAttribute('data-' + attr)) {
        el.setAttribute(attr, el.getAttribute('data-' + attr));
        el.removeAttribute('data-' + attr);
        el.classList.add('b-io');
      }
    },

    setBackground: function (el, opts) {
      var me = this;
      var sources = [];
      var src = 'data-src';

      opts = opts || {};

      // DIV elements with multi-serving CSS background images.
      if (opts.breakpoints) {
        _db.forEach(opts.breakpoints, function (object) {
          sources.push(object.src.replace('data-', ''));
          if (object.width <= me.windowWidth) {
            src = object.src;
            return false;
          }
        });
        me.removeAttributes(el, sources);
      }

      el.style.backgroundImage = 'url("' + el.getAttribute(src) + '")';
      el.removeAttribute(src);
      el.classList.add('b-io');
    },

    load: function (el, opts) {
      var me = this;
      var parent = el.parentNode;

      // DIV/ block elements.
      if (typeof el.src === 'undefined') {
        me.setBackground(el, opts);
      }
      else {
        // IMG elements.
        _db.forEach(['srcset', 'src'], function (attr) {
          me.setAttribute(el, attr);
        });

        // PICTURE elements.
        if (parent.nodeName.toLowerCase() === 'picture') {
          _db.forEach(parent.getElementsByTagName('source'), function (source) {
            me.setAttribute(source, 'srcset');
          });
        }
      }

      me.clearing(el, true);
    },

    loadAndDisconnect: function (entries, observer, opts) {
      var me = this;

      // Disconnect when all of the images are loaded.
      if (me.count === 0 && drupalSettings.blazyIo.disconnect) {
        observer.disconnect();
        return;
      }

      // Load each on entering viewport, and stop observing it.
      _db.forEach(entries, function (entry) {
        if (entry.isIntersecting && !entry.target.classList.contains('b-loaded')) {
          me.load(entry.target, opts);
          observer.unobserve(entry.target);
        }
      });
    },

    observeAndValidate: function (el, entries, observer) {
      _db.forEach(entries, function (entry) {
        // Only observes if not already loaded.
        if (!entry.classList.contains('b-loaded')) {
          observer.observe(entry);
        }
      });
    },

    io: function (el, opts) {
      var me = this;
      var entries = el === null || typeof el === 'undefined' ? document.querySelectorAll(me.selector) : el.querySelectorAll(me.selector);
      var observer;
      var config = {
        rootMargin: drupalSettings.blazyIo.rootMargin,
        threshold: drupalSettings.blazyIo.threshold
      };

      opts = opts || {};

      // Initialize the IO.
      observer = new IntersectionObserver(function (targets, watcher) {
        me.loadAndDisconnect(targets, watcher, opts);
      }, config);

      // Start observing entries.
      me.count = entries.length;
      me.observeAndValidate(el, entries, observer);

      // Revalidate such as on slide changes after being disconnected.
      var revalidate = function (execute) {
        // No need to execute unless required by slick slide changes.
        if (typeof execute === 'undefined' || execute) {
          entries = document.querySelectorAll(me.selector);
          me.count = entries.length;

          me.observeAndValidate(el, entries, observer);
        }
      };

      // @todo BC for bLazy, make it useful, or leave it.
      return {
        options: opts + config,
        load: me.noop,
        revalidate: revalidate,
        observer: observer
      };
    },

    noop: function () {}
  };

  /**
   * Blazy utility functions.
   *
   * @param {HTMLElement} elm
   *   The Blazy HTML element.
   */
  function doBlazy(elm) {
    var me = Drupal.blazy;
    var dataAttr = elm.getAttribute('data-blazy');
    var data = !dataAttr ? {} : _db.parse(dataAttr);
    var opts = _db.extend({}, me.globals(), data);
    var ratios = elm.querySelectorAll('[data-dimensions]');
    var loopRatio = ratios.length > 0;
    var fallbackRatios = elm.querySelectorAll('[data-ratio]');
    var loopFallbackRatio = fallbackRatios.length > 0;

    /**
     * Updates the dynamic multi-breakpoint aspect ratio.
     *
     * This only applies to multi-serving images with aspect ratio fluid if
     * each element contains [data-dimensions] attribute.
     * Static single aspect ratio, e.g. `media--ratio--169`, will be ignored,
     * and will use CSS instead.
     *
     * @param {HTMLElement} el
     *   The .media--ratio--fluid|enforced HTML element.
     */
    function updateRatio(el) {
      var dimensions = !el.getAttribute('data-dimensions') ? false : _db.parse(el.getAttribute('data-dimensions'));

      if (!dimensions) {
        return;
      }

      var keys = Object.keys(dimensions);
      var xs = keys[0];
      var xl = keys[keys.length - 1];
      var mw = function (w) {
        return w >= me.windowWidth;
      };
      var pad = keys.filter(mw).map(function (v) {
        return dimensions[v];
      }).shift();

      if (pad === 'undefined') {
        pad = dimensions[me.windowWidth >= xl ? xl : xs];
      }

      if (pad !== 'undefined') {
        el.style.paddingBottom = pad + '%';
      }
    }

    /**
     * Fix for Twig inline_template and Views rewrite striping out style.
     *
     * @param {HTMLElement} el
     *   The .media--ratio--fluid|enforced HTML element.
     */
    function updateFallbackRatio(el) {
      // Only rewrites if the style is indeed stripped out by Twig, and not set.
      if (!el.hasAttribute('style')) {
        el.style.paddingBottom = el.getAttribute('data-ratio') + '%';
      }
      el.removeAttribute('data-ratio');
    }

    // Initializes IntersectionObserver or Blazy instance.
    me.init = me.isIo() ? me.io(elm, opts) : new Blazy(opts);

    // Reacts on resizing, and the magic () also does it on page load.
    _db.resize(function () {
      me.windowWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth || window.screen.width;

      if (loopRatio) {
        _db.forEach(ratios, updateRatio, elm);
      }
      else if (loopFallbackRatio) {
        _db.forEach(fallbackRatios, updateFallbackRatio, elm);
      }

      // BC with bLazy, IO doesn't need to revalidate, Slick multiple-view does.
      me.init.revalidate(elm.classList.contains('slick--multiple-view'));
    })();

    elm.classList.add('blazy--on');
  }

  /**
   * Attaches blazy behavior to HTML element identified by [data-blazy].
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazy = {
    attach: function (context) {
      var me = Drupal.blazy;
      var el = document.querySelector('[data-blazy]');

      // Runs basic Blazy if no [data-blazy] found, probably a single image.
      // Cannot use .contains(), as IE11 doesn't support method 'contains'.
      if (el === null) {
        me.init = me.isIo() ? me.io() : new Blazy(me.globals());
        return;
      }

      // Runs Blazy with multi-serving images, and aspect ratio supports.
      var blazies = document.querySelectorAll('.blazy:not(.blazy--on)');
      _db.once(_db.forEach(blazies, doBlazy));
    }
  };

}(Drupal, drupalSettings, dBlazy, this, this.document));
