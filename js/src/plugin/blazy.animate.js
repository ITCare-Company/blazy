/**
 * @file
 * Provides animate extension for dBlazy when using blur or animate.css.
 *
 * Alternative for native Element.animate, only with CSS animation instead.
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Element/animate
 */

(function ($, _win) {

  'use strict';

  var PLACEHOLDER = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
  var ANI = 'animation';
  var BLUR = 'blur';
  var B_BLUR = 'b-' + BLUR;
  var K_BLUR = 'b' + BLUR;
  var BLUR_STORAGES = [];
  var P_DATA = 'data-';
  var IS_STORAGE = _win.localStorage;

  /**
   * A simple wrapper to animate anything using animate.css.
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string|Function} cb
   *   Any custom animation name, fallbacks to [data-animation], or a callback.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function animate(els, cb) {
    var me = this;

    var chainCallback = function (el) {
      var _set = el.dataset;

      if (!$.isElm(el) || !_set) {
        return me;
      }

      var $el = $(el);
      var animation = _set.animation || _set.bAnimation;

      if ($.isStr(cb)) {
        animation = cb;
      }

      if (!animation) {
        return me;
      }

      var _animated = 'animated';
      var _aniEnd = ANI + 'end.' + animation;
      var _style = el.style;
      var classes = _animated + ' ' + animation;
      var props = [
        ANI,
        ANI + '-duration',
        ANI + '-delay',
        ANI + '-iteration-count'
      ];

      $el.addClass(classes);

      $.each(['Duration', 'Delay', 'IterationCount'], function (key) {
        var _aniKey = ANI + key;
        if (_set && _aniKey in _set) {
          _style[_aniKey] = _set[_aniKey];
        }
      });

      // Supports both BG and regular image.
      var cn = $.closest(el, '.media') || el;
      var bg = $el.hasClass('b-bg');
      var isBlur = animation === BLUR;
      var an = el;

      // The animated blur is image not this container, except a background.
      if (isBlur && !bg) {
        var img = $.find(cn, 'img:not(.' + B_BLUR + ')');
        an = $.isElm(img) ? img : an;
      }

      function ended(e) {
        $el.addClass('is-b-' + _animated)
          .removeClass(classes)
          .removeAttr(props, P_DATA);

        $.each(props, function (key) {
          _style.removeProperty(key);
        });

        if ($.isFun(cb)) {
          cb(e);
        }

        if (isBlur) {
          var elBlur = $.find(cn, 'img.' + B_BLUR);
          if ($.isElm(elBlur)) {
            elBlur.src = PLACEHOLDER;
            $.removeAttr(elBlur, P_DATA + B_BLUR);
            $el.removeClass('is-' + BLUR + '-client');
          }
        }
      }

      return $.one(an, _aniEnd, ended, false);
    };

    return $.chain(els, chainCallback);
  }

  // https://developer.mozilla.org/en-US/docs/Web/API/HTMLCanvasElement.
  // https://caniuse.com/canvas
  function toDataUri(url, mime, cb) {
    var img = new Image();
    var load = function () {
      var me = this;

      var canvas = $.create('canvas');
      canvas.width = me.naturalWidth;
      canvas.height = me.naturalHeight;

      canvas.getContext('2d')
        .drawImage(me, 0, 0);

      cb(canvas.toDataURL(mime));
    };

    img.src = url;

    $.decode(img)
      .then(function () {
        load.call(img);
      })
      .catch(function () {
        cb(url);
      });
  }

  /**
   * Processes blur element.
   *
   * @param {Element} target
   *   The .b-lazy element, not the .b-blur one.
   */
  function blur(target) {
    var cn = $.aniElement && $.aniElement(target);
    if (!$.isElm(cn)) {
      return;
    }

    var el = $.find(cn, 'img.' + B_BLUR);
    if (!$.isElm(el)) {
      return;
    }

    var data = $.attr(el, P_DATA + B_BLUR);
    if (!data) {
      return;
    }

    data = data.split('::');

    var shouldStore = IS_STORAGE && data[0] === '1';
    var isDisabled = data[0] === '-1';
    var bid = data[1];
    var mime = data[2];
    var url = data[3];
    var existing = null;
    var valid = false;
    var stored = $.storage(K_BLUR);
    // @todo remove at 3.x:
    var dt = 'data-thumb';
    var dbt = 'data-b-thumb';
    var dtValue = $.attr(cn, dbt + ' ' + dt);
    var found;

    if (dtValue) {
      // @todo remove the last at 3.x:
      if ($.is(url, dbt) || $.is(url, dt)) {
        url = dtValue;
      }
    }

    // If the browser is capable, and the client option enabled.
    if (shouldStore) {
      found = stored && $.contains(stored, bid);

      valid = !stored || !found;

      BLUR_STORAGES = stored ? $.parse(stored) : [];

      if (found) {
        $.each(BLUR_STORAGES, function (img) {
          var key = $.keys(img)[0];
          if (key === bid) {
            existing = img[bid];
            return false;
          }
        });
      }
    }
    else {
      // Clear, if disabled (-1), or switching to server from client-side (0).
      if (stored) {
        $.storage(K_BLUR, null);
      }
    }

    // If client is disabled (-1), use server-side data URI. Clear done above.
    // Run it late, to ensure storages are cleared above as configured.
    if (isDisabled) {
      $.removeAttr(el, P_DATA + B_BLUR);
      return;
    }

    // We are here when client is being enabled.
    if (existing) {
      el.src = existing;
    }
    else {
      toDataUri(url, mime, function (uri) {
        el.src = uri;

        if (shouldStore && valid) {
          var tmp = {};
          tmp[bid] = uri;

          BLUR_STORAGES.push(tmp);

          $.storage(K_BLUR, JSON.stringify(BLUR_STORAGES));
        }
      });
    }
  }

  $.animate = animate.bind($);
  $.fn.animate = function (animation) {
    return animate(this, animation);
  };

  $.blur = blur.bind($);

}(dBlazy, this));
