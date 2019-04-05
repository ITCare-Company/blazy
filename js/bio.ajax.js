/**
 * @file
 * Provides Intersection Observer API AJAX helper.
 *
 * Blazy IO works fine with AJAX, until using VIS, or alike. Adds a helper.
 */

(function (Drupal) {

  'use strict';

  var _blazy = Drupal.blazy || {};
  var _ajax;
  var _proto
  var _revTimer;

  if (_blazy.isIo()) {
    _ajax = Drupal.Ajax;
    _proto = _ajax.prototype;

    // Overrides Drupal.Ajax.prototype.success to re-observe new AJAX contents.
    _proto.success = (function (_ajax) {
      return function (response, status) {
        var me = _blazy.init;

        window.clearTimeout(_revTimer);
        // DOM ready fix. Be sure Views "Use field template" is disabled.
        _revTimer = window.setTimeout(function () {
          var elms = document.querySelectorAll(me.options.selector);
          me.load(elms);
        }, 100);

        return _ajax.apply(this, arguments);
      };
    })(_proto.success);

  }

})(Drupal);
