/**
 * @file
 * Provides instagram extension for dBlazy with media switchers.
 */

(function ($, Drupal, _win, _doc) {

  'use strict';

  var _id = 'b-instagram';
  var _nick = _id;
  var _idOnce = _nick;
  var _mounted = 'is-' + _nick;
  var _loaded = _mounted + '-loaded';
  var _selBase = '.' + _id;
  var _selector = _selBase + ':not(.' + _mounted + ')';
  var _dataToken = 'data-b-token';
  var _iframes = {};
  var _iFrame = 'iframe';
  var script = '//platform.instagram.com/en_US/embeds.js';
  // var script = 'https://www.instagram.com/embed.js';

  function load(cb) {
    if (_win.instgrm) {
      _win.instgrm.Embeds.process();
      if (cb) {
        cb();
      }
    }
  }

  function _init(cb, token) {
    var fun = function () {
      load(cb);
    };

    if (_win.instgrm) {
      fun();
    }
    else {
      $.getScript(script, fun, token);
    }
  }

  function update(root, w) {
    var pw = root.parentElement;

    root.style.width = w + 'px';
    if ($.hasClass(pw, 'media-wrapper')) {
      pw.style.width = w + 'px';
    }

    // @todo remove if no issues with aspect ratio.
    root.style.paddingBottom = '';
    $.removeClass(root, 'media--ratio media--ratio--fluid');
    $.addClass(root, _loaded);
  }

  function onLoad(iframe, cb) {
    var me = this;
    var root = me.root;
    var token = me.token;
    var ws = me.ws;
    var w;
    var h;

    $.on(iframe, 'load', function () {
      var ifrm = this;
      w = parseInt($.css(ifrm, 'min-width'), 0);
      h = parseInt($.height(ifrm), 0);

      if (h < 500) {
        if (ws.height < 620) {
          h = ws.height - 65;
        }
        else {
          h = 620;
        }
      }

      h = h < 100 ? 520 : h;

      update(root, w);

      me.width = w;
      me.height = h;

      if (!_iframes[token]) {
        iframe.innerHTML = '';

        _iframes[token] = {
          iframe: iframe,
          width: w,
          height: h
        };
      }

      if (cb) {
        cb(me);
      }
    });
  }

  $.instagram = {
    root: null,
    token: null,
    width: null,
    height: null,
    ws: null,
    init: function (root, obj) {
      var me = this;
      me.root = root;
      me.token = obj.token;
      me.width = obj.width;
      me.height = obj.height;
      me.ws = $.windowSize();
    },

    show: function (cb, iframe) {
      var me = this;
      var root = me.root;
      var token = me.token || $.attr(root, _dataToken);

      if (!token) {
        return;
      }

      var fromCache = function () {
        var cache = _iframes[token];
        if (cache) {
          me.width = cache.width || me.width;
          me.height = cache.height || me.height;

          update(root, me.width);

          if (cb) {
            cb(me);
          }
        }
      };

      var fromDisk = function () {
        iframe = iframe || $.find(root, _iFrame);
        if ($.isElm(iframe)) {
          onLoad.call(me, iframe, cb);
        }
      };

      var loadIframe = function () {
        if (_iframes[token]) {
          fromCache();
        }
        else {
          fromDisk();
        }
      };

      _init(loadIframe, token);
    },

    destroy: function () {
      // _iframes = {};
    },

    exists: function () {
      var token = this.token;
      return !$.isUnd(_iframes[token]) && !$.isUnd(_iframes[token].iframe);
    }
  };

  /**
   * Instagram utility functions.
   *
   * @param {HTMLElement} el
   *   The instagram HTML element.
   */
  function process(el) {
    var iframe;
    var token = $.attr(el, _dataToken);
    var instagram = $.instagram;
    var data = {
      token: token
    };

    $.ready(function () {
      instagram.init(el, data);

      iframe = $.find(el, 'iframe');

      if ($.isElm(iframe)) {
        instagram.show();
      }
      else {
        _init(null, token);
      }
    });

    $.addClass(el, _mounted);
  }

  /**
   * Attaches Instagram behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyInstagram = {
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
