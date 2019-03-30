/**
 * @file
 * Provides Intersection Observer API loader for media.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
 * @see https://developers.google.com/web/updates/2016/04/intersectionobserver
 */

/* global window, document, define, module */
(function (root, factory) {

  'use strict';

  // Inspired by https://github.com/addyosmani/memoize.js/blob/master/memoize.js
  if (typeof define === 'function' && define.amd) {
    // AMD. Register as an anonymous module.
    define([window.dBlazy, window.Bio], factory);
  }
  else if (typeof exports === 'object') {
    // Node. Does not work with strict CommonJS, but only CommonJS-like
    // environments that support module.exports, like Node.
    module.exports = factory(window.dBlazy, window.Bio);
  }
  else {
    // Browser globals (root is window).
    root.BioMedia = factory(window.dBlazy, window.Bio);
  }
})(this, function (dBlazy, Bio) {

  'use strict';

  /**
   * Private variables.
   */
  var _db = dBlazy;
  var _bio = Bio;
  var _src = 'src';
  var _srcSet = 'srcset';
  var _bgSrc = 'data-src';
  var _dataSrc = 'data-src';
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
    return _bio.apply(this, arguments);
  }

  // Inherits Bio prototype.
  var _proto = BioMedia.prototype = Object.create(Bio.prototype);
  _proto.constructor = BioMedia;

  _proto.prepare = (function (_bio) {
    return function () {
      var me = this;

      // DIV elements with multi-serving CSS background images.
      if (me.opts.breakpoints) {
        _db.forEach(me.opts.breakpoints, function (object) {
          _bgSources.push(object.src.replace('data-', ''));
          if (object.width <= me.windowWidth) {
            _bgSrc = object.src;
            return false;
          }
        });
      }

      return _bio.call(this);
    };
  })(_proto.prepare);

  _proto.lazyLoad = (function (_bio) {
    return function (el) {
      var me = this;
      var parent = el.parentNode;
      var isImage = me.equal(el, 'img');
      var isBg = typeof el.src === 'undefined' && el.classList.contains(me.opts.bgClass);
      var isPicture = parent && me.equal(parent, 'picture');
      var isVideo = me.equal(el, 'video');

      // PICTURE elements.
      if (isPicture) {
        _db.forEach(parent.getElementsByTagName('source'), function (source) {
          me.setAttr(source, _srcSet);
        });
        // Tiny image inside picture element won't get preloaded.
        me.loaded(el, me._ok);
      }
      // VIDEO elements.
      else if (isVideo) {
        _db.forEach(el.getElementsByTagName('source'), function (source) {
          me.setAttr(source, _src);
        });
        el.load();
        me.loaded(el, me._ok);
      }
      else {
        // IMG or DIV/ block elements.
        if (isImage || isBg) {
          me.setImage(el, isBg);
        }
        // IFRAME elements, etc.
        else {
          if (el.getAttribute(_dataSrc) && el.hasAttribute(_src)) {
            el.src = el.getAttribute(_dataSrc);
            me.loaded(el, me._ok);
          }
        }
      }

      return _bio.apply(this, arguments);
    };
  })(_proto.lazyLoad);

  _proto.loaded = (function (_bio) {
    return function (el, status) {

      this.removeAttrs(el, _imgSources);

      return _bio.apply(this, arguments);
    };
  })(_proto.loaded);

  _proto.promise = function (el, isBg) {
    var me = this;

    return new Promise(function (resolve, reject) {
      var img = new Image();

      // Preload `img` to have correct event handlers.
      me.setAttrs(el, _imgSources, img, isBg ? _bgSrc : _dataSrc);

      // Handle onload event.
      img.onload = function () {
        if (isBg) {
          me.setBg(el);
        }
        else {
          me.setAttrs(el, _imgSources);
        }
        resolve(me._ok);
      };

      // Handle onerror event.
      img.onerror = function () {
        reject(me._er);
      };
    });
  };

  _proto.setImage = function (el, isBg) {
    var me = this;

    return me.promise(el, isBg)
      .then(function (status) {
        me.loaded(el, status);
      })
      .catch(function (status) {
        me.loaded(el, status);
      })
      .finally(function () {
        me.removeAttrs(el, _imgSources);
      });
  };

  _proto.setBg = function (el) {
    var me = this;

    if (el.hasAttribute(_bgSrc)) {
      el.style.backgroundImage = 'url("' + el.getAttribute(_bgSrc) + '")';
      me.removeAttrs(el, _bgSources);
      el.removeAttribute(_src);
    }
  };

  return BioMedia;

});
