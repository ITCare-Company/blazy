/**
 * @file
 * Provides native, Intersection Observer API, or bLazy lazy loader.
 */

(function (Drupal, drupalSettings, _d, _win, _doc) {

  'use strict';

  var _dataAnimation = 'data-animation';
  var _dataDimensions = 'data-dimensions';
  var _dataBg = 'data-backgrounds';
  var _dataRatio = 'data-ratio';
  var _isNativeExecuted = false;
  var _resizeTick = 0;

  /**
   * Blazy public methods.
   *
   * @namespace
   */
  Drupal.blazy = {
    context: null,
    init: null,
    instances: [],
    items: [],
    windowWidth: 0,
    blazySettings: drupalSettings.blazy || {},
    ioSettings: drupalSettings.blazyIo || {},
    revalidate: false,
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

      return _d.extend(me.blazySettings, me.ioSettings, commons);
    },

    selector: function (suffix) {
      suffix = suffix || '';
      var opts = this.options;
      return opts.selector + suffix + ':not(.' + opts.successClass + ')';
    },

    clearing: function (el) {
      var me = this;
      var cn = _d.closest(el, '.media');
      var an = _d.closest(el, '[' + _dataAnimation + ']');

      // Clear loading classes.
      _d.clearLoading(el);

      // Reevaluate the element for errors, or IE.
      me.reevaluate(el);

      // Container might be the el itself for BG, do not NULL check here.
      me.updateContainer(el, cn);

      // Supports blur, animate.css for CSS background, picture, image, media.
      if (an || _d.hasAttr(el, _dataAnimation)) {
        _d.animate(an === null ? el : an);
      }

      // Provides event listeners for easy overrides without full overrides.
      // Runs before native to allow native use this on its own onload event.
      _d.trigger(el, 'blazy.done', {
        options: me.options
      });

      // Initializes the native lazy loading once the first found is loaded.
      if (!_isNativeExecuted) {
        _d.trigger(me.context, 'blazy.native', {
          options: me.options
        });

        _isNativeExecuted = true;
      }
    },

    isLoaded: function (el) {
      return _d.hasClass(el, this.options.successClass);
    },

    load: function (cn) {
      var me = this;

      // DOM ready fix.
      _win.setTimeout(function () {
        var elms = _d.findAll(cn || _doc, me.selector());

        if (elms.length) {
          _d.forEach(elms, function (el) {
            me.update(el, false);
          });
        }
      }, 100);
    },

    update: function (el, delayed) {
      var me = this;
      var _update = function () {
        if (_d.hasAttr(el, _dataBg)) {
          _d.updateBg(el, me.options.mobileFirst);
        }
        else {
          if (me.init) {
            me.init.load(el);
          }
        }
      };

      if (delayed) {
        // DOM ready fix.
        _win.setTimeout(function () {
          _update();
        }, 100);
      }
      else {
        _update();
      }
    },

    reevaluate: function (el) {
      var me = this;
      var ie = _d.hasClass(el, 'b-responsive') && _d.hasAttr(el, 'data-pfsrc');

      // In case an error, try forcing it, once.
      if (_d.hasClass(el, me.options.errorClass) && !_d.hasClass(el, 'b-checked')) {
        _d.addClass(el, 'b-checked');

        // This is a rare case, hardly called, just nice to have for errors.
        me.update(el, true);
      }

      // @see http://scottjehl.github.io/picturefill/
      if (_win.picturefill && ie) {
        _win.picturefill({
          reevaluate: true,
          elements: [el]
        });
      }
    },

    updateContainer: function (el, cn) {
      var me = this;
      var isPicture = _d.equal(el.parentNode, 'picture') && _d.hasAttr(cn, _dataDimensions);

      // Fixed for effect Blur messes up Aspect ratio Fluid calculation.
      _win.setTimeout(function () {
        if (me.isLoaded(el)) {
          // Adds context for effetcs: blur, etc. considering BG, or just media.
          _d.addClass(_d.hasClass(cn, 'media') ? cn : el, 'is-b-loaded');

          // Only applies to ratio fluid.
          if (isPicture) {
            me.updatePicture(el, cn);
          }

          // Basically makes multi-breakpoint BG work for IO or old bLazy once.
          if (_d.hasAttr(el, _dataBg)) {
            _d.updateBg(el, me.options.mobileFirst);
          }
        }
      });
    },

    updatePicture: function (el, cn) {
      var me = this;
      var pad = Math.round(((el.naturalHeight / el.naturalWidth) * 100), 2);

      cn.style.paddingBottom = pad + '%';

      // Swap all aspect ratio once to reduce abrupt ratio changes for the rest.
      if (me.instances.length > 0) {
        var picture = function (elm) {
          if (!('blazyInstance' in elm) && !('blazyUniform' in elm)) {
            return;
          }

          if ((elm.blazyInstance === cn.blazyInstance) && (_resizeTick > 1 || !('isBlazyPicture' in elm))) {
            _d.trigger(elm, 'blazy.uniform.' + elm.blazyInstance, {
              pad: pad
            });
            elm.isBlazyPicture = true;
          }
        };

        // Uniform sizes must apply to each instance, not globally.
        _d.forEach(me.instances, function (elm) {
          Drupal.debounce(picture(elm), 201, true);
        }, me.context);
      }
    },

    /**
     * Attempts to fix for Views rewrite stripping out data URI causing 404.
     *
     * E.g.: src="image/jpg;base64 should be src="data:image/jpg;base64.
     * The "Placeholder" 1px.gif via Blazy UI costs extra HTTP requests. This is
     * a less costly solution, but not bulletproof due to being client-side
     * which means too late to the party. Yet not bad for 404s below the fold.
     * This must be run before any lazy (native, bLazy or IO) kicks in.
     *
     * @todo Remove if a permanent non-client available other than Placeholder.
     */
    fixMissingDataUri: function () {
      var me = this;
      var els = _d.findAll(me.context, me.selector('[src^="image"]'));

      var fixDataUri = function (img) {
        var src = _d.attr(img, 'src');
        if (_d.contains(src, ['base64', 'svg+xml'])) {
          _d.attr(img, 'src', src.replace('image', 'data:image'));
        }
      };

      if (els.length > 0) {
        _d.forEach(els, fixDataUri);
      }
    },

    /**
     * Updates the dynamic multi-breakpoint aspect ratio: bg, picture or image.
     *
     * This only applies to Responsive images with aspect ratio fluid.
     * Static ratio (media--ratio--169, etc.) is ignored and uses CSS instead.
     *
     * @param {Element} cn
     *   The .media--ratio--fluid container HTML element.
     */
    updateRatio: function (cn) {
      var me = this;
      var el = _d.closest(cn, '.blazy');
      var dimensions = _d.parse(_d.attr(cn, _dataDimensions));

      if (!dimensions) {
        me.updateFallbackRatio(cn);
        return;
      }

      // For picture, this is more a dummy space till the image is downloaded.
      var isPicture = _d.find(cn, 'picture') !== null && _resizeTick > 0;
      var pad = _d.activeWidth(dimensions, isPicture);

      // Provides marker for grouping between multiple instances.
      cn.blazyInstance = el !== null && 'blazyInstance' in el ? el.blazyInstance : null;
      if (!_d.isUndefined(pad)) {
        cn.style.paddingBottom = pad + '%';
      }

      // Fix for picture or bg element with resizing.
      if (_resizeTick > 0 && (isPicture || _d.hasAttr(cn, _dataBg))) {
        me.updateContainer((isPicture ? _d.find(cn, 'img') : cn), cn);
      }
    },

    updateFallbackRatio: function (cn) {
      // Only rewrites if the style is indeed stripped out by Twig, and not set.
      if (!_d.hasAttr(cn, 'style') && _d.hasAttr(cn, _dataRatio)) {
        cn.style.paddingBottom = _d.attr(cn, _dataRatio) + '%';
      }
    },

    /**
     * Swap lazy attributes to let supportive browsers lazy load them.
     *
     * This means Blazy and even IO should not lazy-load them any more.
     * Ensures to not touch lazy-loaded AJAX, or likely non-supported elements:
     * Video, DIV, etc. Only IMG and IFRAME are supported for now.
     * Due to native init is deferred, the first row is still using IO/ bLazy.
     */
    doNativeLazy: function () {
      var me = this;

      if (!me.isNativeLazy()) {
        return;
      }

      var doc = me.context;

      me.items = _d.findAll(doc, me.selector('[loading]'));
      if (me.items.length === 0) {
        return;
      }

      var onNativeEvent = function (e) {
        var el = e.target;
        var er = e.type === 'error';

        // Refines based on actual result, runs clearing, animation, etc.
        _d.addClass(el, me.options[er ? 'errorClass' : 'successClass']);
        me.clearing(el);

        _d.unbindEvent(el, e.type, onNativeEvent);
      };

      var doNative = function (el) {
        // Reset attributes, and let supportive browsers lazy load natively.
        _d.setAttr(el, ['srcset', 'src'], true);

        // Also supports PICTURE or (future) VIDEO which contains SOURCEs.
        _d.setAttrsWithSources(el, false, true);

        // Blur thumbnail is just making use of the swap due to being small.
        if (_d.hasClass(el, 'b-blur')) {
          _d.attr(el, 'loading', null);
        }
        else {
          // Mark it loaded to prevent bLazy/ IO to do any further work.
          _d.addClass(el, me.options.successClass);

          // Attempts to make nice with the harsh native, defer clearing, etc.
          _d.bindEvent(el, 'load', onNativeEvent);
          _d.bindEvent(el, 'error', onNativeEvent);
        }
      };

      var onNative = function () {
        _d.forEach(me.items, doNative);
      };

      _d.bindEvent(doc, 'blazy.native', onNative, {
        once: true
      });
    },

    isNativeLazy: function () {
      return 'loading' in HTMLImageElement.prototype;
    },

    isIo: function () {
      return this.ioSettings && this.ioSettings.enabled && 'IntersectionObserver' in _win;
    },

    isRo: function () {
      return 'ResizeObserver' in _win;
    },

    isBlazy: function () {
      return !this.isIo() && 'Blazy' in _win;
    },

    run: function (opts) {
      return this.isIo() ? new BioMedia(opts) : new Blazy(opts);
    },

    afterInit: function () {
      var me = this;
      var doc = me.context;
      var rObserver = false;
      var ratioItems = _d.findAll(doc, '.media--ratio');
      var shouldLoop = ratioItems.length > 0;

      var loopRatio = function (entries) {
        me.windowWidth = _d.windowWidth();

        // BC with bLazy, native/IO doesn't need to revalidate, bLazy does.
        // Scenarios: long horizontal containers, Slick carousel slidesToShow >
        // 3. If any issue, add a class `blazy--revalidate` manually to .blazy.
        if (!me.isNativeLazy() && (me.isBlazy() || me.revalidate)) {
          me.init.revalidate(true);
        }

        if (shouldLoop) {
          _d.forEach(entries, function (entry) {
            me.updateRatio('target' in entry ? entry.target : entry);
          }, doc);
        }

        _resizeTick++;
        return false;
      };

      var checkRatio = function () {
        return me.isRo() ? new ResizeObserver(loopRatio) : loopRatio(ratioItems);
      };

      // Checks for aspect ratio, onload event is a bit later.
      // Uses ResizeObserver for modern browsers, else degrades.
      rObserver = checkRatio();
      if (rObserver) {
        if (shouldLoop) {
          _d.forEach(ratioItems, function (entry) {
            rObserver.observe(entry);
          }, doc);
        }
      }
      else {
        _d.bindEvent(_win, 'resize', Drupal.debounce(checkRatio, 200, true));
      }
    }

  };

}(Drupal, drupalSettings, dBlazy, this, this.document));
