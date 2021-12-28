/**
 * @file
 * Provides native, Intersection Observer API, or bLazy lazy loader.
 */

(function (Drupal, drupalSettings, once, _db, _doc) {

  'use strict';

  var _id = 'blazy';
  var _element = '.blazy';
  var _idGlobal = 'blazy-global';
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
    var opts = {};

    opts.mobileFirst = opts.mobileFirst || false;

    // Set docroot in case we are in an iframe.
    if (!_doc.documentElement.isSameNode(context)) {
      opts.root = context;
    }

    me.options = _db.extend({}, me.globals(), opts);

    // Old bLazy, not IO, might need scrolling CSS selector like Modal library.
    // A scrolling modal with an iframe like Entity Browser has no issue since
    // the scrolling container is the entire DOM. Another use case is parallax.
    var scrollElms = '#drupal-modal, .is-b-scroll';
    if (me.options.container) {
      scrollElms += ', ' + me.options.container.trim();
    }
    me.options.container = scrollElms;

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
   *
   * @todo reenable initBlazy here if any issue with the following:
   *   Each [data-blazy] may or may not:
   *     - be ajaxified, be lightboxed, have uniform or different sizes, and
   *       have few more unique features per instance, etc.
   */
  function doBlazy(elm) {
    var me = Drupal.blazy;
    var dataAttr = _db.attr(elm, 'data-blazy');
    var opts = (!dataAttr || dataAttr === '1') ? {} : (_db.parse(dataAttr) || {});
    var isUniform = me.contains(elm, 'blazy--field') || me.contains(elm, 'block-grid') || me.contains(elm, 'blazy--uniform');
    var instance = (Math.random() * 10000).toFixed(0);
    var eventId = 'blazy.uniform.' + instance;
    var localItems = _db.findAll(elm, '.media--ratio');

    me.options = _db.extend(me.options, opts);
    me.revalidate = me.revalidate || elm.classList.contains('blazy--revalidate');
    elm.classList.add('blazy--on');
    elm.blazyInstance = instance;

    if (isUniform) {
      elm.blazyUniform = true;
    }

    me.instances.push(elm);

    var swapRatio = function (e) {
      var pad = e.detail.pad || 0;

      if (pad > 10) {
        _db.forEach(localItems, function (cn) {
          cn.style.paddingBottom = pad + '%';
        }, elm);
      }
    };

    // Reduces abrupt ratio changes for the rest after the first loaded.
    // To support resizing, use debounce. To disable use {once: true}.
    if (isUniform && localItems.length > 0) {
      _db.bindEvent(elm, eventId, swapRatio);
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

      var doc = _db.context(context);
      Drupal.blazy.context = doc;

      // Processes .blazy, if available, without initialization.
      // Initialization is not per container to also support IO with root.
      once(_id, _element, doc).forEach(doBlazy);

      // Initializes blazy.
      once(_idGlobal, _elementGlobal, doc).forEach(initBlazy);
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        if (once.find(_idGlobal, context).length) {
          once.remove(_idGlobal, _elementGlobal, context);
        }
        if (once.find(_id, context).length) {
          once.remove(_id, _element, context);
        }
      }
    }
  };

}(Drupal, drupalSettings, once, dBlazy, this.document));
