/**
 * @file
 * Provides Blazy layout utilities.
 */


(function ($, Drupal, _doc) {

  'use strict';

  var BASE = 'b-layout';
  var ID = BASE + '-form';
  var ID_ONCE = ID;
  var C_MOUNTED = 'is-' + ID_ONCE;
  var S_BASE = '.form-wrapper--' + BASE;
  var S_ELEMENT = S_BASE + ':not(.' + C_MOUNTED + ')';
  var S_ACTIVE_LAYOUT = '.' + BASE + '.is-layout-builder-highlighted';

  /**
   * Processes a blazy layout form.
   *
   * @param {HTMLElement} elm
   *   The container HTML element.
   */
  function process(elm) {
    var colors = $.findAll(elm, 'input[type="color"]');
    var ranges = $.findAll(elm, 'input[type="range"]');

    var updateValue = function (el) {
      if (el.nextElementSibling) {
        el.nextElementSibling.textContent = el.value;
      }
    };

    var updateColor = function (el, region) {
      if ($.contains(el.name, 'background_')) {
        // @todo live preview.
      }
    };

    var updateOpacity = function (el, region) {
      if ($.contains(el.name, 'background_')) {
        // @todo live preview.
      }
    };

    var onChange = function () {
      var el = this;
      var region;
      var rid;
      var layout = $.find(_doc, S_ACTIVE_LAYOUT);
      var formRegion = $.closest(el, '[data-b-region]');

      updateValue(el);

      setTimeout(function () {
        formRegion = $.closest(el, '[data-b-region]');

        if (formRegion) {
          rid = formRegion.dataset.bRegion;
          region = $.find(layout, '[data-region="' + rid + '"]');

          if (region) {
            if ($.contains(el.name, '_opacity')) {
              updateOpacity(el, region);
            }
            else {
              updateColor(el, region);
            }
          }
        }
      });
    };

    var subprocess = function (elms) {
      $.each(elms, function (el) {
        updateValue(el);

        $.on(el, 'change.' + ID, onChange);
      });
    };

    subprocess(colors);
    subprocess(ranges);

    $.addClass(elm, C_MOUNTED);
  }

  /**
   * Attaches Blazy behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyLayoutModal = {
    attach: function (context) {

      $.once(process, ID_ONCE, S_ELEMENT, context);

    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        $.once.removeSafely(ID_ONCE, S_BASE, context);
      }
    }
  };

}(dBlazy, Drupal, this.document));
