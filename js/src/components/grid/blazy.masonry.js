/**
 * @file
 * Provides CSS3 flex based on Flexbox layout.
 *
 * @credit: https://css-tricks.com/a-lightweight-masonry-solution/
 *
 * @requires aspect ratio fluid in the least to layout correctly.
 * @todo deprecated this is worse than NativeGrid Masonry. We can't compete
 * against the fully tested Outlayer or GridStack library.
 */

(function ($, Drupal, _win, _doc, _html) {

  'use strict';

  var C_GRID = 'grid';
  var S_GRID = '.' + C_GRID;
  var E_FLEX = 'flex';
  var E_NATIVEGRID = 'nativegrid';

  $.masonry = {

    options: {},

    resized: false,

    columnCount: function (elm) {
      var me = this;
      var engine = me.options.engine;

      if (engine === E_NATIVEGRID) {
        return getComputedStyle(elm).gridTemplateColumns.split(' ').length;
      }
      else if (engine === E_FLEX) {
        var box = $.find(elm, S_GRID);
        var parentWidth = $.rect(elm).width;
        var boxWidth = $.rect(box).width;
        var boxStyle = $.computeStyle(box);
        var margin = parseFloat(boxStyle.marginLeft) + parseFloat(boxStyle.marginRight);
        var itemWidth = boxWidth + margin;
        return Math.round((1 / (itemWidth / parentWidth)));
      }

      return 1;
    },

    toObject: function (elms, e) {
      var me = this;
      var opts = me.options;
      var engine = me.options.engine;
      var gap;

      return elms.map(function (el) {
        var children = $.slice(el.childNodes);

        if (engine === E_NATIVEGRID) {
          gap = parseFloat(getComputedStyle(el).gridRowGap);
        }
        else if (engine === E_FLEX) {
          gap = parseFloat($.computeStyle(_html, opts.gap, true));
        }

        return {
          _el: el,
          event: e || {},
          gap: gap,
          items: children.filter(function (c) {
            return $.hasClass(c, C_GRID) && !$.hasClass(c, 'region--bg');
          }),
          ncol: 0,
          count: children.length
        };
      });
    },

    /**
     * Processes a grid object.
     *
     * @param {Object} grid
     *   The grid object.
     */
    subprocess: function (grid) {
      var me = this;
      var opts = me.options;

      // Get the post relayout number of columns.
      var ncol = me.columnCount(grid._el);

      // If the number of columns has changed.
      if (grid.ncol !== ncol || grid.mod) {
        // Update number of columns.
        grid.ncol = ncol;

        // Revert to initial positioning, no margin.
        var cleanout = function () {
          $.each(grid.items, function (c) {
            c.style.removeProperty('margin-top');
          });
        };

        cleanout();

        // If we have more than one column.
        if (grid.ncol > 1) {
          $.removeClass(grid._el, opts.cDisabled);
          $.each(grid.items.slice(ncol), function (c, i) {
            // Bottom edge of item above.
            var prevFin = $.rect(grid.items[i]).bottom;
            // Top edge of current item.
            var currItm = $.rect(c).top;

            c.style.marginTop = (prevFin + grid.gap - currItm) + 'px';
          });
        }
        else {
          $.addClass(grid._el, opts.cDisabled);
          cleanout();
        }

        grid.mod = false;
      }
    },

    /**
     * Initialize the grid elements.
     *
     * @param {HTMLElement} elms
     *   The container HTML elements.
     * @param {Object} opts
     *   The options.
     */
    init: function (elms, opts) {
      var me = this;
      var nick = opts.nick;

      opts.id = 'b-' + nick;
      opts.sBase = '.' + opts.id;
      opts.cDisabled = 'is-' + opts.id + '-disabled';

      me.options = opts;

      var roots = me.toObject(elms);

      var onResize = function (entry) {
        roots.find(function (grid) {
          if (grid._el === entry.target.parentElement) {
            if (me.resized) {
              me.subprocess(grid);
            }
            return true;
          }
          return false;
        }).mod = true;
      };

      var o = new ResizeObserver(function (entries) {
        $.each(entries, onResize);

        if (!me.resized) {
          me.resized = true;
        }
      });

      function observe() {
        $.each(roots, function (grid) {
          $.each(grid.items, function (c) {
            o.observe(c);
          });
        });
      }

      function layout(e) {
        if ($.isUnd(e)) {
          $.each(roots, me.subprocess, me);
        }
        else {
          var elms = $.toElms(opts.sBase);

          if (elms.length) {
            roots = me.toObject(elms, e);

            roots.find(function (grid) {
              return $.hasClass(grid._el, opts.id);
            }).mod = true;

            $.each(roots, me.subprocess, me);
          }
        }
      }

      var watch = function (e) {
        setTimeout(function () {

          observe();
          layout(e);

          // AJAX package may be late to populate DOM.
        }, e === me.options.unload ? 700 : 1);
      };

      // Fix for LB or AJAX in general integration.
      // @todo move it to an AJAX event when Drupal has one by 2048.
      if (me.options.unload) {
        watch(me.options.unload);
        me.options.unload = false;
      }

      $.on('load.' + opts.id, function () {
        watch();
      }, false);
    }

  };

}(dBlazy, Drupal, this, this.document, this.document.documentElement));
