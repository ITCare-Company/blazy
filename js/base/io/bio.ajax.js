/**
 * @file
 * Provides Intersection Observer API AJAX helper.
 *
 * Blazy IO works fine with AJAX, until using VIS, or alike. Adds a helper.
 *
 * @todo recheck old bLazy, this appears no-longer needed with the latest IO as
 * Drupal module thanks to core/once. Not sure about VIS and VLM. But fine
 * since IO.module 1.3.
 * @todo remove, if old bLazy proves working fine with non-IO -- VIS, VLM alike.
 */

(function (Drupal) {

  'use strict';

  var _blazy = Drupal.blazy || {};
  var _ajax = Drupal.Ajax || {};
  var _proto = _ajax.prototype;

  // Overrides Drupal.Ajax.prototype.success to re-observe new AJAX contents.
  _proto.success = (function (_ajax) {
    return function (response, status) {
      // IO.module seems fine with core/once, only concerns if IO is disabled.
      if (_blazy && (_blazy.init && _blazy.isBlazy())) {
        // ::load() means forcing them to load at once, great for small
        // amount of items, bad for large amount.
        // ::revalidate() means re-observe newly loaded AJAX contents without
        // forcing all images to load at once, great for large, bad for small.
        // Unfortunately revalidate() not always work, likely layout reflow.
        _blazy.load();
      }

      return _ajax.apply(this, arguments);
    };
  })(_proto.success);

})(Drupal);
