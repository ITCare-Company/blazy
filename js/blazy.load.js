/**
 * @file
 * Provides native, Intersection Observer API, or bLazy lazy loader.
 *
 * This file is not loaded when `No JavaScript` lazy loader is enabled. It is
 * for those who still wants to support IE9+, and similar oldies. The bLazy
 * library supports IE7+, but the module only tested it at IE9+ years ago.
 * There might new IE issues due to latest devs, but could be fixed as needed.
 *
 * @todo convert to dBlazy object where chaining is need or appropriate.
 * @todo move out some part which might be relevant for both native and script.
 */

(function ($, Drupal, drupalSettings, _win, _doc) {

  'use strict';

  var _id = 'blazy';
  var _mounted = _id + '--on';
  var _element = '.' + _id + ':not(.' + _mounted + ')';
  var _elementGlobal = 'html';
  var _data = 'data';
  var _isNativeExecuted = false;
  var _loading = 'loading';
  var _checked = 'b-checked';
  var _successClass = 'successClass';
  var _errorClass = 'errorClass';
  var _image = 'image';
  var _src = 'src';
  var _events = 'load.bload error.bload';
  var _eventNative = _id + '.native';
  var _eventDone = _id + '.done';

  /**
   * Blazy public methods.
   *
   * @namespace
   */
  Drupal.blazy = $.extend(Drupal.blazy || {}, {

    run: function (opts) {
      return this.isIo() ? new BioMedia(opts) : new Blazy(opts);
    },

    clearing: function (el) {
      var me = this;

      // Clear loading classes.
      // @todo move it into MutationObserver to support both native + loader.
      $.unloading(el);

      // Reevaluate the element for errors, or IE.
      me.reevaluate(el);

      // Provides event listeners for easy overrides without full overrides.
      // Runs before native to allow native use this on its own onload event.
      $.trigger(el, _eventDone, {
        options: me.options
      });

      // Initializes the native lazy loading once the first found is loaded.
      // This is a delayed loading due to native lazy early load.
      if (!_isNativeExecuted) {
        $.trigger(me.context, _eventNative, {
          options: me.options
        });

        _isNativeExecuted = true;
      }
    },

    /**
     * Attempts to fix for Views rewrite stripping out data URI causing 404.
     *
     * This is not needed by `No JavaScript` version due to no placeholders.
     *
     * E.g.: src="image/jpg;base64 should be src="data:image/jpg;base64.
     * The browsers load it as https://mysite.com/image/jpg... which causes 404.
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

    // @todo re-check if `No JavaScript` version needs help, likely IE one.
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
      // @todo move it into blazy.compat.js to help failing native at IE.
      if (_win.picturefill && ie) {
        _win.picturefill({
          reevaluate: true,
          elements: [el]
        });
      }
    },

    /**
     * Swap lazy attributes to let supportive browsers lazy load them.
     *
     * This is not needed by `No JavaScript` version due to no placeholders.
     *
     * This means Blazy and even IO should not lazy-load them any more.
     * Ensures to not touch lazy-loaded AJAX, or likely non-supported elements:
     * Video, DIV, etc. Only IMG and IFRAME are supported for now.
     * Due to native init is deferred, the first row is still using IO/ bLazy.
     */
    nativeLazy: function () {
      var me = this;
      var opts = me.options;

      if (!me._isNative) {
        return;
      }

      var doc = me.context;

      var els = $.findAll(doc, me.selector('[' + _loading + ']:not(.b-blur)'));
      if ($.isEmpty(els)) {
        return;
      }

      var onNativeEvent = function (e) {
        var el = e.target;
        var er = e.type === 'error';

        // Refines based on actual result, runs clearing, animation, etc.
        $.addClass(el, opts[er ? _errorClass : _successClass]);

        me.clearing(el);
      };

      var onNative = function () {
        me.mapAttr(els);

        $.each(els, function (el) {
          // Attempts to make nice with the harsh native, defer clearing, etc.
          $.one(el, _events, onNativeEvent);
        });
      };

      // This is delayed, triggered after the first row loaded once.
      $.one(doc, _eventNative, onNative);
    }

  });

  /**
   * Initialize the blazy instance, either basic, advanced, or native.
   *
   * This is not needed by `No JavaScript` version due to no libraries.
   *
   * @param {HTMLElement} context
   *   The documentElement.
   */
  var init = function (context) {
    var me = Drupal.blazy;
    var opts = {
      mobileFirst: false
    };

    // Set docroot in case we are in an iframe.
    if (!_doc.documentElement.isSameNode(context)) {
      opts.root = context;
    }

    opts = $.extend({}, me.globals(), me.options, opts);

    // Old bLazy, not IO, might need scrolling CSS selector like Modal library.
    // A scrolling modal with an iframe like Entity Browser has no issue since
    // the scrolling container is the entire DOM. Another use case is parallax.
    var scrollElms = '#drupal-modal, .is-b-scroll';
    if (opts.container) {
      scrollElms += ', ' + opts.container.trim();
    }

    opts.container = scrollElms;
    me.options = opts;

    // Attempts to fix for Views rewrite stripping out data URI causing 404.
    me.fixDataUri();

    // Swap lazy attributes to let supportive browsers lazy load them.
    me.nativeLazy();

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
  function process(elm) {
    var me = Drupal.blazy;
    var opts = $.parse($.attr(elm, 'data-' + _id));
    var isUniform = $.hasClass(elm, _id + '--field block-grid ' + _id + '--uniform');
    var instance = (Math.random() * 10000).toFixed(0);
    var eventId = _id + '.uniform.' + instance;
    var localItems = $.findAll(elm, '.media--ratio');

    me.options = $.extend(me.options, opts);
    me.revalidate = me.revalidate || $.hasClass(elm, _id + '--revalidate');

    $.addClass(elm, _mounted);

    // Each cointainer may have different image styles and aspect ratio.
    // Provides marker to call event once, since adding classes make no sense.
    // @todo this can be removed when we figure out a better solution.
    elm.dblazy = instance;
    elm.dbuniform = isUniform;

    me.instances.push(elm);

    // @todo re-check if `No JavaScript` version needs help with reflows.
    // @todo move it to blazy.ro.js if also needed there.
    var swapRatio = function (e) {
      var pad = e.detail.pad || 0;

      if (pad > 10) {
        $.each(localItems, function (cn) {
          cn.style.paddingBottom = pad + '%';
        });
      }
    };

    // Triggered per .blazy container, not .b-lazy item on resizing to reduce
    // abrupt ratio changes for the rest after the first loaded.
    // Basically setting up the fixed frame specific for dynamic Picture as
    // otherwise they apperar collapsed due to slow loaded images.
    // To support resizing, use debounce. To disable use $.one().
    // @see Drupal.blazy.updatePicture() at blazy.observer.js.
    // @todo remove to not support resizing to minimize complication.
    // @todo move it into ResizeObserver if doable otherwise.
    if (isUniform && localItems.length) {
      $.bindEvent(elm, eventId, swapRatio);
    }
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

      var me = Drupal.blazy;
      var doc = $.context(context);

      me.context = doc;

      // Processes .blazy, if available, without initialization.
      // Initialization is not per container to also support IO with root.
      // @todo replace with core/once when min D9.2, and or after sub-modules.
      $.once(process, _element, doc);

      // Initializes blazy once as a global observer, not per container.
      $.once(init, _elementGlobal, doc);
    }
  };

}(dBlazy, Drupal, drupalSettings, this, this.document));
