/**
 * @file
 * Provides base methods used by drupal-related codes.
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

})(dBlazy, Drupal);
