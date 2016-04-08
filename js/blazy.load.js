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

        // Prevents custom breakpoints from merging, e.g.: 767 x 768.
        if (typeof data.breakpoints !== 'undefined' && typeof globals.breakpoints !== 'undefined') {
          globals.breakpoints = [];
        }

        var options = $.extend({}, globals, data);
        me.init = new Blazy(options);

        $elm.data('blazy', options);

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
        dimensions: false,
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

    updateRatio: function (elm, data) {
      var me = this;
      var th = null;
      var tw = null;
      var ow = data.max !== 'undefined' ? data.max[0] : null;
      var oh = data.max !== 'undefined' ? data.max[1] : null;

      if (!data.dimensions) {
        return;
      }

      var keys = $.map(data.dimensions, function (item, idx) { return idx; });
      var first = keys[0];
      var last = keys[keys.length - 1];

      // This should be easier when Blazy supports mobile first.
      if (first >= me.windowWidth) {
        th = data.dimensions[first].height;
        tw = data.dimensions[first].width;
      }
      else {
        $.each(data.dimensions, function (key, v) {
          if (oh !== null && me.windowWidth > last) {
            th = oh;
            tw = ow;
          }
          else if (key <= me.windowWidth) {
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
      var $ratio = elm.closest('.media--ratio');

      elm.attr('height', th).attr('width', tw);
      if ($ratio.length) {
        $ratio.css({
          paddingBottom: Math.round((th / tw) * 100) + '%'
        });
      }
    },

    clearing: function (elm) {
      var me = this;
      var blazyClasses;
      var $elm = $(elm);
      var $blazy = $elm.closest('[data-blazy]');
      var data = $blazy.data('blazy');

      window.clearTimeout(blazyClasses);
      blazyClasses = window.setTimeout(function () {
        $elm.removeClass('b-error b-loaded').addClass('b-loaded').closest('.media--loading').removeClass('media--loading');
      }, 200);

      if (data && data.dimensions) {
        me.updateRatio($elm, data);
      }

      $blazy.on('resizing', function (e, windowWidth) {
        if (data && data.dimensions) {
          me.updateRatio($elm, data);
        }
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
