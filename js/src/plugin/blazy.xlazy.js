/**
 * @file
 * Provides reusable methods across lazyloaders: Bio and bLazy.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy sub-modules.
 *   It is extending dBlazy as a separate plugin depending on $.viewport.
 */

(function ($, _win, _doc) {

  'use strict';

  // var _id = 'blazy';
  var _erCounted = 0;
  var _data = 'data-';
  // @todo remove at 3.x:
  var _dataAnim = _data + 'animation';
  var _dataBanim = _data + 'b-animation';
  var _src = 'src';
  var _srcSet = 'srcset';
  var _imgSources = [_srcSet, _src];
  var _bgClass = 'b-bg';

  $._defaults = {
    error: false,
    offset: 100,
    root: _doc,
    success: false,
    selector: '.b-lazy',
    separator: '|',
    container: false,
    containerClass: false,
    errorClass: 'b-error',
    loadInvisible: false,
    successClass: 'b-loaded',
    visibleClass: false,
    validateDelay: 25,
    saveViewportOffsetDelay: 50,

    // @todo recheck IO.module. Slick has data-lazy, and irrelevant for Blazy.
    srcset: 'data-srcset',
    src: 'data-src',
    bgClass: _bgClass,

    // IO specifics.
    isMedia: false,
    parent: '.media',
    disconnect: false,
    intersecting: false,
    observing: false,
    resizing: false,
    mobileFirst: false,
    rootMargin: '0px',
    threshold: [0]
  };

  // Returns a success.
  function success(el, status, parent, opts) {
    // Who knows Safari has different interpretation on Function:
    // See https://www.drupal.org/project/blazy/issues/3279316.
    if ($.isFun(opts.success) || $.isObj(opts.success)) {
      opts.success(el, status, parent, opts);
    }

    if (_erCounted > 0) {
      _erCounted--;
    }
    return _erCounted;
  }

  // Returns an error.
  function error(el, status, parent, opts) {
    // Who knows Safari has different interpretation on Function:
    // See https://www.drupal.org/project/blazy/issues/3279316.
    if ($.isFun(opts.error) || $.isObj(opts.error)) {
      opts.error(el, status, parent, opts);
    }

    _erCounted++;
    return _erCounted;
  }

  // Make it private to avoid confusion.
  function loaded(el, status, opts) {
    var cn = $.closest(el, opts.parent) || el;
    var ok = status === $._ok || status === true;
    var successClass = opts.successClass;
    var errorClass = opts.errorClass;
    var isSuccess = 'is-' + successClass;
    var isError = 'is-' + errorClass;

    $.addClass(el, ok ? successClass : errorClass);

    // Adds context for effects: blur, etc. considering BG, or just media.
    $.addClass(cn, ok ? isSuccess : isError);

    if (ok) {
      _erCounted = success(el, status, cn, opts);
      // Native may already remove `data-[SRC|SRCSET]` early, except BG/Video.
      if ($.hasAttr(el, _data + _src)) {
        $.removeAttr(el, _imgSources, _data);
      }
    }
    else {
      _erCounted = error(el, status, cn, opts);
    }

    // @todo remove in case causing double triggers with blazy.done.
    // $.trigger(el, _id + '.loaded', {
    // status: status
    // });
    return _erCounted;
  }

  /**
   * Checks if image or iframe is decoded/ completely loaded.
   *
   * @private
   *
   * @param {Image|Iframe} el
   *   The Image or Iframe element.
   *
   * @return {bool}
   *   True if the image or iframe is loaded.
   */
  $.isCompleted = function (el) {
    if ($.isElm(el)) {
      if ($.equal(el, 'img')) {
        return $.isDecoded(el);
      }
      if ($.equal(el, 'iframe')) {
        var doc = el.contentDocument || el.contentWindow.document;
        return doc.readyState === 'complete';
      }
    }
    return false;
  };

  $.selector = function (opts, suffix) {
    var selector = opts.selector;
    // @todo recheck, troubled for onresize: + ':not(.' + opts.successClass + ')'.
    if (suffix && $.isBool(suffix)) {
      suffix = ':not(.' + opts.successClass + ')';
    }

    suffix = suffix || '';
    return selector + suffix;
  };

  $.status = function (el, status, opts) {
    // Image decode fails with Responsive image, assumes ok, no side effects.
    return loaded(el, status, opts);
  };

  $.aniElement = function (el) {
    // @todo remove the last at 3.x:
    // If BG, the container itself is the animated element.
    if ($.hasAttr(el, _dataBanim) || $.hasAttr(el, _dataAnim)) {
      return el;
    }

    // Else anything else, will traverse the parent/ closest animated element.
    return $.closest(el, '[' + _dataBanim + ']') || $.closest(el, '[' + _dataAnim + ']');
  };

})(dBlazy, this, this.document);
