/**
 * @file
 * Provides Intersection Observer API loader for media.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
 * @see https://developers.google.com/web/updates/2016/04/intersectionobserver
 */

/* global define, module */
(function (root, factory) {

  'use strict';

  // Inspired by https://github.com/addyosmani/memoize.js/blob/master/memoize.js
  if (typeof define === 'function' && define.amd) {
    // AMD. Register as an anonymous module.
    define([root.dBlazy, root.Bio], factory);
  }
  else if (typeof exports === 'object') {
    // Node. Does not work with strict CommonJS, but only CommonJS-like
    // environments that support module.exports, like Node.
    module.exports = factory(root.dBlazy, root.Bio);
  }
  else {
    // Browser globals (root is window).
    root.BioMedia = factory(root.dBlazy, root.Bio);
  }
})(this, function (dBlazy, Bio) {

  'use strict';

  /**
   * Private variables.
   */
  var $ = dBlazy;
  var _b = Bio;
  var _src = 'src';
  var _srcSet = 'srcset';
  var _bgSrc = 'data-src';
  var _dataSrc = 'data-src';
  var _dataSrcset = 'data-srcset';
  var _bgSources = [_src];
  var _imgSources = [_srcSet, _src];

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
    return _b.apply(this, arguments);
  }

  // Inherits Bio prototype.
  var _proto = BioMedia.prototype = Object.create(Bio.prototype);
  _proto.constructor = BioMedia;

  _proto.lazyLoad = (function (_bio) {
    return function (el) {
      var $el = $(el);
      // Image may take time to load after being hit, and it may be intersected
      // several times till marked loaded. Ensures it is hit once regardless
      // of being loaded, or not. No real issue with normal images on the page,
      // until having VIS alike which may spit out new images on AJAX request.
      if ($el.hasAttr('data-bio-hit')) {
        return;
      }

      var me = this;
      var $parent = $(el.parentNode);
      var isImage = $el.equal('img');
      var isBg = $.isUnd(el.src) && $el.hasClass(me.options.bgClass);
      var isPicture = $parent.length && $parent.equal('picture');
      var isVideo = $el.equal('video');

      // PICTURE elements.
      if (isPicture) {
        $el.mapSource(_srcSet, true);

        // Tiny controller image inside picture element won't get preloaded.
        $el.mapAttr(_src, true);
        me.loaded(el, me._ok);
      }
      // VIDEO elements.
      else if (isVideo) {
        $el.mapSource(_src, true);
        el.load();
        me.loaded(el, me._ok);
      }
      else {
        // IMG or DIV/ block elements got preloaded for better UX with loading.
        if (isImage || isBg) {
          me.setImage(el, isBg);
        }
        // IFRAME elements, etc.
        else {
          if ($el.attr(_dataSrc) && $el.hasAttr(_src)) {
            $el.mapAttr(_src, true);
            me.loaded(el, me._ok);
          }
        }
      }

      // Marks it hit/ requested. Not necessarily loaded.
      $el.attr('data-bio-hit', 1);

      return _b.apply(this, arguments);
    };
  })(_proto.lazyLoad);

  _proto.setImage = function (el, isBg) {
    var me = this;
    var $el = $(el);
    var img = new Image();
    var isResimage = $el.hasAttr(_dataSrcset);

    // Applies attributes regardless, will re-observe if any error.
    var applyAttrs = function () {
      if (isBg) {
        me.bg(el);
      }
      else {
        $el.mapAttr(_imgSources, false);
      }
    };

    var load = function (ok) {
      applyAttrs();

      // Image decode fails with Responsive image, assumes ok, no side effects.
      me.loaded(el, ok ? me._ok : me._er);
      if (ok) {
        $el.removeAttr(isBg ? _bgSources : _imgSources, 'data-');
      }
    };

    $.decode(img)
      .then(function () {
        load(true);
      })
      .catch(function () {
        load(isResimage);

        // Allows to re-observe.
        if (!isResimage) {
          $el.attr('data-bio-hit', null);
        }
      })
      .finally(function () {
        // Be sure to throttle, or debounce your method when calling this.
        $el.trigger('bio.finally', {
          options: me.options
        });
      });

    // Preload `img` to have correct event handlers.
    if ('decode' in img) {
      img.decoding = 'async';
    }

    img.src = $el.attr(isBg ? _bgSrc : _dataSrc);
    if (isResimage) {
      img.srcset = $el.attr(_dataSrcset);
    }

  };

  _proto.bg = function (el) {
    var $el = $(el);
    if ($el.hasAttr(_bgSrc)) {
      el.style.backgroundImage = 'url("' + $el.attr(_bgSrc) + '")';
      $el.attr(_src, null);
    }
  };

  return BioMedia;

});
