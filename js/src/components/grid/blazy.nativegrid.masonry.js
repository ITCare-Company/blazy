/**
 * @file
 * Provides CSS3 Native Grid treated as Masonry based on Grid Layout.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Grid_Layout
 * The two-dimensional Native Grid does not use JS until treated as a Masonry.
 * If you need GridStack kind, avoid inputting numeric value for Grid.
 * Below is the cheap version of GridStack.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.blazy = Drupal.blazy || {};

  var ID = 'b-nativegrid';
  var ID_ONCE = 'b-masonry';
  var C_IS_MASONRY = 'is-' + ID_ONCE;
  var C_MOUNTED = C_IS_MASONRY + '-mounted';
  var C_IS_UNLOAD = 'is-b-unload';
  var S_ELEMENT = '.' + ID + '.' + C_IS_MASONRY + ':not(.' + C_MOUNTED + ')';
  var HEIGHTS = [];
  var UNLOAD = false;
  var OPTS = {
    $el: null,
    gap: 15,
    height: 15,
    rows: 10
  };



  /**
   * Applies the correct span to each grid item.
   *
   * @param {HTMLElement|Event} el
   *   The item HTML element, or event object on blazy.done.
   * @param {int} i
   *   The element index.
   * @param {bool} isResized
   *   If the resize event is triggered.
   */
  function processItem(el, i, isResized) {
    var target = el.target;
    var box = 'target' in el ? $.closest(target, '.grid') : el;
    var cn;

    if (!$.isElm(box)) {
      return;
    }

    cn = $.find(box, '.grid__content');

    if (OPTS.gap === 0) {
      OPTS.gap = 0.0001;
    }

    // Once setup, we rely on CSS to make it responsive.
    var layout = function () {
      var height = $.outerHeight(cn, true);
      var rect = $.rect(cn);
      var span;

      HEIGHTS.push(height);
      span = Math.ceil((rect.height + OPTS.gap) / (OPTS.height + OPTS.gap));

      // Sets the grid row span based on content and gap height.
      box.style.gridRowEnd = 'span ' + span;

      $.addClass(box, 'is-b-grid');
      setTimeout(function () {
        cn.style.minHeight = '';
        $.addClass(box, 'is-b-layout');
      }, UNLOAD ? 600 : 200);
    };

    if (isResized || UNLOAD) {
      setTimeout(layout, UNLOAD ? 300 : 200);
    }
    else {
      layout();
    }
  }

  /**
   * Applies grid row end to each grid item.
   *
   * @param {HTMLElement} elm
   *   The container HTML element.
   */
  function process(elm) {
    var selector = '.grid:not(.is-b-grid)';
    // The is-b-grid is flag to not re-do with VIS, views infinite scroll/ IO.
    var items = $.findAll(elm, selector);

    var init = function () {
      var style = $.computeStyle(elm);
      var gap = style.getPropertyValue('grid-row-gap');
      var rows = style.getPropertyValue('grid-auto-rows');

      if (gap) {
        OPTS.gap = $.toInt(gap, 0);
      }
      if (rows) {
        OPTS.height = $.toInt(rows, 1);
      }

      if (items.length) {
        // @todo recheck and remove.
        if (UNLOAD) {
          $.each(items, function (item, i) {
            var cn = $.find(item, '.grid__content');
            if (cn && HEIGHTS[i]) {
              cn.style.minHeight = HEIGHTS[i] + 'px';
            }
          });
        }

        // Process on page load.
        $.each(items, processItem);

        // Process on resize.
        if (!UNLOAD) {
          Drupal.blazy.checkResize(items, processItem, elm, processItem);
        }

      }
    };

    setTimeout(init, UNLOAD ? 110 : 0);
    OPTS.$el = elm;

    if (UNLOAD) {
      $.addClass(elm, C_IS_UNLOAD);
    }

    UNLOAD = false;
    $.addClass(elm, C_MOUNTED);
  }

  /**
   * Attaches Blazy behavior to HTML element identified by .b-nativegrid.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyNativeGrid = {
    attach: function (context) {
      $.once(process, ID_ONCE, S_ELEMENT, context);
    },
    detach: function (context, setting, trigger) {
      UNLOAD = trigger === 'unload';
      if (UNLOAD) {
        $.once.removeSafely(ID_ONCE, S_ELEMENT, context);
      }
    }

  };

}(dBlazy, Drupal));
