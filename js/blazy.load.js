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
      var globals = Drupal.blazy.globalSettings();

      if (!$blazy.length) {
        me.init = new Blazy(globals);
      }

      $blazy.once('blazy').each(function () {
        var $elm = $(this);
        var data = $elm.data('blazy') || {};

        // Prevents custom breakpoints from merging, e.g.: 767 x 768.
        if (typeof data.breakpoints !== 'undefined' && typeof globals.breakpoints !== 'undefined') {
          globals.breakpoints = [];
        }

        me.init = new Blazy($.extend({}, globals, data));

        me.resizing(function () {
          me.windowWidth = window.innerWidth || document.documentElement.clientWidth || $(window).width();

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
  Drupal.blazy = {
    init: null,
    windowWidth: 0,
    globalSettings: function () {
      var me = this;
      var settings = drupalSettings.blazy || {};
      var commons = {
        ratio: false,
        success: function (elm) {
          me.clearing(elm);
        },
        error: function (elm) {
          me.loadSrcset(elm);
          me.clearing(elm);
        }
      };

      return $.extend(settings, commons);
    },

    // @todo drop for https://github.com/dinbror/blazy/issues/75.
    loadSrcset: function (elm) {
      var $elm = $(elm);
      var srcset = $elm.data('srcset');

      if (!$elm.attr('srcset') && srcset && window.picturefill) {
        $elm.attr('srcset', srcset);
        window.picturefill({reevaluate: true, elements: [elm]});

        $elm.removeAttr('data-srcset');
      }
    },

    updateRatio: function (elm) {
      var me = this;
      var th = null;
      var tw = null;
      var dimensions = me.init.options.dimensions || null;

      if (dimensions === null) {
        return;
      }

      var keys = $.map(dimensions, function (item, idx) { return idx; });
      var first = keys[0];
      var last = keys[keys.length - 1];

      // This should be easier when Blazy supports mobile first.
      if (first >= me.windowWidth) {
        th = dimensions[first].height;
        tw = dimensions[first].width;
      }
      else if (me.windowWidth > last) {
        th = elm.attr('height');
        tw = elm.attr('width');
      }
      else {
        $.each(dimensions, function (key, v) {
          if (key <= me.windowWidth) {
            th = v.height;
            tw = v.width;
          }
        });
      }

      if (th !== null) {
        me.setRatio(elm, th, tw);
      }
    },

    setRatio: function (elm, th, tw) {
      var me = this;

      elm.attr('height', th).attr('width', tw);
      if (me.init.options.ratio && elm.closest('.media--ratio').length) {
        elm.closest('.media--ratio').css({
          paddingBottom: Math.round((th / tw) * 100) + '%'
        });
      }
    },

    clearing: function (elm) {
      var me = this;
      var $elm = $(elm);
      var $blazy = $elm.closest('[data-blazy]');
      var blazyClasses;

      $blazy.on('resizing', function (e, windowWidth) {
        if (me.init.options.dimensions !== 'undefined') {
          me.updateRatio($elm);
        }
      });

      window.clearTimeout(blazyClasses);
      blazyClasses = window.setTimeout(function () {
        $elm.removeClass('b-error b-loaded').addClass('b-loaded').closest('.media--loading').removeClass('media--loading');
      }, 200);
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
