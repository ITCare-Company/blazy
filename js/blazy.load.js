/**
 * @file
 * Provides native, Intersection Observer API, or bLazy lazy loader.
 */

(function ($, Drupal, drupalSettings, _doc) {

  'use strict';

  var _id = 'blazy';
  var _mounted = _id + '--on';
  var _element = '.' + _id + ':not(.' + _mounted + ')';
  var _elementGlobal = 'html';

  Drupal.blazy = Drupal.blazy || {};

  /**
   * Initialize the blazy instance, either basic, advanced, or native.
   *
   * @param {HTMLElement} context
   *   The documentElement.
   */
  var initBlazy = function (context) {
    var me = Drupal.blazy;
    var opts = {mobileFirst: false};

    // Set docroot in case we are in an iframe.
    if (!_doc.documentElement.isSameNode(context)) {
      opts.root = context;
    }

    // Old bLazy, not IO, might need scrolling CSS selector like Modal library.
    // A scrolling modal with an iframe like Entity Browser has no issue since
    // the scrolling container is the entire DOM. Another use case is parallax.
    var scrollElms = '#drupal-modal, .is-b-scroll';
    if (opts.container) {
      scrollElms += ', ' + opts.container.trim();
    }

    opts.container = scrollElms;
    me.options = $.extend({}, me.globals(), opts);

    // Attempts to fix for Views rewrite stripping out data URI causing 404.
    me.fixMissingDataUri();

    // Swap lazy attributes to let supportive browsers lazy load them.
    me.doNativeLazy();

    // Put the blazy/IO instance into a public object for references/ overrides.
    // If native lazy load is supported, the following will skip internally.
    me.init = me.run(me.options);

    // Runs after init.
    me.afterInit();
  };

  /**
   * Blazy utility functions.
   *
   * @param {HTMLElement} elm
   *   The .blazy/[data-blazy] container, not the lazyloaded .b-lazy element.
   */
  function doBlazy(elm) {
    var me = Drupal.blazy;
    var opts = $.parse($.attr(elm, 'data-blazy'));
    var isUniform = $.hasClass(elm, 'blazy--field block-grid blazy--uniform');
    var instance = (Math.random() * 10000).toFixed(0);
    var eventId = 'blazy.uniform.' + instance;
    var localItems = $.findAll(elm, '.media--ratio');

    me.options = $.extend(me.options, opts);
    me.revalidate = me.revalidate || $.hasClass(elm, 'blazy--revalidate');

    $.addClass(elm, _mounted);
    elm.blazyInstance = instance;

    if (isUniform) {
      elm.blazyUniform = true;
    }

    me.instances.push(elm);

    var swapRatio = function (e) {
      var pad = e.detail.pad || 0;

      if (pad > 10) {
        $.forEach(localItems, function (cn) {
          cn.style.paddingBottom = pad + '%';
        }, elm);
      }
    };

    // Reduces abrupt ratio changes for the rest after the first loaded.
    // To support resizing, use debounce. To disable use {once: true}.
    if (isUniform && localItems.length > 0) {
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

      var doc = $.context(context);
      Drupal.blazy.context = doc;

      // Processes .blazy, if available, without initialization.
      // Initialization is not per container to also support IO with root.
      // @todo replace with core/once when min D9.2, and or after sub-modules.
      $.once(doBlazy, _element, doc);

      // Initializes blazy once as a global observer, not per container.
      $.once(initBlazy, _elementGlobal, doc);
    }
  };

}(dBlazy, Drupal, drupalSettings, this.document));
