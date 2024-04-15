/**
 * @file
 * Provides CSS3 Native Grid treated as Masonry based on Grid Layout.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Grid_Layout
 * The two-dimensional Native Grid does not use JS until treated as a Masonry.
 * If you need GridStack kind, avoid inputting numeric value for Grid.
 * Below is the cheap version of GridStack.
 *
 * @credit: https://css-tricks.com/a-lightweight-masonry-solution/
 */

(function ($, Drupal) {

  'use strict';

  var ID = 'b-nativegrid';
  var ID_ONCE = 'b-masonry';
  var C_IS_MASONRY = 'is-' + ID_ONCE;
  var C_MOUNTED = C_IS_MASONRY + '-mounted';
  var S_ELEMENT = '.' + ID + '.' + C_IS_MASONRY;
  var C_IS_CAPTIONED = 'is-b-captioned';
  var UNLOAD;

  /**
   * Processes a grid object.
   *
   * @param {Object} grid
   *   The grid object.
   */
  function processItem(grid) {
    // Get the post relayout number of columns.
    var ncol = getComputedStyle(grid._el).gridTemplateColumns.split(' ').length;

    // If the number of columns has changed.
    if (grid.ncol !== ncol || grid.mod) {
      // Update number of columns.
      grid.ncol = ncol;

      // Revert to initial positioning, no margin.
      $.each(grid.items, function (c) {
        c.style.removeProperty('margin-top');
      });

      // If we have more than one column.
      if (grid.ncol > 1) {
        $.each(grid.items.slice(ncol), function (c, i) {
          // Bottom edge of item above.
          var prevFin = $.rect(grid.items[i]).bottom;
          // Top edge of current item.
          var currItm = $.rect(c).top;

          c.style.marginTop = (prevFin + grid.gap - currItm) + 'px';
        });
      }

      grid.mod = 0;
    }
  }

  function map(grids) {
    return grids.map(function (grid) {
      var children = $.slice(grid.childNodes);

      return {
        _el: grid,
        gap: parseFloat(getComputedStyle(grid).gridRowGap),
        items: children.filter(function (c) {
          return c.nodeType === 1 && +getComputedStyle(c).gridColumnEnd !== -1;
        }),
        ncol: 0
      };
    });
  }

  /**
   * Processes a grid masonry.
   *
   * @param {HTMLElement} elm
   *   The container HTML element.
   */
  function process(elm) {
    var caption = $.find(elm, '.views-field') && $.find(elm, '.views-field p');

    // @todo move it to PHP, unreliable here.
    // It is here for Views rows not aware of captions, not formatters.
    if (caption) {
      $.addClass(elm, C_IS_CAPTIONED);
    }

    $.addClass(elm, C_MOUNTED);
  }

  /**
   * Initialize the grid elements.
   *
   * @param {HTMLElement} grids
   *   The container HTML elements.
   */
  function init(grids) {
    var onResize = function (entry) {
      grids.find(function (grid) {
        return grid._el === entry.target.parentElement;
      }).mod = 1;
    };

    var o = new ResizeObserver(function (entries) {
      $.each(entries, onResize);
    });

    grids = map(grids);

    function layout() {
      $.each(grids, processItem);
    }

    // Fix for LB or AJAX in general integration.
    if (UNLOAD) {
      setTimeout(function () {
        layout();
        UNLOAD = false;
      }, 300);
    }

    $.on('load.' + ID_ONCE, function () {
      layout();

      $.each(grids, function (grid) {
        $.each(grid.items, function (c) {
          o.observe(c);
        });
      });

      $.on('resize.' + ID_ONCE, layout, false);
    }, false);
  }

  /**
   * Attaches Blazy behavior to HTML element identified by .b-nativegrid.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyNativeGrid = {
    attach: function (context) {

      var grids = $.once(process, ID_ONCE, S_ELEMENT, context);

      if (grids.length && getComputedStyle(grids[0]).gridTemplateRows !== 'masonry') {
        init(grids);
      }

    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        UNLOAD = true;
        $.once.removeSafely(ID_ONCE, S_ELEMENT, context);
      }
    }

  };

}(dBlazy, Drupal));
