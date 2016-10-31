/**
 * @file
 * Provides bLazy loader.
 */

(function ($, Drupal, drupalSettings, window, document) {

  'use strict';

  /**
   * Attaches blazy behavior to HTML element identified by [data-blazy].
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazy = {
    attach: function (context) {
      var me = Drupal.blazy;
      var $blazy = $('[data-blazy]', context);
      var globals = me.globalSettings();

      if (!$blazy.length) {
        me.init = new Blazy(globals);
      }

      $blazy.once('blazy').each(function () {
        var $elm = $(this);
        var data = $elm.data('blazy') || {};
        var options = $.extend({}, globals, data);

        me.init = new Blazy(options);

        $elm.data('blazy', options);

        me.resizing(function () {
          me.windowWidth = window.innerWidth || document.documentElement.clientWidth || $(window).width();

          $('.media--ratio', context).each(me.updateRatio);

          $elm.trigger('resizing', [me.windowWidth]);
        })();
      });
    }
  };

  /**
   * Blazy methods.
   *
   * @namespace
   */
  Drupal.blazy = Drupal.blazy || {
    init: null,
    windowWidth: 0,
    globalSettings: function () {
      var me = this;
      var settings = drupalSettings.blazy || {};
      var commons = {
        success: function (elm) {
          me.clearing(elm);
        },
        error: function (elm, msg) {
          me.clearing(elm);
        }
      };

      return $.extend(settings, commons);
    },

    updateRatio: function (i, item) {
      var me = Drupal.blazy;
      var $item = $(item);
      var $blazy = $item.closest('[data-blazy]');
      var dataGlobal = $blazy.length && $blazy.data('blazy') ? $blazy.data('blazy') : {};
      var dimensions = $item.data('dimensions') || dataGlobal.dimensions || null;
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
    },

    clearing: function (elm) {
      // .b-lazy can be attached to IMG, or DIV as CSS background.
      $(elm).removeClass('media--loading').parents('[class*="loading"]').removeClass(function (index, css) {
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

}(jQuery, Drupal, drupalSettings, this, this.document));
