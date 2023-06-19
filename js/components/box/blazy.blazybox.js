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
  var _elBase = '.' + _id;
  var _element = _elBase + ':not(.' + _mounted + ')';
  var _elContent = _elBase + '__content';
  var _btnClose = _elBase + '__close';
  var _isOpened = 'is-' + _id + '--open';
  var _visualyHidden = 'visually-hidden';
  var _ariaHidden = 'aria-hidden';

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
     */
    open: function (settings) {
      var me = Drupal.blazyBox;
      var $el = me.$el;
      var content = Drupal.theme('blazyBoxMedia', {
        data: settings
      });

      Drupal.attachBehaviors($el[0]);

      $el.removeClass(_visualyHidden)
        .attr(_ariaHidden, false)
        .find(_elContent).innerHTML = content;

      $.addClass(_doc.body, _isOpened);

      // First, reset any (local) video to avoid multiple videos from playing.
      $.pauseVideo();

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
        .find(_elContent).innerHTML = '';

      $.removeClass(_doc.body, _isOpened);

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
      if (!$.isElm($.find(_doc.body, _element))) {
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

    html = '<div id="$id" class="$id visually-hidden" tabindex="-1" role="dialog" aria-hidden="true" aria-label="$id">';
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
    var html = '';

    html = '<div class="media media--fullscreen">';

    // For future betterment, allows more complex data object than just url.
    if ($.isObj(data)) {
      var $el = $(data.el || data.element);
      var href = $el.attr('href');
      oembedUrl = $el.attr('data-oembed-url', href, true);
    }

    if ($.isStr(oembedUrl)) {
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

      $.once(process, _idOnce, _element, context);
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        $.once.removeSafely(_idOnce, _element, context);
      }
    }
  };

})(dBlazy, Drupal, this, this.document);
