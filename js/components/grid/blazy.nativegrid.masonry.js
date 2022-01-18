/**
 * @file
 * Provides CSS3 Native Grid treated as Masonry based on Grid Layout.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Grid_Layout
 * The two-dimensional Native Grid does not use JS until treated as a Masonry.
 * If you need GridStack kind, avoid inputting numeric value for Grid.
 * Below is the cheap version of GridStack.
 */

(function ($, Drupal, _win) {

  'use strict';

  var _id = 'block-nativegrid';
  var _masonry = 'is-b-masonry';
  var _mounted = _masonry + '--on';
  var _element = '.' + _id + '.' + _masonry + ':not(.' + _mounted + ')';

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
  function processItem(el) {
    var me = Drupal.blazy.nativeGrid;
    var target = el.target;
    var box = 'target' in el ? $.closest(target, '.grid') : el;

    if (!$.isElm(box)) {
      return;
    }

    var cn = $.find(box, '.grid__content');

    if ($.isElm(cn)) {
      if (me.gap === 0) {
        me.gap = 0.0001;
      }

      _win.setTimeout(function () {
        var rect = cn.getBoundingClientRect();
        var span = Math.ceil((rect.height + me.gap) / (me.height + me.gap));

        // Sets the grid row span based on content and gap height.
        box.style.gridRowEnd = 'span ' + span;
        $.addClass(box, 'is-b-grid');
      }, 600);
    }
  }

  /**
   * Applies grid row end to each grid item.
   *
   * @param {HTMLElement} elm
   *   The container HTML element.
   */
  function process(elm) {
    var me = Drupal.blazy.nativeGrid;
    var selector = '.grid:not(.is-b-grid)';

    var init = function () {
      var style = _win.getComputedStyle(elm);
      var gap = style.getPropertyValue('grid-row-gap');
      var rows = style.getPropertyValue('grid-auto-rows');

      if (gap) {
        me.gap = parseInt(gap, 10);
      }
      if (rows) {
        me.height = parseInt(rows, 10);
      }

      // The is-b-grid is flag to not re-do with VIS, views infinite scroll/ IO.
      var items = $.findAll(elm, selector);

      if (items.length) {
        // Process on page load.
        $.each(items, processItem);

        // Process on resize.
        Drupal.blazy.checkResize(items, processItem, elm, processItem);
      }
    };

    init();

    $.addClass(elm, _mounted);
  }

  /**
   * Attaches Blazy behavior to HTML element identified by .block-nativegrid.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyNativeGrid = {
    attach: function (context) {

      context = $.context(context);

      $.once(process, _element, context);
    }
  };

}(dBlazy, Drupal, this));
