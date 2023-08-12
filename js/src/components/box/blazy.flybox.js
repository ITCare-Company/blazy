/**
 * @file
 * Provides a Flybox, a non-disruptive lightbox.
 *
 * @todo provide Native Fullscreen API toggler with an optional polyfill.
 */

(function ($, Drupal) {

  'use strict';

  var _id = 'flybox';
  var _isId = 'is-' + _id;
  var _selfClass = 'b-' + _id;
  var _bodyClass = _isId + '--open';
  var _bodyClosingClass = _isId + '--closing';
  var _idOnce = _id;
  var _mounted = _isId;
  var _dataId = 'data-' + _id;
  var _gallery = '[' + _dataId + '-gallery]:not(.' + _mounted + ')';
  var _trigger = '[' + _dataId + '-trigger]';

  /**
   * Flybox utility functions.
   *
   * @param {HTMLElement} el
   *   The flybox HTML element.
   */
  function process(el) {

    /**
     * Launch a flybox.
     *
     * @param {Event} e
     *   The click event.
     */
    function launch(e) {
      e.preventDefault();
      e.stopPropagation();

      var target = e.target;
      var link = target.href ? target : $.closest(target, _trigger);

      if ($.isElm(link)) {
        Drupal.blazyBox.open(link,
          {
            bodyClass: _bodyClass,
            bodyClosingClass: _bodyClosingClass,
            class: _selfClass
          });
      }
    }

    $.on(el, 'click.' + _id, _trigger, launch);
    $.addClass(el, _mounted);
  }

  /**
   * Attaches flybox behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.flyBox = {
    attach: function (context) {

      $.once(process, _idOnce, _gallery, context);
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        $.once.removeSafely(_idOnce, _gallery, context);
      }
    }
  };

})(dBlazy, Drupal);
