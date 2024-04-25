/**
 * @file
 * Provides Blazy layout utilities.
 */


(function ($, Drupal) {

  'use strict';

  var ID = 'b-layout-form';
  var ID_ONCE = ID;
  var C_MOUNTED = 'is-' + ID_ONCE;
  var S_BASE = '.form-wrapper--b-layout';
  var S_ELEMENT = S_BASE + ':not(.' + C_MOUNTED + ')';

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

    var subprocess = function (elms) {
      $.each(elms, function (el) {
        updateValue(el);

        $.on(el, 'change.' + ID, function () {
          updateValue(this);
        });
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
  Drupal.behaviors.blazyLayoutAdmin = {
    attach: function (context) {

      $.once(process, ID_ONCE, S_ELEMENT, context);

    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        $.once.removeSafely(ID_ONCE, S_BASE, context);
      }
    }
  };

}(dBlazy, Drupal));
