/**
 * @file
 * Provides CSS3 flex based on Flexbox layout.
 *
 * Credit: https://fjolt.com/article/css-grid-masonry
 */

(function ($, Drupal, _win) {

  'use strict';

  var _element = '.block-flex';
  var _loading = 'is-b-loading';

  /**
   * Applies height adjustments to each item.
   *
   * @param {HTMLElement} elm
   *   The container HTML element.
   */
  function process(elm) {
    var _box = '.grid';
    var heights = {};
    var box = $.find(elm, _box);

    if (!$.isElm(box)) {
      return;
    }

    var parentWith = elm.getBoundingClientRect().width;
    var boxWith = box.getBoundingClientRect().width;
    var style = _win.getComputedStyle(box);
    var itemWith = boxWith + parseFloat(style.marginLeft) + parseFloat(style.marginRight);
    var columnWidth = Math.round((1 / (itemWith / parentWith)));
    var items = $.find(elm, _box, false);

    function processItem(item, id) {
      var cn = $.find(item, _box + '__content');
      var cr = cn.getBoundingClientRect();
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
        if (itemAbove) {
          var prevBottom = itemAbove.getBoundingClientRect().bottom;
          var currentTop = cr.top - parseFloat(style.marginBottom);

          item.style.top = '-' + (currentTop - prevBottom) + 'px';
        }
      }
    }

    function init() {
      $.each(items, processItem);

      var max = Math.max.apply(null, Object.values(heights));
      elm.style.height = max + 'px';
    }

    $.bindEvent(_win, 'load resize', Drupal.debounce(init, 200, true));

    $.addClass(elm, _loading);
    _win.setTimeout(function () {
      $.removeClass(elm, _loading);
    }, 600);
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
