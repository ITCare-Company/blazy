/**
 * @file
 * Provides background extension for dBlazy.
 */

(function ($) {

  'use strict';

  var _dataSrc = 'data-src';
  var _cEncoded = 'is-b-encoded';
  var _data = 'data-b-';
  var _cache = {};

  /**
   * Updates CSS background with multi-breakpoint images.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The container HTML element(s), or dBlazy instance.
   * @param {Object} winData
   *   Containing ww: windowWidth, and up: to use min-width or max-width.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function bg(els, winData) {
    var chainCallback = function (el) {
      if ($.isElm(el)) {
        var url = $.bgUrl(el, winData);

        if (url) {
          el.style.backgroundImage = 'url("' + url + '")';
          $.removeAttr(el, _dataSrc);
        }
      }
    };

    return $.chain(els, chainCallback);
  }

  $.bgUrl = function (el, winData) {
    var str = $.attr(el, _data + 'bg');
    var token = $.attr(el, _data + 'token');
    var data = _cache[token];

    if (!data) {
      if ($.hasClass(el, _cEncoded)) {
        str = atob(str);
      }

      data = $.parse(str);
      _cache[token] = data;
    }

    if (!$.isEmpty(data)) {
      var obj = $.activeWidth(data, winData);
      if (obj && !$.isUnd(obj)) {
        var ratio = obj.ratio;

        // Allows to disable Aspect ratio if it has known/ fixed heights such as
        // gridstack multi-size boxes.
        if (ratio && !$.hasClass(el, 'b-noratio')) {
          el.style.paddingBottom = ratio + '%';
        }
        return obj.src;
      }
    }
    return $.attr(el, _dataSrc);
  };

  $.bg = bg;
  $.fn.bg = function (winData) {
    return bg(this, winData);
  };

}(dBlazy));
