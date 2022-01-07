/**
 * @file
 * Provides CSS3 flex based on Flexbox layout.
 *
 * Credit: https://fjolt.com/article/css-grid-masonry
 */

(function ($, Drupal, _win) {

  'use strict';

  var _masonry = 'is-b-flex';
  var _mounted = _masonry + '--on';
  var _element = '.block-flex:not(.' + _mounted + ')';
  var _loading = 'is-b-loading';

  Drupal.blazy = Drupal.blazy || {};

  /**
   * Applies height adjustments to each item.
   *
   * @param {HTMLElement} elm
   *   The container HTML element.
   */
  function process(elm) {
    var me = Drupal.blazy;
    var _box = '.grid';
    var heights = {};
    var box = $.find(elm, _box);

    if (!$.isElm(box)) {
      return;
    }

    var items = $.findAll(elm, _box);
    var parentWith = rect(elm).width;
    var boxWith = rect(box).width;
    var style = _win.getComputedStyle(box);
    var itemWith = boxWith + parseFloat(style.marginLeft) + parseFloat(style.marginRight);
    var columnWidth = Math.round((1 / (itemWith / parentWith)));

    function processItem(item, id) {
      var target = item.target;
      item = target ? $.closest(target, _box) : item;
      id = $.isUnd(id) ? items.indexOf(item) : id;

      var cn = $.find(item, _box + '__content');
      if (!$.isElm(cn)) {
        return;
      }

      if (item.bflex) {
        return;
      }

      var cr = rect(cn);
      var ch = cr.height;
      var curColumn = id % columnWidth;
      var style = _win.getComputedStyle(item);

      if ($.isUnd(heights[curColumn])) {
        heights[curColumn] = 0;
      }

      item.style.height = ch + 'px';
      heights[curColumn] += ch + parseFloat(style.marginBottom);

      // If the item has an item above it, then move it to fill the gap.
      if (id - columnWidth >= 0) {
        var nh = id - columnWidth + 1;
        var itemAbove = $.find(elm, _box + ':nth-of-type(' + nh + ')');
        if ($.isElm(itemAbove)) {
          var prevBottom = rect(itemAbove).bottom;
          var currentTop = cr.top - parseFloat(style.marginBottom);

          item.style.top = '-' + (currentTop - prevBottom) + 'px';
        }
      }
    }

    function checkHeight() {
      var max = Math.max.apply(null, Object.values(heights));
      elm.style.height = max + 'px';
    }

    function init() {
      // Process on page load.
      $.each(items, processItem);

      checkHeight();
    }

    /* eslint-disable no-unused-vars */
    // @todo this breaks initial bricks.
    var checkResize = function () {
      // Process on resize.
      var cb = function (entries) {
        $.each(entries, processItem);
      };

      me.checkResize(items, cb, elm);
    };
    /* eslint-disable no-unused-vars */

    init();

    $.addClass(elm, _loading);
    _win.setTimeout(function () {
      $.removeClass(elm, _loading);
    }, 600);

    $.addClass(elm, _mounted);
  }

  function rect(el) {
    return el.getBoundingClientRect();
  }

  /**
   * Attaches Blazy behavior to HTML element identified by .block-flex.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyFlex = {
    attach: function (context) {

      context = $.context(context);

      $.once(process, _element, context);
    }
  };

}(dBlazy, Drupal, this));
