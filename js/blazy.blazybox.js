/**
 * @file
 * Provides a fullscreen video view for Intense, Slick Browser, etc.
 */

(function (Drupal, once, _db, _doc) {

  'use strict';

  var _id = 'blazybox';
  var _element = '.' + _id;
  var _elContent = _element + '__content';
  var _mounted = _id + '--on';
  var _open = 'is-' + _id + '--open';
  var _hidden = 'visually-hidden';
  var _ariaHidden = 'aria-hidden';

  /**
   * Blazybox public methods.
   *
   * @namespace
   */
  Drupal.blazyBox = {
    el: _db.find(_doc, _element),

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
      _db.find(me.el, _elContent).innerHTML = mediaEl;

      me.el.classList.remove(_hidden);
      _db.attr(me.el, _ariaHidden, false);
      _doc.body.classList.add(_open);
    },

    /**
     * Attach the blazyBox.
     */
    attach: function () {
      if (_db.find(_doc, _element) === null) {
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

      el.classList.add(_hidden);
      _db.attr(el, _ariaHidden, true);
      _db.find(el, _elContent).innerHTML = '';
      _doc.body.classList.remove(_open);
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

    return _db.template(html, {
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

    box.classList.add(_mounted);
    me.el = box;

    _db.on(me.el, 'click', _element + '__close', me.close);
  }

  /**
   * Attaches Blazybox behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyBox = {
    attach: function (context) {

      context = _db.context(context);

      once(_id, _element, context).forEach(doBlazyBox);
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        if (once.find(_id, context).length) {
          once.remove(_id, _element, context);
        }
      }
    }
  };

})(Drupal, once, dBlazy, this.document);
