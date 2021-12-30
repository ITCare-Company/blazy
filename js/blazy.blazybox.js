/**
 * @file
 * Provides a fullscreen video view for Intense, Slick Browser, etc.
 */

(function (Drupal, _d, _doc) {

  'use strict';

  var _id = 'blazybox';
  var _mounted = _id + '--on';
  var _element = '.' + _id + ':not(.' + _mounted + ')';
  var _elContent = _element + '__content';
  var _open = 'is-' + _id + '--open';
  var _hidden = 'visually-hidden';
  var _ariaHidden = 'aria-hidden';

  /**
   * Blazybox public methods.
   *
   * @namespace
   */
  Drupal.blazyBox = {
    el: _d.find(_doc, _element),

    /**
     * Open the blazyBox.
     *
     * @param {string} embedUrl
     *   The video embed url.
     */
    open: function (embedUrl) {
      var me = this;
      var mediaEl = Drupal.theme('blazyBoxMedia', {embedUrl: embedUrl});

      Drupal.attachBehaviors(me.el);
      _d.find(me.el, _elContent).innerHTML = mediaEl;

      _d.removeClass(me.el, _hidden);
      _d.attr(me.el, _ariaHidden, false);
      _d.addClass(_doc.body, _open);
    },

    /**
     * Attach the blazyBox.
     */
    attach: function () {
      if (_d.find(_doc, _element) === null) {
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
      e.preventDefault();

      _d.addClass(el, _hidden);
      _d.attr(el, _ariaHidden, true);
      _d.find(el, _elContent).innerHTML = '';
      _d.removeClass(_doc.body, _open);
    }
  };

  /**
   * Theme function for a fullscreen lightbox video container.
   *
   * @return {HTMLElement}
   *   Returns a HTMLElement object.
   */
  Drupal.theme.blazyBox = function () {
    var html;

    html = '<div id="$id" class="$id visually-hidden" tabindex="-1" role="dialog" aria-hidden="true">';
    html += '<div class="$id__content">$placeholder</div>';
    html += '<button class="$id__close" data-role="none">&times;</button>';
    html += '</div>';

    return _d.template(html, {
      id: _id,
      placeholder: Drupal.t('Dynamic video content.')
    });
  };

  /**
   * Theme function for a standalone fullscreen video.
   *
   * @param {Object} settings
   *   An object containing the embed url.
   *
   * @return {HTMLElement}
   *   Returns a HTMLElement object.
   */
  Drupal.theme.blazyBoxMedia = function (settings) {
    var html;

    html = '<div class="media media--fullscreen">';
    html += '<iframe src="' + settings.embedUrl + '" width="100%" height="100%" allowfullscreen></iframe>';
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

    _d.addClass(box, _mounted);
    me.el = box;

    _d.on(me.el, 'click', _element + '__close', me.close);
  }

  /**
   * Attaches Blazybox behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyBox = {
    attach: function (context) {

      context = _d.context(context);

      _d.once(doBlazyBox, _element, context);
    }
  };

})(Drupal, dBlazy, this.document);
