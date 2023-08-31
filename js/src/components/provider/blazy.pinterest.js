/**
 * @file
 * Provides pinterest initializer.
 */

(function ($, Drupal, _win, _doc) {

  'use strict';

  var _id = 'b-pinterest';
  var _nick = _id;
  var _idOnce = _nick;
  var _mounted = 'is-' + _nick;
  var _selBase = '.' + _id;
  var _sPin = '[data-pin-do]';
  var _sRendered = '[data-pin-href]';
  var _selector = _selBase + ':not(.' + _mounted + ')';
  var _dataToken = 'data-b-token';
  var script = 'https://assets.pinterest.com/js/pinit.js';

  function load(cb) {
    _win.setTimeout(function () {
      if (_win.PinUtils) {
        _win.PinUtils.build();

        if (cb) {
          cb();
        }
      }
    });
  }

  function _init(cb, token) {
    var fun = function () {
      load(cb);
    };

    if (_win.PinUtils) {
      fun();
    }
    else {
      $.getScript(script, fun, token);
    }
  }

  $.pinterest = {
    root: null,
    token: null,
    init: function (root) {
      var me = this;
      me.root = root;
      me.token = $.attr(root, _dataToken) || _id;
    },

    show: function (cb) {
      var me = this;

      var loadMedia = function () {
        if (cb) {
          cb(me);
        }
      };

      _init(loadMedia, me.token);
    }
  };

  /**
   * Pinterest utility functions.
   *
   * @param {HTMLElement} el
   *   The [data-pin-do] HTML element.
   */
  function process(el) {
    var provider = $.pinterest;
    var pin = $.find(el, _sPin);

    provider.init(el);

    var isRendered = function (root) {
      var check = $.find(root, _sRendered);
      return $.isElm(check);
    };

    var show = function (pindo) {
      _win.setTimeout(function () {
        if (!isRendered(el)) {
          provider.show();
        }
      }, $.isElm(pindo) ? 3 : 301);
    };

    if ($.isElm(pin)) {
      show(pin);
    }
    else {
      if ($.isHtml(el)) {
        $.on(el, 'blazy.done', function (e) {
          el = e.target;
          if (!isRendered(el)) {
            pin = $.find(el, _sPin);
            show(pin);
          }
        });
      }
    }

    $.addClass(el, _mounted);
  }

  /**
   * Attaches Pinterest behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyPinterest = {
    attach: function (context) {
      $.once(process, _idOnce, _selector, context);
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        $.once.removeSafely(_idOnce, _selector, context);
      }
    }
  };

}(dBlazy, Drupal, this, this.document));
