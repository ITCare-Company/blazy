/**
 * @file
 * Provides Intersection Observer API loader for media using data-[SRC|SRCSET].
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
 * @see https://developers.google.com/web/updates/2016/04/intersectionobserver
 * @todo refactor to fallback to native right here, not on the loaders, to avoid
 * all or nothing, and degrades gracefully.
 */

/* global define, module */
(function (root, factory) {

  'use strict';

  var ns = 'BioMedia';
  var db = root.dBlazy;
  var bio = root.Bio;

  // Inspired by https://github.com/addyosmani/memoize.js/blob/master/memoize.js
  if (typeof define === 'function' && define.amd) {
    // AMD. Register as an anonymous module.
    define([ns, db, bio], factory);
  }
  else if (typeof exports === 'object') {
    // Node. Does not work with strict CommonJS, but only CommonJS-like
    // environments that support module.exports, like Node.
    module.exports = factory(ns, db, bio);
  }
  else {
    // Browser globals (root is window).
    root[ns] = factory(ns, db, bio);
  }
})(this, function (ns, $, _b) {

  'use strict';

  /**
   * Private variables.
   */
  var _data = 'data-';
  var _src = 'src';
  var _srcSet = 'srcset';
  var _dataSrc = _data + _src;
  var _dataSrcset = _data + _srcSet;
  var _bgSrc = _dataSrc;
  var _bgSources = [_src];
  var _imgSources = [_srcSet, _src];

  // Inherits Bio prototype.
  var _proto = Bio.prototype;
  var fn = BioMedia.prototype = Object.create(_proto);
  fn.constructor = BioMedia;

  /**
   * Constructor for BioMedia, Blazy IntersectionObserver for media.
   *
   * @param {object} options
   *   The BioMedia options.
   *
   * @return {object}
   *   The BioMedia instance.
   *
   * @namespace
   */
  function BioMedia(options) {
    var me = _b.apply($.extend(_proto, $.map(fn, this)), arguments);

    me.name = ns;

    return me;
  }

  // Extends Bio prototype.
  fn.lazyLoad = function (el) {
    // Image may take time to load after being hit, and it may be intersected
    // several times till marked loaded. Ensures it is hit once regardless
    // of being loaded, or not. No real issue with normal images on the page,
    // until having VIS alike which may spit out new images on AJAX request.
    if (el.biohit) {
      return;
    }

    var me = this;
    var parent = el.parentNode;
    var isImage = $.equal(el, 'img');
    var isBg = $.isUnd(el.src) && $.hasClass(el, me.options.bgClass);
    var isPicture = $.equal(parent, 'picture');
    var isVideo = $.equal(el, 'video');

    // PICTURE elements.
    if (isPicture) {
      $.mapSource(el, _srcSet, true);

      // Tiny controller image inside picture element won't get preloaded.
      $.mapAttr(el, _src, true);
      me.loaded(el, $._ok);
    }
    // VIDEO elements.
    else if (isVideo) {
      $.mapSource(el, _src, true);
      el.load();
      me.loaded(el, $._ok);
    }
    else {
      // IMG or DIV/ block elements got preloaded for better UX with loading.
      if (isImage || isBg) {
        setImage.call(me, el, isBg);
      }
      // IFRAME elements, etc.
      else {
        if ($.hasAttr(el, _src)) {
          if ($.attr(el, _dataSrc)) {
            $.mapAttr(el, _src, true);
          }

          me.loaded(el, $._ok);
        }
      }
    }

    // Marks it hit/ requested. Not necessarily loaded.
    el.biohit = true;
  };

  function setImage(el, isBg) {
    var me = this;
    var img = new Image();
    var isResimage = $.hasAttr(el, _srcSet);

    // Applies attributes regardless, will re-observe if any error.
    var applyAttrs = function () {
      if (isBg) {
        bg(el);
      }
      else {
        $.mapAttr(el, _imgSources, false);
      }
    };

    var load = function (ok) {
      // Image decode fails with Responsive image, assumes ok, no side effects.
      me.loaded(el, ok ? $._ok : $._er);
      if (ok) {
        $.removeAttr(el, isBg ? _bgSources : _imgSources, _data);
      }
    };

    applyAttrs();

    // Preload `img` to have correct event handlers.
    $.decode(img)
      .then(function () {
        load(true);
      })
      .catch(function () {
        load(isResimage);

        // Allows to re-observe.
        if (!isResimage) {
          el.biohit = false;
        }
      });
  }

  // @todo remove for more robust dBlazy.bg with Responsive image.
  function bg(el) {
    if ($.hasAttr(el, _bgSrc)) {
      el.style.backgroundImage = 'url("' + $.attr(el, _bgSrc) + '")';
      $.removeAttr(el, _src);
    }
  };

  return BioMedia;

});
