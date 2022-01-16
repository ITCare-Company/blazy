/**
 * @file
 * Provides ResizeObserver with fallback extension for Drupal.blazy.
 *
 * @todo refine and merge with blazy.compat if needed.
 */

(function ($, Drupal, _win) {

  'use strict';

  var _b = Drupal.blazy || {};
  var _id = 'blazy';
  var _data = 'data';
  var _dataDimensions = _data + '-dimensions';
  var _dataRatio = _data + '-ratio';
  var _media = 'media';
  var _picture = 'picture';
  var _elMedia = '.' + _media;
  var _elRatio = _elMedia + '--ratio';
  var _winData = {};

  /**
   * Updates the dynamic multi-breakpoint aspect ratio: bg, picture or image.
   *
   * Even Native needs help since browsers do not auto-update dynamic ratio.
   *
   * This only applies to Responsive images with aspect ratio fluid.
   * Static ratio (media--ratio--169, etc.) is ignored and uses CSS instead.
   *
   * @param {Element} cn
   *   The .media--ratio[--fluid] container HTML element.
   */
  function updateRatio(cn) {
    cn = cn.target || cn;
    if (!$.isElm(cn)) {
      return;
    }

    var me = this;
    // Blazy container (via formatter or Views style) is not always there.
    var root = $.closest(cn, '.' + _id);
    var dimensions = $.parse($.attr(cn, _dataDimensions));
    var isResized = me.resizeTick > 1;

    // Bail out if a static/ non-fluid aspect ratio.
    if (!dimensions) {
      fallbackRatio(cn);
      return;
    }

    // For picture, this is more a dummy space till the image is downloaded.
    var isPicture = $.isElm($.find(cn, _picture)) && isResized;
    var data = $.extend(_winData, {
      up: isPicture
    });
    var pad = $.activeWidth(dimensions, data);

    // Provides marker for grouping between multiple instances.
    cn.dblazy = $.isElm(root) && root.dblazy;
    if (!$.isUnd(pad)) {
      cn.style.paddingBottom = pad + '%';
    }

    // Update multi-breakpoint CSS background.
    // @todo move it out of ratio. ATM, requires ratio to update multi-BG.
    if (isResized) {
      me.update(cn, false, _winData);
    }

    // @todo refactor or remove into IO.
    // Fix for picture or bg element with resizing.
    // if (isResized && (isPicture || $.hasAttr(cn, _dataBg))) {
    // me.onIntersecting((isPicture ? $.find(cn, 'img') : cn), cn);
    // }
  }

  // Only rewrites if the style is indeed stripped out, and not set.
  // View rewrite result stripped out style attribute required by fluid ratio.
  function fallbackRatio(cn) {
    var value = $.attr(cn, _dataRatio);

    if (!$.hasAttr(cn, 'style') && value) {
      cn.style.paddingBottom = value + '%';
    }
  }

  /**
   * Processes resized elements, and update aspect ratio, if any.
   *
   * @return {Object}
   *   Returns public methods.
   */
  function ro() {
    var me = this;
    var doc = me.context;
    var els = $.findAll(doc, _elRatio);
    var loop = function (entries) {
      _winData = me.winData();

      $.each(entries, updateRatio.bind(me));
      return false;
    };

    // Update multi-breakpoint fluid aspect ratio, if any.
    if (els.length) {
      me.checkResize(els, loop, doc);
    }

    return {
      unload: function () {
        me.unresize();
      }
    };

  }

  _b.extend({
    ro: ro
  });

}(dBlazy, Drupal, this));
