/**
 * @file
 * Provides base methods to bridge drupal-related codes with generic ones.
 */

(function ($, Drupal) {

  'use strict';

  function _debounce(cb, arg, scope) {
    var _cb = function () {
      cb.call(scope, arg);
    };
    Drupal.debounce(_cb, 201, true);
  }

  $.debounce = _debounce;

  $.isBg = function (el) {
    return $.hasClass(el, 'b-bg');
  };

})(dBlazy, Drupal);
