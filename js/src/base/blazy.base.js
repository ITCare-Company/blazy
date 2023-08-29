/**
 * @file
 * Provides base methods to bridge drupal-related codes with generic ones.
 *
 * @todo watch out for Drupal namespace removal, likely becomes under window.
 */

(function ($, Drupal, _win) {

  'use strict';

  $.debounce = function (cb, arg, scope, delay) {
    var _cb = function () {
      cb.call(scope, arg);
    };

    Drupal.debounce(_cb, delay || 201, true);
  };

  $.matchMedia = function (width, minmax) {
    if (_win.matchMedia) {
      if ($.isUnd(minmax)) {
        minmax = 'max';
      }
      var mq = _win.matchMedia('(' + minmax + '-device-width: ' + width + ')');
      return mq.matches;
    }
    return false;
  };

  function is(el, name) {
    el = el.target || el;
    return $.hasClass(el, name);
  }

  $.isBg = function (el, opts) {
    return is(el, opts && opts.bgClass || 'b-bg');
  };

  $.isBlur = function (el) {
    return is(el, 'b-blur');
  };

  $.isGrid = function (el) {
    el = el.target || el;
    return $.isElm($.closest(el, '.grid'));
  };

  $.isHtml = function (el, opts) {
    return is(el, 'b-html');
  };

  $.image = {

    alt: function (el, fallback) {
      var img = $.find(el, 'img:not(.b-blur)');
      var alt = $.attr(img, 'alt');

      fallback = fallback || 'Video preview';

      // If using BG.
      if (!alt) {
        var cn = $.find(el, '.media');
        alt = $.attr(cn, 'title');
      }

      // If nobody put the important info, add a fallback.
      return alt ? Drupal.checkPlain(alt) : Drupal.t(fallback);
    },

    ratio: function (data) {
      var width = data.width ? parseInt(data.width, 0) : 640;
      var height = data.height ? parseInt(data.height, 0) : 360;
      return data ? ((height / width) * 100).toFixed(2) : 100;
    },

    dimension: function (w, h) {
      return {
        width: w,
        height: h
      };
    },

    hack: function (a, b) {
      return {
        paddingBottom: a,
        height: b
      };
    }
  };

  $.thirdPartyScript = {
    attach: function (provider, callback, delay) {
      _win.setTimeout(function () {
        // Instagram, Twitter are good, except for Pinterest.
        if (provider === 'pinterest' && _win.PinUtils) {
          _win.PinUtils.build();
        }

        if (callback) {
          callback();
        }
      }, delay || 101);
    }
  };

})(dBlazy, Drupal, this);
