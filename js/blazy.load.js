/**
 * @file
 * Provides bLazy loader.
 *
 * @todo: Use Vanilla JS.
 */

(function ($, Drupal, drupalSettings, window, document) {

  'use strict';

  /**
   * Blazy public methods.
   *
   * @namespace
   */
  Drupal.blazy = Drupal.blazy || {
    init: null,
    windowWidth: 0,
    globals: function () {
      var me = this;
      var settings = drupalSettings.blazy || {};
      var commons = {
        success: me.clearing,
        error: me.clearing
      };

      return $.extend(settings, commons);
    },

    clearing: function (elm) {
      // .b-lazy can be attached to IMG, or DIV as CSS background.
      var l = $(elm).closest('.is-loading');
      var c = l.length ? l : $(elm).closest('[class*="loading"]');

      $(elm).removeClass('media--loading').parentsUntil(c.parent()).removeClass(function (i, css) {
        return (css.match(/(\S+)loading/g) || []).join(' ');
      });
    },

    // Thanks to https://github.com/louisremi/jquery-smartresize
    resizing: function (c, t) {
      window.onresize = function () {
        window.clearTimeout(t);
        t = window.setTimeout(c, 200);
      };
      return c;
    }
  };

  /**
   * Blazy utility functions.
   *
   * @param {int} i
   *   The index of the current element.
   * @param {HTMLElement} elm
   *   The .blazy HTML element.
   */
  function blazyLoad(i, elm) {
    var me = Drupal.blazy;
    var $elm = $(elm);
    var globals = me.globals();
    var data = $elm.data('blazy') || {};
    var opts = data ? $.extend({}, globals, data) : globals;
    var $ratio = $('.media--ratio', elm).length ? $('.media--ratio', elm) : null;

    /**
     * Updates aspect ratio.
     *
     * @param {int} i
     *   The index of the current element.
     * @param {HTMLElement} item
     *   The .b-lazy HTML element.
     */
    function updateRatio(i, item) {
      var $item = $(item);
      var dimensions = $item.data('dimensions') || data.dimensions || null;
      var pad = null;
      var keys;

      if (dimensions === null) {
        return;
      }

      keys = Object.keys(dimensions);
      var xs = keys[0];
      var xl = keys[keys.length - 1];

      $.each(dimensions, function (w, v) {
        if (w >= me.windowWidth) {
          pad = v;
          return false;
        }
      });

      if (pad === null) {
        pad = dimensions[me.windowWidth >= xl ? xl : xs];
      }

      if (pad !== null) {
        $item.css({
          paddingBottom: pad + '%'
        });
      }
    }

    // Initializes Blazy.
    me.init = new Blazy(opts);

    me.resizing(function () {
      me.windowWidth = window.innerWidth || document.documentElement.clientWidth || $(window).width();

      if ($ratio !== null) {
        $ratio.each(updateRatio);
      }

      $elm.trigger('resizing', [me.windowWidth]);
    })();
  }

  /**
   * Attaches blazy behavior to HTML element identified by [data-blazy].
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazy = {
    attach: function (context) {
      var me = Drupal.blazy;
      var $blazy = $('[data-blazy]', context);
      var globals = me.globals();

      // Executes basic Blazy when no [data-blazy] found like a single image.
      if (!$blazy.length) {
        me.init = new Blazy(globals);
        return;
      }

      $blazy.once('blazy').each(blazyLoad);
    }
  };

}(jQuery, Drupal, drupalSettings, this, this.document));
