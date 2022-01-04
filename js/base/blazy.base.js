/**
 * @file
 * Provides native, Intersection Observer API, or bLazy lazy loader.
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
  var _successClass = 'successClass';
  var _errorClass = 'errorClass';
  var _image = 'image';
  var _media = 'media';
  var _src = 'src';

  /**
   * Blazy public methods.
   *
   * @namespace
   */
  Drupal.blazy = {
    context: null,
    $context: null,
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

    selector: function (suffix) {
      suffix = suffix || '';
      var opts = this.options;
      return opts.selector + suffix + ':not(.' + opts[_successClass] + ')';
    },

    clearing: function (el) {
      var me = this;
      var $el = $(el);
      var cn = $.closest(el, '.' + _media);
      var an = $.closest(el, '[' + _dataAnimation + ']');

      // Clear loading classes.
      $el.unloading();

      // Reevaluate the element for errors, or IE.
      me.reevaluate($el);

      // Container might be the el itself for BG, do not NULL check here.
      updateContainer.call(me, el, cn);

      // Supports blur, animate.css for CSS background, picture, image, media.
      if (an || $el.hasAttr(_dataAnimation)) {
        $(an || el).animate();
      }

      // Provides event listeners for easy overrides without full overrides.
      // Runs before native to allow native use this on its own onload event.
      $el.trigger(_id + '.done', {
        options: me.options
      });

      // Initializes the native lazy loading once the first found is loaded.
      if (!_isNativeExecuted) {
        me.$context.trigger(_id + '.native', {
          options: me.options
        });

        _isNativeExecuted = true;
      }
    },

    isLoaded: function ($el) {
      return $el.hasClass(this.options[_successClass]);
    },

    // Only do this to fix errors, revalidation.
    load: function (cn) {
      var me = this;

      // DOM ready fix.
      _win.setTimeout(function () {
        var elms = $(cn || _doc).findAll(me.selector());

        if (elms.length) {
          $(elms).each(me.update.bind(me));
        }
      }, 100);
    },

    update: function (el, delayed) {
      var me = this;
      var $el = $(el);
      var _update = function () {
        if ($el.hasAttr(_dataBg)) {
          $el.bg(me.options.mobileFirst);
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

    reevaluate: function ($el) {
      var me = this;
      var el = $el[0];
      var ie = $el.hasClass('b-responsive') && $el.hasAttr(_data + '-pfsrc');

      // In case an error, try forcing it, once.
      if ($el.hasClass(me.options[_errorClass]) && !$el.hasClass(_checked)) {
        $el.addClass(_checked);

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
      var els = me.$context.findAll(me.selector('[src^="' + _image + '"]'));

      var _fix = function (img) {
        var $img = $(img);
        var src = $img.attr(_src);
        if ($.contains(src, ['base64', 'svg+xml'])) {
          $img.attr(_src, src.replace(_image, _data + ':' + _image));
        }
      };

      if (els.length) {
        $(els).each(_fix);
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

      var $doc = me.$context;

      me.items = $doc.findAll(me.selector('[' + _loading + ']'));
      if ($.isEmpty(me.items)) {
        return;
      }

      var onNativeEvent = function (e) {
        var el = e.target;
        var er = e.type === 'error';
        var $el = $(el);

        // Refines based on actual result, runs clearing, animation, etc.
        $el.addClass(opts[er ? _errorClass : _successClass]);
        me.clearing(el);

        $el.unbindEvent(e.type, onNativeEvent);
      };

      var doNative = function (el) {
        var $el = $(el);
        // Reset attributes, and let supportive browsers lazy load natively.
        $el.mapAttr(['srcset', _src], true);

        // Also supports PICTURE or (future) VIDEO which contains SOURCEs.
        $el.mapSource(false, true);

        // Blur thumbnail is just making use of the swap due to being small.
        if ($el.hasClass('b-blur')) {
          $el.attr(_loading, null);
        }
        else {
          // Mark it loaded to prevent bLazy/ IO to do any further work.
          $el.addClass(opts[_successClass]);

          // Attempts to make nice with the harsh native, defer clearing, etc.
          $el.bindEvent('load error', onNativeEvent);
        }
      };

      var onNative = function () {
        $(me.items).each(doNative);
      };

      $doc.one(_id + '.native', onNative);
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
      var $doc = me.$context;
      var doc = $doc[0];
      var rObserver = false;
      var ratioItems = $doc.find('.' + _media + '--ratio', true);
      var shouldLoop = ratioItems.length > 0;

      var loopRatio = function (entries) {
        me.windowWidth = $.windowWidth();

        // BC with bLazy, native/IO doesn't need to revalidate, bLazy does.
        // Scenarios: long horizontal containers, Slick carousel slidesToShow >
        // 3. If any issue, add a class `blazy--revalidate` manually to .blazy.
        if (!me.isNative() && (me.isBlazy() || me.revalidate)) {
          me.init.revalidate(true);
        }

        if (shouldLoop) {
          $(entries).each(function (entry) {
            updateRatio.call(me, entry.target || entry);
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
          $(ratioItems).each(function (entry) {
            rObserver.observe(entry);
          }, doc);
        }
      }
      else {
        $.bindEvent(_win, 'resize', Drupal.debounce(checkRatio, 200, true));
      }
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
        var $elm = $(elm);

        if ((_dblazy in elm) && ('dbuniform' in elm)) {
          if ((elm.dblazy === cn.dblazy) && (_resizeTick > 1 || !('dbpicture' in elm))) {
            $elm.trigger(_id + '.uniform.' + elm.dblazy, {
              pad: pad
            });
            elm.dbpicture = true;
          }
        }
      };

      // Uniform sizes must apply to each instance, not globally.
      $(elms).each(function (elm) {
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
    var $cn = $(cn);
    var dimensions = $.parse($cn.attr(_dataDimensions));

    if (!dimensions) {
      fallbackRatio(cn);
      return;
    }

    // For picture, this is more a dummy space till the image is downloaded.
    var isPicture = $cn.find(_picture) && _resizeTick > 0;
    var pad = $.activeWidth(dimensions, isPicture);

    // Provides marker for grouping between multiple instances.
    cn.dblazy = $.isElm(el) && _dblazy in el ? el.dblazy : null;
    if (!$.isUnd(pad)) {
      cn.style.paddingBottom = pad + '%';
    }

    // Fix for picture or bg element with resizing.
    if (_resizeTick > 0 && (isPicture || $cn.hasAttr(_dataBg))) {
      updateContainer.call(me, (isPicture ? $cn.find('img') : cn), cn);
    }
  }

  function fallbackRatio(cn) {
    var $cn = $(cn);
    var value = $cn.attr(_dataRatio);
    // Only rewrites if the style is indeed stripped out by Twig, and not set.
    if (!$cn.hasAttr('style') && value) {
      cn.style.paddingBottom = value + '%';
    }
  }

  function updateContainer(el, cn) {
    var me = this;
    var $el = $(el);
    var $cn = $(cn);
    var isPicture = $(el.parentNode).equal(_picture) && $cn.hasAttr(_dataDimensions);

    // Fixed for effect Blur messes up Aspect ratio Fluid calculation.
    _win.setTimeout(function () {
      if (me.isLoaded($el)) {
        // Adds context for effetcs: blur, etc. considering BG, or just media.
        $($cn.hasClass(_media) ? cn : el).addClass('is-b-loaded');

        // Only applies to ratio fluid.
        if (isPicture) {
          updatePicture.call(this, el, cn);
        }

        // Basically makes multi-breakpoint BG work for IO or old bLazy once.
        if ($el.hasAttr(_dataBg)) {
          $el.bg(me.options.mobileFirst);
        }
      }
    });
  }

}(dBlazy, Drupal, drupalSettings, this, this.document));
