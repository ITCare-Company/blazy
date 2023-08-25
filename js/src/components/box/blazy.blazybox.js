/**
 * @file
 * Provides a fullscreen video view for Intense, ElevateZoomPlus, etc.
 *
 * @todo provide Native Fullscreen API toggler with an optional polyfill.
 */

(function ($, Drupal, _win, _doc) {

  'use strict';

  var _id = 'blazybox';
  var _nick = 'bbox';
  var _idOnce = _id;
  var _mounted = 'is-' + _nick;
  var _selBase = '.' + _id;
  var _selector = _selBase + ':not(.' + _mounted + ')';
  var _selContent = _selBase + '__content';
  var _cMediaElement = 'media__element';
  var _btnClose = _selBase + '__close';
  var _isOpened = 'is-' + _id + '--open';
  var _fitHeight = _mounted + '--fh';
  var _isFullscreen = _mounted + '--fs';
  var _visualyHidden = 'visually-hidden';
  var _ariaHidden = 'aria-hidden';
  var _sanitizer = $.sanitizer;
  var _multimedia = $.multimedia || false;
  var _instagram = $.instagram || false;
  var oClass;
  var oBodyClass;
  var oBodyClosingClass;

  /**
   * Blazybox public methods.
   *
   * @namespace
   */
  Drupal.blazyBox = {
    btnClose: null,
    el: null,
    $el: null,
    options: {
      hideCloseBtn: false
    },

    /**
     * Open the blazyBox.
     *
     * @param {HTMLElement} trigger
     *   The link HTMLElement to extract video/ media data.
     * @param {Object} options
     *   The optional options containing: classes.
     */
    open: function (trigger, options) {
      var me = Drupal.blazyBox;
      var body = _doc.body;
      var $el = me.$el;
      var link = toElm(trigger);
      var dataset = $.isElm(link) ? $.parse($.attr(link, 'data-b-media data-media')) : {};
      var elContent = $el.find(_selContent);
      var elIframe;
      var elMedia;
      var isInstagram;
      var winSize = $.windowSize();
      var opts = options || {};

      // Separate theme options from lighbox options.
      if ($.isUnd(opts.fs)) {
        opts.fs = true;
        opts.width = winSize.width;
        opts.height = winSize.height;
      }

      var content = Drupal.theme('blazyBoxMedia', {
        el: link,
        dataset: dataset,
        options: opts
      });

      var config = {
        ADD_TAGS: ['iframe'],
        ADD_ATTR: [
          'allow',
          'allowfullscreen'
        ]
      };

      // Drupal.attachBehaviors($el[0]);
      $el.removeClass(_visualyHidden)
        .attr(_ariaHidden, false);

      if (opts.fs) {
        $el.addClass(_isFullscreen);
      }

      elContent.innerHTML = _sanitizer.sanitize(content, config);

      if (options) {
        me.options = $.extend({}, me.options, options);
        var o = me.options;

        oClass = o.class || '';
        oBodyClass = o.bodyClass || '';
        oBodyClosingClass = o.bodyClosingClass || '';

        if (oClass) {
          $el.addClass(oClass);
        }

        if (oBodyClass) {
          $.removeClass(body, oBodyClass);
        }

        setTimeout(function () {
          if (oBodyClass) {
            $.addClass(body, oBodyClass);
          }
        }, 301);
      }
      else {
        $.addClass(body, _isOpened);
      }

      // Reset any (local) video/ audio to avoid multiple elements from playing.
      if (_multimedia) {
        _multimedia.pause();
      }

      $el[0].style.minHeight = '';
      $el.removeClass(_fitHeight);

      Drupal.attachBehaviors($el[0]);

      // Initialize Instagram after being attached.
      elMedia = $.find(elContent, '.media');
      elIframe = $.find(elContent, 'iframe');

      if ($.isElm(elMedia) && $.isElm(elIframe)) {
        isInstagram = $.hasClass(elMedia, 'b-instagram');

        if (isInstagram && _instagram) {
          setTimeout(function () {
            var cb = function (obj) {
              var h = (obj.height + 30) + 'px';
              var w = obj.width + 'px';

              $el[0].style.minHeight = h;
              elMedia.style.width = w;

              // Instagram takes up the window height at small areas, normally.
              $el.addClass(_fitHeight);
            };
            _instagram.show(cb, elIframe);
          }, 101);
        }
      }

      if ($.isElm(elIframe)) {
        $.addClass(elIframe, _cMediaElement);
      }

      me.check();
    },

    /**
     * Close the blazyBox.
     *
     * @param {Event} e
     *   The mouse event triggering the close.
     */
    close: function (e) {
      var me = Drupal.blazyBox;
      var body = _doc.body;
      var $el = me.$el;

      // Allows calling this directly.
      if (!$.isUnd(e)) {
        e.preventDefault();
      }

      var closing = function () {
        $el.addClass(_visualyHidden)
          .attr(_ariaHidden, true)
          .find(_selContent).innerHTML = '';
      };

      var transitioning = function () {
        if (oBodyClosingClass) {
          $.removeClass(body, oBodyClosingClass);
        }
        if (oClass) {
          $el.removeClass(oClass);
          closing();
        }

        $el.off('transitionend', transitioning);
      };

      $.removeClass(body, _isOpened);
      $el.removeClass(_isFullscreen);

      if (oBodyClass) {
        $.removeClass(body, oBodyClass);
      }
      if (oBodyClosingClass) {
        $.addClass(body, oBodyClosingClass);
      }
      else {
        closing();
      }

      $el.on('transitionend', transitioning);

      // Failsafe in case transitionend is screwed up, people click it rapidly.
      setTimeout(function () {
        if ($el.hasClass(oClass)) {
          transitioning();
        }
      }, 1000);

      Drupal.detachBehaviors($el[0]);
    },

    check: function () {
      var me = this;

      if (me.options.hideCloseBtn) {
        var close = me.btnClose || me.$el.find(_btnClose);
        $.addClass(close, _visualyHidden);
      }
    },

    /**
     * Attach the blazyBox.
     */
    attach: function () {
      var check = $.find(_doc.body, _selBase);
      if (!$.isElm(check)) {
        $.append(_doc.body, Drupal.theme('blazyBox'));
      }
    },

    isOpened: function () {
      var me = Drupal.blazyBox;
      return !me.$el.hasClass(_visualyHidden);
    }
  };

  // For future betterment, allows more complex data object than just url.
  function toElm(data) {
    var el = data;
    if ($.isObj(data)) {
      el = data.el || data.element;
    }
    return $.isElm(el) ? el : null;
  }

  /**
   * Theme function for a fullscreen lightbox video container.
   *
   * @return {String}
   *   Returns a html string.
   */
  Drupal.theme.blazyBox = function () {
    var html;

    html = '<div class="$id visually-hidden" tabindex="-1" role="dialog" aria-hidden="true" aria-label="$id">';
    html += '<div class="$id__content"></div>';
    html += '<button class="$id__close" data-role="none">&times;</button>';
    html += '</div>';

    return $.template(html, {
      id: _id
    });
  };

  /**
   * Theme function for a standalone fullscreen video.
   *
   * @param {Object} data
   *   An object containing:
   *   - el: The lightbox link element, normally [data-LIGHTBOX-trigger].
   *   - dataset: the [data-b-media] object, extracted from link element.
   *   - options: extra options not contained within dataset.
   *
   * @return {String}
   *   Returns a html string.
   */
  Drupal.theme.blazyBoxMedia = function (data) {
    var el = data.el;
    var dataset = data.dataset || {};
    var options = data.options || {};
    var fs = options.fs;
    var oembedUrl = $.attr(el, 'data-oembed-url');
    var alt;
    var href;
    var url;
    var pad;
    var content = dataset.html;
    var isMedia = true;
    var html = '';

    // Video|Audio|Responsive|Picture elements.
    if (content) {
      isMedia = false;
      if (dataset.encoded) {
        content = atob(content);
      }

      html += content;
    }
    else if (dataset.boxType === 'image') {
      fs = true;
      options.width = dataset.width;
      options.height = dataset.height;
      alt = $.image.alt(el, '');
      href = el.href;
      url = $.attr(el, 'data-box-url', href, true);
      html += '<img class="' + _cMediaElement + '" src="' + url + '" decoding="async" loading="eager" alt="' + alt + '" />';
    }

    // Iframe element.
    if (oembedUrl && !_sanitizer.isDangerous('src', oembedUrl)) {
      html += '<iframe class="' + _cMediaElement + '" src="' + oembedUrl + '" width="100%" height="100%" allowfullscreen></iframe>';
    }

    if (fs && options.width && isMedia) {
      pad = $.image.ratio(options);
      var mdClass = 'media media--ratio media--ratio--fluid';
      var mdStyle = 'padding-bottom: ' + pad + '%; width:' + options.width + 'px;';
      html = '<div class="' + mdClass + '" style="' + mdStyle + '">' + html + '</div>';
    }

    return '<div class="' + _id + '__media">' + html + '</div>';
  };

  /**
   * BlazyBox utility functions.
   *
   * @param {HTMLElement} el
   *   The blazybox HTML element.
   */
  function process(el) {
    var me = Drupal.blazyBox;
    var $el = $(el);

    me.el = el;
    me.$el = $el;
    me.btnClose = $el.find(_btnClose);

    $el.on('click.' + _id, _btnClose, me.close, true);
    $el.addClass(_mounted);
  }

  /**
   * Attaches Blazybox behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyBox = {
    attach: function (context) {

      Drupal.blazyBox.attach();

      $.once(process, _idOnce, _selector, context);
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        $.once.removeSafely(_idOnce, _selector, context);
      }
    }
  };

})(dBlazy, Drupal, this, this.document);
