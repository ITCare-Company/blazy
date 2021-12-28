/**
 * @file
 * Provides a fullscreen video view for Intense, Slick Browser, etc.
 */

(function (Drupal, once, _db, _doc) {

  'use strict';

  var _id = 'blazy-box';
  var _element = '.blazybox';

  Drupal.blazyBox = Drupal.blazyBox || {};

  Drupal.blazyBox.el = _db.find(_doc, '.blazybox');

  /**
   * Theme function for a fullscreen lightbox video container.
   *
   * @return {HTMLElement}
   *   Returns a HTMLElement object.
   */
  Drupal.theme.blazyBox = function () {
    var html;

    html = '<div id="blazybox" class="blazybox visually-hidden" tabindex="-1" role="dialog" aria-hidden="true">';
    html += '<div class="blazybox__content">' + Drupal.t('Dynamic video content.') + '</div>';
    html += '<button class="blazybox__close" data-role="none">&times;</button>';
    html += '</div>';

    return html;
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
   * Open the blazyBox.
   *
   * @param {string} embedUrl
   *   The video embed url.
   */
  Drupal.blazyBox.open = function (embedUrl) {
    var me = this;
    var mediaEl = Drupal.theme('blazyBoxMedia', {embedUrl: embedUrl});

    Drupal.attachBehaviors(me.el);
    _db.find(me.el, '.blazybox__content').innerHTML = mediaEl;

    me.el.classList.remove('visually-hidden');
    _db.attr(me.el, 'aria-hidden', false);
    _doc.body.classList.add('is-blazybox--open');
  };

  /**
   * Attach the blazyBox.
   */
  Drupal.blazyBox.attach = function () {
    if (_db.find(_doc, '.blazybox') === null) {
      // https://developer.mozilla.org/en-US/docs/Web/API/Element/insertAdjacentHTML
      _doc.body.insertAdjacentHTML('beforeend', Drupal.theme('blazyBox'));
    }
  };

  /**
   * Close the blazyBox.
   *
   * @param {Event} e
   *   The mouse event triggering the close.
   */
  Drupal.blazyBox.close = function (e) {
    var el = Drupal.blazyBox.el;
    e.preventDefault();

    el.classList.add('visually-hidden');
    _db.attr(el, 'aria-hidden', true);
    _db.find(el, '.blazybox__content').innerHTML = '';
    _doc.body.classList.remove('is-blazybox--open');
  };

  /**
   * BlazyBox utility functions.
   *
   * @param {HTMLElement} box
   *   The blazybox HTML element.
   */
  function doBlazyBox(box) {
    var me = Drupal.blazyBox;
    box.classList.add('blazybox--on');
    me.el = box;

    _db.on(me.el, 'click', '.blazybox__close', me.close);
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
