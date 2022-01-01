/**
 * @file
 * Provides a fullscreen video view for Intense, ElevateZoomPlus, etc.
 */

(function ($, Drupal, _win, _doc) {

  'use strict';

  var _id = 'blazybox';
  var _mounted = _id + '--on';
  var _element = '.' + _id;
  var _elContent = _element + '__content';
  var _isOpened = 'is-' + _id + '--open';
  var _visualyHidden = 'visually-hidden';
  var _ariaHidden = 'aria-hidden';

  /**
   * Blazybox public methods.
   *
   * @namespace
   */
  Drupal.blazyBox = {
    el: $.find(_doc, _element),
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
      var el = me.el;
      var content = Drupal.theme('blazyBoxMedia', {
        data: settings
      });

      Drupal.attachBehaviors(el);

      $.find(el, _elContent).innerHTML = content;

      $.removeClass(el, _visualyHidden);
      $.attr(el, _ariaHidden, false);
      $.addClass(_doc.body, _isOpened);

      me.check();
    },

    /**
     * Attach the blazyBox.
     */
    attach: function () {
      if ($.find(_doc, _element) === null) {
        // https://developer.mozilla.org/en-US/docs/Web/API/Element/insertAdjacentHTML
        _doc.body.insertAdjacentHTML('beforeend', Drupal.theme('blazyBox'));
      }
    },

    /**
     * Close the blazyBox.
     *
     * @param {Event} e
     *   The mouse event triggering the close.
     */
    close: function (e) {
      var el = Drupal.blazyBox.el;

      // Allows calling this directly.
      if (!$.isUndefined(e)) {
        e.preventDefault();
      }

      $.addClass(el, _visualyHidden);
      $.attr(el, _ariaHidden, true);
      $.find(el, _elContent).innerHTML = '';
      $.removeClass(_doc.body, _isOpened);
    },

    check: function () {
      var me = this;
      var btn = _element + '__close';

      if (me.options.hideCloseBtn) {
        var close = $.find(me.el, btn);
        if (close) {
          $.addClass(close, _visualyHidden);
        }
      }
    },

    isOpened: function () {
      var el = Drupal.blazyBox.el;
      return !$.hasClass(el, _visualyHidden);
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

    if ($.isObject(data)) {
      var elm = data.el || data.element;
      var href = $.attr(elm, 'href');
      oembedUrl = $.attr(elm, 'data-oembed-url', href, true);
    }

    if (oembedUrl) {
      html += '<iframe src="' + oembedUrl + '" width="100%" height="100%" allowfullscreen></iframe>';
    }

    html += '</div>';

    return html;
  };

  /**
   * BlazyBox utility functions.
   *
   * @param {HTMLElement} box
   *   The blazybox HTML element.
   */
  function doBlazyBox(box) {
    var me = Drupal.blazyBox;
    var btn = _element + '__close';

    me.el = box;

    $.on(box, 'click.' + _id, btn, me.close, true);
    $.addClass(box, _mounted);
  }

  /**
   * Attaches Blazybox behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyBox = {
    attach: function (context) {

      context = $.context(context);

      Drupal.blazyBox.attach();
      $.once(doBlazyBox, _element + ':not(.' + _mounted + ')', context);
    }
  };

})(dBlazy, Drupal, this, this.document);
