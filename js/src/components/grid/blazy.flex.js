/**
 * @file
 * Provides CSS3 flex based on Flexbox layout.
 *
 * Credit: https://fjolt.com/article/css-grthis loader id-masonry
 *
 * @requires aspect ratio fluid in the least to layout correctly.
 * @todo deprecated this is worse than NativeGrid Masonry. We can't compete
 * against the fully tested Outlayer or GridStack library.
 */

(function ($, Drupal, _doc) {

  'use strict';

  var ID = 'b-flex';
  var ID_ONCE = ID;
  var C_MOUNTED = 'is-' + ID_ONCE;
  var C_DONE = C_MOUNTED + '-done';
  var C_RESIZED = C_MOUNTED + '-resized';
  var S_ELEMENT = '.' + ID + ':not(.' + C_MOUNTED + ')';
  var S_GRID = '.grid';
  var V_BIO = 'bio';
  var E_DONE = V_BIO + ':done';
  var E_RESIZED = V_BIO + ':resizing';
  var E_TRANSTIONEND = 'transitionend.' + ID;
  var V_MAX = 0;
  var V_OPTS = {
    $el: null
  };

  /**
   * Applies height adjustments to each item.
   *
   * @param {HTMLElement} elm
   *   The container HTML element.
   */
  function process(elm) {
    var heights = {};
    var items;
    var html = $.find(elm, '.b-html');

    function toGrid(grids) {
      var box = $.find(elm, S_GRID);
      var parentWidth = $.rect(elm).width;
      var boxWidth = $.rect(box).width;
      var boxStyle = $.computeStyle(box);
      var itemWidth = boxWidth + (parseFloat(boxStyle.marginLeft) + parseFloat(boxStyle.marginRight));
      var columnWidth = Math.round((1 / (itemWidth / parentWidth)));

      var layout = function (grid, id) {
        var target = grid.target;
        grid = target ? $.closest(target, S_GRID) : grid;
        id = $.isUnd(id) ? grids.indexOf(grid) : id;

        var cn = $.find(grid, S_GRID + '__content');
        if (!$.isElm(cn)) {
          return;
        }

        var cr = $.rect(cn);
        var ch = cr.height;

        if (ch < 60) {
          cr = $.rect(grid);
          ch = cr.height;
        }

        if (ch < 60) {
          return;
        }

        var curColumn = id % columnWidth;
        var style = $.computeStyle(grid);

        if ($.isUnd(heights[curColumn])) {
          heights[curColumn] = 0;
        }

        // grid.style.minHeight = ch + 'px';
        heights[curColumn] += ch + parseFloat(style.marginBottom);

        // If the item has an item above it, then move it to fill the gap.
        if (id - columnWidth >= 0) {
          var nh = id - columnWidth + 1;
          var itemAbove = $.find(elm, S_GRID + ':nth-of-type(' + nh + ')');
          if ($.isElm(itemAbove)) {
            var prevBottom = $.rect(itemAbove).bottom;
            var currentTop = cr.top - parseFloat(style.marginBottom);

            // grid.style.top = '-' + (currentTop - prevBottom) + 'px';
            grid.style.transform = 'translateY(-' + parseInt(currentTop - prevBottom, 0) + 'px)';
          }
        }
      };

      var processItem = function (item, id) {
        layout(item, id);
      };

      // Process on page load.
      $.each(grids, processItem);

      var checkHeight = function () {
        var values = Object.values(heights);
        var max = Math.max.apply(null, values);

        if (max < 0) {
          max = V_MAX;
        }

        // Min-height causes unwanted white-space. Height is too risky with
        // dynamic contents without aspect ratio, but normally fit best.
        elm.style.height = max + 'px';

        V_MAX = max;
      };

      checkHeight();

      // @todo this breaks initial bricks.
      // var checkResize = function () {
      // Process on resize.
      // me.checkResize(items, processItem, elm);
      // };
    }

    function initNow(e) {
      var isDone = e && e.type === E_DONE;
      var resized = e && e.type === E_RESIZED;
      items = $.findAll(elm, S_GRID);

      function start() {
        items = $.findAll(elm, S_GRID);
        toGrid(items);

        $.removeClass(elm, C_RESIZED);

        setTimeout(function () {
          $.addClass(elm, C_DONE);
        }, resized ? 600 : 0);
      }

      if (resized) {
        $.removeClass(elm, C_DONE);
        $.addClass(elm, C_RESIZED);

        var ended = function () {
          heights = {};

          $.each(items, function (el) {
            el.style.transform = '';
            // el.style.minHeight = '';
          });
          start();

          $.off(elm, E_TRANSTIONEND, ended);
        };

        $.on(elm, E_TRANSTIONEND, ended);
      }
      else {
        start();
      }

      if (isDone) {
        $.off(E_DONE + '.' + ID, initNow);
      }
    }

    if ($.isElm(html)) {
      $.on(E_DONE + '.' + ID, initNow);
    }
    else {
      setTimeout(initNow, 301);
    }

    $.on(E_RESIZED + '.' + ID, $.debounce(initNow, 601));

    $.addClass(elm, C_MOUNTED);
    V_OPTS.$el = elm;
  }

  /**
   * Attaches Blazy behavior to HTML element identified by .b-flex.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyFlex = {
    attach: function (context) {
      $.once(process, ID_ONCE, S_ELEMENT, context);
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        $.once.removeSafely(ID_ONCE, S_ELEMENT, context);
      }
    }
  };

}(dBlazy, Drupal, this.document));
