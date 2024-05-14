/**
 * @file
 * Provides once compat for D9+.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 */

(function ($, Drupal, _win) {

  'use strict';

  // See https://www.drupal.org/project/drupal/issues/3254840
  var coreOnce = Drupal.once || _win.once;

  /**
   * A wrapper for core/once with some BC.
   *
   * @param {Function|string} cb
   *   The executed function, or string for regular core/once.
   * @param {string} id
   *   The id of the once call.
   * @param {NodeList|Array.<Element>|Element|string} selector
   *   A NodeList, array of elements, single Element, or a string.
   * @param {Document|Element|null} ctx
   *   An element to use as context for querySelectorAll, or empty.
   *
   * @return {Array.<Element>}
   *   An array of elements to process, or empty for old behavior.
   */
  function onceCompat(cb, id, selector, ctx) {
    var els = [];

    // If cb is a string, allow empty selector/ context for document.
    // Assumes once(id, selector, context), by shifting one argument.
    // This is the common implementation of core/once, but hardly used by Blazy.
    if ($.isStr(cb) && $.isUnd(ctx)) {
      return initOnce(cb, id, selector);
    }

    // Original once for BC.
    if ($.isUnd(selector)) {
      _once(cb);
    }
    // If extra arguments are provided, assumes regular loop over elements.
    else {
      els = initOnce(id, selector, ctx);
      if (els.length) {
        // Already avoids loop for a single item.
        $.each(els, cb);
      }
    }

    return els;
  }

  /**
   * Executes the function once.
   *
   * @private
   *
   * @author Daniel Lamb <dlamb.open.source@gmail.com>
   * @link https://github.com/daniellmb/once.js
   *
   * @param {Function} cb
   *   The executed function.
   *
   * @return {Object}
   *   The function result.
   */
  function _once(cb) {
    var result;
    var ran = false;
    return function proxy() {
      if (ran) {
        return result;
      }
      ran = true;
      result = cb.apply(this, arguments);
      // For garbage collection.
      cb = null;
      return result;
    };
  }

  function _filter(selector, elements, apply) {
    return elements.filter(function (el) {
      var selected = $.is(el, selector);
      if (selected && apply) {
        apply(el);
      }
      return selected;
    });
  }

  // @todo BigPipe compat to avoid legacy approach with `processed` classes.
  // See:
  // - https://www.drupal.org/project/drupal/issues/1461322.
  // - https://www.drupal.org/project/slick/issues/3340509.
  // - https://www.drupal.org/project/slick/issues/3211873.
  function initOnce(id, selector, ctx) {
    var root = $.context(ctx, selector);
    return coreOnce(id, selector, root);
  }

  $.once = $.extend(onceCompat, coreOnce);
  $.filter = _filter;

  // @todo implement clear, maybe by internal processed class.
  // Only relevant for BigPipe compat, though.
  $.once.removeSafely = function (id, selector, ctx, clear) {
    var me = this;
    var root = $.context(ctx, selector);

    if (me.find(id, root).length) {
      return me.remove(id, selector, root);
    }
    return [];
  };

})(dBlazy, Drupal, this);
