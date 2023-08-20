/**
 * @file
 * Provides instagram extension for dBlazy with media switchers.
 */

(function ($, _win, _doc) {

  'use strict';

  var _md = 'media';
  // var _cElement = _md + '__element';
  var _sBlockquote = '.instagram-' + _md;
  var _iframes = {};
  var _iFrame = 'iframe';
  var script = '//platform.instagram.com/en_US/embeds.js';
  // var script = 'https://www.instagram.com/embed.js';

  function process(cb) {
    if (_win.instgrm) {
      _win.instgrm.Embeds.process();
      cb();
    }
  }

  function _init(cb, token) {
    var fun = function () {
      process(cb);
    };

    if (_win.instgrm) {
      fun();
    }
    else {
      $.getScript(script, fun, token);
    }
  }

  $.instagram = {
    root: null,
    script: null,
    token: null,
    width: null,
    height: null,
    init: function (root, obj) {
      var me = this;
      me.root = root;
      me.token = obj.token;
      me.width = obj.width;
      me.height = obj.height;
    },

    show: function (cb) {
      var me = this;
      var root = me.root;
      var token = me.token;
      var newIframe;
      var w;
      var h;

      if (!me.token) {
        return;
      }

      var rendered = $.find(root, _sBlockquote + ':not(' + _iFrame + ')');
      var loadIframe = function () {
        newIframe = _iframes[token] ?
          _iframes[token].iframe : $.find(root, _iFrame);

        if ($.isElm(newIframe)) {
          w = $.css(newIframe, 'min-width') || me.width;
          h = me.height || $.height(newIframe);
          w = parseInt(w, 0);
          h = parseInt(h, 0);

          h = h < 100 ? 620 : h;

          me.width = w;
          me.height = h;

          // @todo $.addClass(newIframe, _cElement);
          if (!_iframes[token]) {
            var check = $.find(root, _sBlockquote + ':not(' + _iFrame + ')');
            if ($.isElm(check)) {
              rendered = check;
            }

            newIframe.innerHTML = '';

            _iframes[token] = {
              iframe: newIframe,
              rendered: rendered,
              width: w,
              height: h
            };
          }

          if (cb) {
            cb(me);
          }
        }
      };

      setTimeout(function () {
        if (_iframes[token]) {
          rendered = _iframes[token].rendered;

          if ($.isElm(rendered)) {
            var check = $.find(root, _sBlockquote + ':not(' + _iFrame + ')');
            // Ensures payload is respected for subsequent requests, else error.
            $.remove(check);

            $.append(root, rendered);

            newIframe = _iframes[token].iframe;
            $.append(root, newIframe);
          }
        }
      });

      _init(loadIframe, token);
    },

    hide: function () {
      var me = this;
      var token = me.token;

      if (!_iframes[token]) {
        return;
      }

      var rendered = _iframes[token].rendered;
      if ($.isElm(rendered)) {
        $.append(me.root, rendered);
      }
    },

    destroy: function () {
      // _iframes = {};
    },

    exists: function () {
      var token = this.token;
      return !$.isUnd(_iframes[token]) && !$.isUnd(_iframes[token].rendered);
    }
  };

}(dBlazy, this, this.document));
