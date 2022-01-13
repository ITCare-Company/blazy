/**
 * @file
 * Provides blur extension for Drupal.blazy.
 */

(function ($, Drupal) {

  'use strict';

  var _b = Drupal.blazy || {};

  /**
   * Processes blur elements, if any.
   */
  function blur() {
    var me = this;

    // Only needed if `No JavaScript` enabled, else bailout.
    if (!me.options.loader) {
      var els = $.findAll(me.context, me.selector('.b-blur'));
      if (els.length) {
        me.mapAttr(els);
      }
    }
  }

  _b.extend({blur: blur});

}(dBlazy, Drupal));
