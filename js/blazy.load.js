/**
 * @file
 * Provides Intersection Observer API or bLazy loader.
 */

(function (Drupal, drupalSettings, _db, window, document) {

  'use strict';

  var _ratioTimer;

  /**
   * Blazy public methods.
   *
   * @namespace
   */
  Drupal.blazy = Drupal.blazy || {
    init: null,
    loopRatio: false,
    windowWidth: 0,
    blazySettings: drupalSettings.blazy || {},
    ioSettings: drupalSettings.blazyIo || {},
    isForced: false,
    options: {},
    globals: function () {
      var me = this;
      var commons = {
        success: me.clearing.bind(me),
        error: me.clearing.bind(me),
        selector: '.b-lazy',
        errorClass: 'b-error',
        successClass: 'b-loaded'
      };

      return _db.extend(me.blazySettings, me.ioSettings, commons);
    },

    clearing: function (el) {
      var me = this;
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

      // Correct padding after the image is fully loaded.
      me.updatePadding(el);

      // Provides event listeners for easy overrides without full overrides.
      _db.trigger(el, 'blazy.done', {
        options: me.options
      });
    },

    updatePadding: function (el) {
      var me = this;
      var cn = el.classList.contains(el, 'media--ratio--fluid') ? el : _db.closest(el, '.media--ratio--fluid');

      if (me.loopRatio && cn !== null) {
        window.clearTimeout(_ratioTimer);
        _ratioTimer = window.setTimeout(function () {
          var pad = Math.round(((el.naturalHeight / el.naturalWidth) * 100), 2);

          if (pad > 0) {
            cn.style.paddingBottom = pad + '%';
          }
        }, 600);
      }
    },

    doNativeLazy: function (el) {
      var me = this;
      // Reset attributes, and let supportive browsers lazy load them natively.
      _db.setAttrs(el, ['srcset', 'src'], true);

      // Also supports PICTURE or (future) VIDEO element which contains SOURCEs.
      _db.setAttrsWithSources(el, false, true);

      // Mark it loaded to prevent Blazy/IO to do any further work.
      el.classList.add(me.options.successClass);
      me.clearing(el);
    },

    isNativeLazy: function () {
      return 'loading' in HTMLImageElement.prototype;
    },

    isIo: function () {
      return this.ioSettings && this.ioSettings.enabled && 'IntersectionObserver' in window;
    },

    isBlazy: function () {
      return !this.isIo() && 'Blazy' in window;
    },

    forEach: function (context) {
      var blazies = context.querySelectorAll('.blazy:not(.blazy--on)');
      if (blazies.length > 0) {
        _db.forEach(blazies, doBlazy, context);
      }
    },

    run: function (opts) {
      return this.isIo() ? new BioMedia(opts) : new Blazy(opts);
    }

  };

  /**
   * Initialize the blazy instance, either basic, advanced, or native.
   *
   * The initialization may take once for basic (not using module formatters),
   * or per .blazy/[data-blazy] formatter when they are one or many on a page.
   *
   * @param {HTMLElement} context
   *   This can be document, or .blazy container w/o [data-blazy].
   * @param {Object} opts
   *   The options might be empty for basic blazy, not using formatters.
   */
  var initBlazy = function (context, opts) {
    var me = Drupal.blazy;
    me.options = _db.extend({}, me.globals(), opts || {});

    // Set docroot in case we are in an iframe.
    // @see Blazy.toArray
    var documentElement = context instanceof HTMLDocument ? context : _db.closest(context, 'html');
    if (!document.documentElement.isSameNode(documentElement)) {
      me.options.root = documentElement;
    }

    // Swap lazy attributes to let supportive browsers lazy load them.
    // This means Blazy and even IO should not lazy-load them any more.
    // Ensures to not touch lazy-loaded AJAX, or likely non-supported elements:
    // Video, DIV, etc. Only IMG and IFRAME are supported for now.
    // Enforced such as with entity embed iframe where lazyload is less useful
    // due to smaller window estate. Be sure to enable `Native lazy loading`.
    if (me.isNativeLazy() || me.isForced) {
      var elms = context.querySelectorAll('.b-lazy[loading]:not(.' + me.options.successClass + ')');
      if (elms.length > 0) {
        _db.forEach(elms, me.doNativeLazy.bind(me));
      }
    }

    // Put the blazy/IO instance into a public object for references/ overrides.
    // If native lazy load is supported, the following will skip internally.
    me.init = me.run(me.options);
  };

  /**
   * Blazy utility functions.
   *
   * @param {HTMLElement} elm
   *   The .blazy/[data-blazy] container, not the lazyloaded .b-lazy element.
   */
  function doBlazy(elm) {
    var me = Drupal.blazy;
    var dataAttr = elm.getAttribute('data-blazy');
    var opts = (!dataAttr || dataAttr === '1') ? {} : (_db.parse(dataAttr) || {});
    var ratioElms = elm.querySelector('[data-dimensions]') === null ? [] : elm.querySelectorAll('[data-dimensions]');
    var fallbackRatioElms = elm.querySelector('[data-ratio]') === null ? [] : elm.querySelectorAll('[data-ratio]');

    me.loopRatio = ratioElms.length > 0;

    /**
     * Updates the dynamic multi-breakpoint aspect ratio.
     *
     * This only applies to multi-serving images with aspect ratio fluid if
     * each element contains [data-dimensions] attribute.
     * Static single aspect ratio, e.g. `media--ratio--169`, will be ignored,
     * and will use CSS instead.
     *
     * @param {HTMLElement} el
     *   The .media--ratio--fluid HTML element.
     */
    function updateRatio(el) {
      var dimensions = _db.parse(el.getAttribute('data-dimensions'));

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

      el.removeAttribute('data-ratio');
    }

    /**
     * Fix for Twig inline_template and Views rewrite striping out style.
     *
     * @param {HTMLElement} el
     *   The .media--ratio--fluid HTML element.
     */
    function updateFallbackRatio(el) {
      // Only rewrites if the style is indeed stripped out by Twig, and not set.
      if (!el.hasAttribute('style') && el.getAttribute('data-ratio')) {
        el.style.paddingBottom = el.getAttribute('data-ratio') + '%';
      }
    }

    // Initializes native, IntersectionObserver, or Blazy instance.
    initBlazy(elm, opts);

    // Reacts on resizing per 200ms, and the magic () also does it on page load.
    _db.resize(function () {
      me.windowWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth || window.screen.width;

      if (me.loopRatio) {
        _db.forEach(ratioElms, updateRatio, elm);
      }
      else if (fallbackRatioElms.length > 0) {
        _db.forEach(fallbackRatioElms, updateFallbackRatio, elm);
      }

      // BC with bLazy, native/IO doesn't need to revalidate, bLazy does.
      // Scenarios: long horizontal containers, Slick carousel slidesToShow > 3.
      // If any issue, add a class `blazy--revalidate` manually to .blazy.
      if (!me.isNativeLazy() && (me.isBlazy() || elm.classList.contains('blazy--revalidate'))) {
        me.init.revalidate(true);
      }
    })();

    elm.classList.add('blazy--on');
  }

  /**
   * Attaches blazy behavior to HTML element identified by .blazy/[data-blazy].
   *
   * The .blazy/[data-blazy] is the .b-lazy container, might be .field, etc.
   * The .b-lazy is the individual IMG, IFRAME, PICTURE, VIDEO, DIV, BODY, etc.
   * The lazy-loaded element is .b-lazy, not its container. Note the hypen (b-)!
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazy = {
    attach: function (context) {
      // Drupal.attachBehaviors already does this so if this is necessary,
      // someone does an invalid call. But let's be robust here.
      // Note: context can be unexpected <script> element with Media library.
      context = context || document;
      var el = context.querySelector('[data-blazy]');

      // Runs basic Blazy if no [data-blazy] found, probably a single image or
      // a theme that does not use field attributes.
      // The [data-blazy] is set by the module for formatters, or Views gallery.
      // Cannot use .contains(), as IE11 doesn't support method 'contains'.
      // See https://developer.mozilla.org/en-US/docs/Web/API/Node/contains.
      if (el === null) {
        initBlazy(context);
      }

      // Runs Blazy with multi-serving images, and aspect ratio supports.
      // W/o [data-blazy] to address various scenarios like custom simple works,
      // or within Views UI which is not easy to set [data-blazy] via UI.
      // See https://www.drupal.org/node/3057691#comment-13146878
      _db.once(Drupal.blazy.forEach(context));
    }
  };

}(Drupal, drupalSettings, dBlazy, this, this.document));
