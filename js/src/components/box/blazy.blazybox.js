/**
 * @file
 * Provides a fullscreen video view for Intense, ElevateZoomPlus, etc.
 *
 * @todo provide Native Fullscreen API toggler with an optional polyfill.
 */

(function ($, Drupal, _win, _doc) {

  'use strict';

  var _id = 'blazybox';
  var _nick = 'b-box';
  var _idOnce = _id;
  var _mounted = 'is-' + _nick;
  var _selBase = '.' + _id;
  var _selector = _selBase + ':not(.' + _mounted + ')';
  var _selContent = _selBase + '__content';
  var _btnClose = _selBase + '__close';
  var _isOpened = 'is-' + _id + '--open';
  var _visualyHidden = 'visually-hidden';
  var _ariaHidden = 'aria-hidden';
  var _sanitizer = $.sanitizer;
  var _multimedia = $.multimedia || false;
  var oClass;
  var oBodyClass;

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
     * @param {HTMLElement|string} settings
     *   The link HTMLElement to extract video/ media data, or video embed url.
     * @param {Object} options
     *   The optional options containing: class.
     */
    open: function (settings, options) {
      var me = Drupal.blazyBox;
      var $el = me.$el;
      var elContent = $el.find(_selContent);
      var content = Drupal.theme('blazyBoxMedia', {
        data: settings
      });

      var config = {
        ADD_TAGS: ['iframe'],
        ADD_ATTR: [
          'allow',
          'allowfullscreen'
        ]
      };

      Drupal.attachBehaviors($el[0]);

      $el.removeClass(_visualyHidden)
        .attr(_ariaHidden, false);

      elContent.innerHTML = _sanitizer.sanitize(content, config);

      $.addClass(_doc.body, _isOpened);

      if (options) {
        me.options = $.extend({}, me.options, options);
        var opts = me.options;

        oClass = opts.class || '';
        oBodyClass = opts.bodyClass || '';

        if (oClass) {
          $el.addClass(oClass);
        }

        if (oBodyClass) {
          $.removeClass(_doc.body, _isOpened);
          $.removeClass(_doc.body, oBodyClass);
        }

        setTimeout(function () {
          if (oBodyClass) {
            $.addClass(_doc.body, oBodyClass);
          }
        }, 301);
      }

      // Reset any (local) video/ audio to avoid multiple elements from playing.
      if (_multimedia) {
        _multimedia.pause();
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
      var $el = me.$el;

      // Allows calling this directly.
      if (!$.isUnd(e)) {
        e.preventDefault();
      }

      $el.addClass(_visualyHidden)
        .attr(_ariaHidden, true)
        .find(_selContent).innerHTML = '';

      $.removeClass(_doc.body, _isOpened);

      if (oClass) {
        $el.removeClass(oClass);
      }
      if (oBodyClass) {
        $.removeClass(_doc.body, oBodyClass);
      }

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
   * @param {Object} settings
   *   An object containing the embed url, or media object.
   *
   * @return {String}
   *   Returns a html string.
   */
  Drupal.theme.blazyBoxMedia = function (settings) {
    var data = settings.data;
    var oembedUrl = data;
    var alt;
    var dataset;
    var el;
    var href;
    var url;
    var img;
    var pad;
    var width = '';
    var html = '<div class="blazybox__fullscreen">';

    // For future betterment, allows more complex data object than just url.
    if ($.isObj(data)) {
      el = data.el || data.element;
    }
    else {
      el = data;
    }

    if ($.isElm(el)) {
      dataset = $.parse($.attr(el, 'data-b-media data-media'));
      oembedUrl = $.attr(el, 'data-oembed-url');

      // Video|Audio|Responsive|Picture elements.
      if (dataset) {
        var wdth = dataset.width ? parseInt(dataset.width, 0) : 640;
        if (wdth) {
          width = ' style="width:' + wdth + 'px"';
        }

        if (dataset.html) {
          html += '<div class="blazybox__html"' + width + '>' + dataset.html + '</div>';
        }
        else if (dataset.boxType === 'image') {
          alt = $.image.alt(el, '');
          href = el.href;
          url = $.attr(el, 'data-box-url', href, true);
          pad = $.image.ratio(dataset);
          img = '<img class="media__element" src="' + url + '" decoding="async" loading="eager" alt="' + alt + '" />';
          html += '<div class="blazybox__media"' + width + '>';
          html += '<div class="media media--ratio media--ratio--fluid" aria-live="polite" style="padding-bottom: ' + pad + '%">' + img + '</div>';
          html += '</div>';
        }
      }
    }

    // Iframe element.
    if ($.isStr(oembedUrl) && !_sanitizer.isDangerous('src', oembedUrl)) {
      html += '<iframe src="' + oembedUrl + '" width="100%" height="100%" allowfullscreen></iframe>';
    }

    html += '</div>';

    return html;
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
