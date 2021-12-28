/**
 * @file
 * Provides CSS3 Native Grid treated as Masonry based on Grid Layout.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Grid_Layout
 * The two-dimensional Native Grid does not use JS until treated as a Masonry.
 * If you need GridStack kind, avoid inputting numeric value for Grid.
 * Below is the cheap version of GridStack.
 */

(function (Drupal, once, _db, _win) {

  'use strict';

  var _id = 'blazy-nativegrid';
  var _element = '.block-nativegrid.is-b-masonry';

  Drupal.blazy = Drupal.blazy || {};

  /**
   * Blazy nativeGrid public methods.
   *
   * @namespace
   */
  Drupal.blazy.nativeGrid = {
    gap: 15,
    height: 15,
    rows: 10
  };

  /**
   * Applies the correct span to each grid item.
   *
   * @param {HTMLElement|Event} el
   *   The item HTML element, or event object on blazy.done.
   */
  function doNativeGridItem(el) {
    var me = Drupal.blazy.nativeGrid;
    var box = 'target' in el ? _db.closest(el.target, '.grid') : el;

    if (box === null) {
      return;
    }

    var cn = _db.find(box, '.grid__content');

    if (cn !== null) {
      if (me.gap === 0) {
        me.gap = 0.0001;
      }
      _win.setTimeout(function () {
        var rect = cn.getBoundingClientRect();
        var span = Math.ceil((rect.height + me.gap) / (me.height + me.gap));

        // Sets the grid row span based on content and gap height.
        box.style.gridRowEnd = 'span ' + span;
        box.classList.add('is-b-grid');
      }, 600);
    }

    if (el.target && (el.type && el.type === 'blazy.done')) {
      _db.unbindEvent(el.target, 'blazy.done', doNativeGridItem, false);
    }
  }

  /**
   * Applies grid row end to each grid item.
   *
   * @param {HTMLElement} el
   *   The container HTML element.
   */
  function doNativeGrid(el) {
    var me = Drupal.blazy.nativeGrid;
    var style = _win.getComputedStyle(el);
    var gap = style.getPropertyValue('grid-row-gap');
    var rows = style.getPropertyValue('grid-auto-rows');

    if (gap) {
      me.gap = parseInt(gap, 10);
    }
    if (rows) {
      me.height = parseInt(rows, 10);
    }

    // The is-b-grid is flag to not re-do with VIS, views infinite scroll/ IO.
    var items = _db.findAll(el, '.grid:not(.is-b-grid)');
    if (items.length) {
      _db.forEach(items, doNativeGridItem, el);
    }

    var resizeObserver = Drupal.blazy.isRo() ? new ResizeObserver(function (entries) {
      _db.forEach(entries, doNativeGridItem);
    }) : false;

    var blazies = el.getElementsByClassName('b-lazy');
    if (blazies.length) {
      _db.forEach(blazies, function (item) {
        _db.bindEvent(item, 'blazy.done', doNativeGridItem, false);
        if (resizeObserver) {
          resizeObserver.observe(item);
        }
      });
    }
  }

  /**
   * Attaches Blazy behavior to HTML element identified by .block-nativegrid.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyNativeGrid = {
    attach: function (context) {

      context = _db.context(context);

      once(_id, _element, context).forEach(doNativeGrid);
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        if (once.find(_id, context).length) {
          once.remove(_id, _element, context);
        }
      }
    }
  };

}(Drupal, once, dBlazy, this));
