/**
 * @file
 * Provides native, Intersection Observer API, or bLazy lazy loader.
 *
 * @todo convert to dBlazy object where chaining is needed, or appropriate.
 */

(function ($, Drupal, drupalSettings, _win, _doc) {

  'use strict';

  var _id = 'blazy';
  var _dblazy = 'd' + _id;
  var _data = 'data';
  var _dataAnimation = _data + '-animation';
  var _dataDimensions = _data + '-dimensions';
  var _dataBg = _data + '-b-bg';
  var _dataRatio = _data + '-ratio';
  var _isNativeExecuted = false;
  var _resizeTick = 0;
  var _picture = 'picture';
  var _loading = 'loading';
  var _checked = 'b-checked';
  var _elBlur = '.b-blur';
  var _successClass = 'successClass';
  var _errorClass = 'errorClass';
  var _image = 'image';
  var _media = 'media';
  var _src = 'src';
  var _eventNative = _id + '.native';
  var _eventDone = _id + '.done';

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

      return $.extend(me.blazySettings, me.ioSettings, commons);
    },

    winData: function () {
      var me = this;
      return {w: me.windowWidth, up: me.options.mobileFirst};
    },

    selector: function (suffix) {
      suffix = suffix || '';
      var opts = this.options;
      return opts.selector + suffix + ':not(.' + opts[_successClass] + ')';
    },

    clearing: function (el) {
      var me = this;
      var cn = $.closest(el, '.' + _media);
      var an = $.closest(el, '[' + _dataAnimation + ']');

      // Clear loading classes.
      $.unloading(el);

      // Reevaluate the element for errors, or IE.
      me.reevaluate(el);

      // Container might be the el itself for BG, do not NULL check here.
      updateContainer.call(me, el, cn);

      // Supports blur, animate.css for CSS background, picture, image, media.
      if (an || $.hasAttr(el, _dataAnimation)) {
        $.animate(an || el);
      }

      // Provides event listeners for easy overrides without full overrides.
      // Runs before native to allow native use this on its own onload event.
      $.trigger(el, _eventDone, {
        options: me.options
      });

      // Initializes the native lazy loading once the first found is loaded.
      if (!_isNativeExecuted) {
        $.trigger(me.context, _eventNative, {
          options: me.options
        });

        _isNativeExecuted = true;
      }
    },

    isLoaded: function (el) {
      return $.hasClass(el, this.options[_successClass]);
    },

    // Only do this to fix errors, revalidation.
    load: function (cn) {
      var me = this;

      // DOM ready fix.
      _win.setTimeout(function () {
        var elms = $.findAll(cn || _doc, me.selector());

        if (elms.length) {
          $.each(elms, me.update.bind(me));
        }
      }, 100);
    },

    update: function (el, delayed) {
      var me = this;
      var _update = function () {
        if ($.hasAttr(el, _dataBg)) {
          $.bg(el, me.winData());
        }
        else {
          if (me.init) {
            me.init.load(el);
          }
        }
      };

      delayed = delayed || false;
      if (delayed) {
        // DOM ready fix.
        _win.setTimeout(_update, 100);
      }
      else {
        _update();
      }
    },

    reevaluate: function (el) {
      var me = this;
      var ie = $.hasClass(el, 'b-responsive') && $.hasAttr(el, _data + '-pfsrc');

      // In case an error, try forcing it, once.
      if ($.hasClass(el, me.options[_errorClass]) && !$.hasClass(el, _checked)) {
        $.addClass(el, _checked);

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
    fixDataUri: function () {
      var me = this;
      var els = $.findAll(me.context, me.selector('[src^="' + _image + '"]'));

      var _fix = function (img) {
        var src = $.attr(img, _src);
        if ($.contains(src, ['base64', 'svg+xml'])) {
          $.attr(img, _src, src.replace(_image, _data + ':' + _image));
        }
      };

      if (els.length) {
        $.each(els, _fix);
      }
    },

    onLoaded: function (root, cb, observer) {
      var me = this;
      var elms = $.findAll(root, me.options.selector + ':not(' + _elBlur + ')');
      var isMe = elms.length;

      if (!isMe) {
        elms = $.findAll(root, 'img:not(' + _elBlur + ')');
      }

      if (elms.length) {
        $.each(elms, function (el) {
          var type = isMe ? _eventDone : 'load';
          $.one(el, type, cb, isMe);

          if (observer) {
            observer.observe(el);
          }
        });
      }
    },

    checkResize: function (items, cb, root, onDone) {
      var me = this;
      var observer = false;
      var processor = function (entries) {
        me.windowWidth = $.windowWidth();

        _resizeTick++;
        return cb(entries);
      };

      var observe = function () {
        return me.isRo() ? new ResizeObserver(processor) : processor(items);
      };

      // Checks for aspect ratio, onload event is a bit later.
      // Uses ResizeObserver for modern browsers, else degrades.
      observer = observe();
      if (items.length) {
        if (observer) {
          $.each(items, function (item) {
            observer.observe(item);
          });
        }
        else {
          $.bindEvent(_win, 'resize', Drupal.debounce(observe, 200, true));
        }
      }

      // When images are loaded, Flexbox or Native Grid as Masonry might need
      // info about the loaded image dimensions to calculate gaps or positions.
      if (onDone && $.isFun(onDone)) {
        me.onLoaded(root, onDone, observer);
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
    nativeLazy: function () {
      var me = this;
      var opts = me.options;

      if (!me.isNative()) {
        return;
      }

      var doc = me.context;

      me.items = $.findAll(doc, me.selector('[' + _loading + ']'));
      if ($.isEmpty(me.items)) {
        return;
      }

      var onNativeEvent = function (e) {
        var el = e.target;
        var er = e.type === 'error';

        // Refines based on actual result, runs clearing, animation, etc.
        $.addClass(el, opts[er ? _errorClass : _successClass]);
        me.clearing(el);
      };

      var doNative = function (el) {
        // Reset attributes, and let supportive browsers lazy load natively.
        $.mapAttr(el, ['srcset', _src], true);

        // Also supports PICTURE or (future) VIDEO which contains SOURCEs.
        $.mapSource(el, false, true);

        // Blur thumbnail is just making use of the swap due to being small.
        if ($.hasClass(el, 'b-blur')) {
          $.removeAttr(el, _loading);
        }
        else {
          // Mark it loaded to prevent bLazy/ IO to do any further work.
          $.addClass(el, opts[_successClass]);

          // Attempts to make nice with the harsh native, defer clearing, etc.
          $.one(el, 'load error', onNativeEvent);
        }
      };

      var onNative = function () {
        $.each(me.items, doNative);
      };

      $.one(doc, _eventNative, onNative);
    },

    isNative: function () {
      return _loading in HTMLImageElement.prototype;
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
      var ratioItems = $.findAll(doc, '.' + _media + '--ratio');
      var shouldLoop = ratioItems.length > 0;

      var loopRatio = function (entries) {
        // BC with bLazy, native/IO doesn't need to revalidate, bLazy does.
        // Scenarios: long horizontal containers, Slick carousel slidesToShow >
        // 3. If any issue, add a class `blazy--revalidate` manually to .blazy.
        if (!me.isNative() && (me.isBlazy() || me.revalidate)) {
          me.init.revalidate(true);
        }

        if (shouldLoop) {
          $.each(entries, function (entry) {
            updateRatio.call(me, entry.target || entry);
          });
        }
        return false;
      };

      me.checkResize(ratioItems, loopRatio, doc);
    }

  };

  // Private non-reusable functions.
  function updatePicture(el, cn) {
    var me = this;
    var pad = Math.round(((el.naturalHeight / el.naturalWidth) * 100), 2);
    var elms = me.instances;

    cn.style.paddingBottom = pad + '%';

    // Swap all aspect ratio once to reduce abrupt ratio changes for the rest.
    if (elms.length) {
      var picture = function (elm) {
        if ((_dblazy in elm) && ('dbuniform' in elm)) {
          if ((elm.dblazy === cn.dblazy) && (_resizeTick > 1 || !('dbpicture' in elm))) {
            $.trigger(elm, _id + '.uniform.' + elm.dblazy, {
              pad: pad
            });
            elm.dbpicture = true;
          }
        }
      };

      // Uniform sizes must apply to each instance, not globally.
      $.each(elms, function (elm) {
        Drupal.debounce(picture(elm), 201, true);
      }, me);
    }
  }

  /**
   * Updates the dynamic multi-breakpoint aspect ratio: bg, picture or image.
   *
   * This only applies to Responsive images with aspect ratio fluid.
   * Static ratio (media--ratio--169, etc.) is ignored and uses CSS instead.
   *
   * @param {Element} cn
   *   The .media--ratio--fluid container HTML element.
   */
  function updateRatio(cn) {
    if (!$.isElm(cn)) {
      return;
    }

    var me = this;
    // Blazy container (via formatter or Views style) is not always there.
    var el = $.closest(cn, '.' + _id);
    var dimensions = $.parse($.attr(cn, _dataDimensions));

    if (!dimensions) {
      fallbackRatio(cn);
      return;
    }

    // For picture, this is more a dummy space till the image is downloaded.
    var isPicture = $.isElm($.find(cn, _picture)) && _resizeTick > 0;
    var data = $.extend(me.winData(), {up: isPicture});
    var pad = $.activeWidth(dimensions, data);

    // Provides marker for grouping between multiple instances.
    cn.dblazy = $.isElm(el) && _dblazy in el ? el.dblazy : null;
    if (!$.isUnd(pad)) {
      cn.style.paddingBottom = pad + '%';
    }

    // Fix for picture or bg element with resizing.
    if (_resizeTick > 0 && (isPicture || $.hasAttr(cn, _dataBg))) {
      updateContainer.call(me, (isPicture ? $.find(cn, 'img') : cn), cn);
    }
  }

  function fallbackRatio(cn) {
    var value = $.attr(cn, _dataRatio);
    // Only rewrites if the style is indeed stripped out by Twig, and not set.
    if (!$.hasAttr(cn, 'style') && value) {
      cn.style.paddingBottom = value + '%';
    }
  }

  function updateContainer(el, cn) {
    var me = this;
    var isPicture = $.equal(el.parentNode, _picture) && $.hasAttr(cn, _dataDimensions);

    // Fixed for effect Blur messes up Aspect ratio Fluid calculation.
    _win.setTimeout(function () {
      if (me.isLoaded(el)) {
        // Adds context for effetcs: blur, etc. considering BG, or just media.
        $.addClass($.hasClass(cn, _media) ? cn : el, 'is-b-loaded');

        // Only applies to ratio fluid.
        if (isPicture) {
          updatePicture.call(me, el, cn);
        }

        // Basically makes multi-breakpoint BG work for IO or old bLazy once.
        if ($.hasAttr(el, _dataBg)) {
          $.bg(el, me.winData());
        }
      }
    });
  }

}(dBlazy, Drupal, drupalSettings, this, this.document));
