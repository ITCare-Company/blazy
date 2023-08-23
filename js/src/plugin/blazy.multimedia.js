/**
 * @file
 * Provides Multimedia integration.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 */

(function ($, _doc) {

  'use strict';

  // Credit: https://stackoverflow.com/questions/6877403
  // https://caniuse.com/?search=HTMLMediaElement
  var _proto = HTMLMediaElement.prototype;
  if (!_proto.playing) {
    Object.defineProperty(_proto, 'playing', {
      get: function () {
        return !!(this.currentTime > 0
          && !this.paused
          && !this.ended
          && this.readyState > 2);
      }
    });
  }

  var _selPlaying = '.is-playing';
  var _selIconClose = '.media__icon--close';

  /**
   * Pause a video/ audio element.
   *
   * @param {String} type
   *   A media type for querySelectorAll, default to both audio and video.
   * @param {Document|Element} ctx
   *   An element to use as context for querySelectorAll, default to document.
   * @param {Element} current
   *   A current playing video/ audio element.
   *
   * @return {Object}
   *   The current dBlazy collection object.
   */
  function pause(type, ctx, current) {
    type = type || 'audio, video';

    var els = $.findAll(ctx, type);
    var chainCallback = function (el) {
      if ($.isElm(el) && el !== current) {
        if (el.playing) {
          el.pause();
        }
      }
    };

    return $.chain(els, chainCallback);
  }

  /**
   * Pause other video/ audio elements.
   *
   * @param {Event} e
   *   A playing video/ audio event.
   */
  function pauseOthers(e) {
    var target = e.target;
    var el = $.find(_doc, _selPlaying);
    var btn;

    // Pause other local media.
    pause(null, _doc, target);

    // Stop iframe media players.
    if ($.isElm(el)) {
      btn = $.find(el, _selIconClose);
      if ($.isElm(btn)) {
        btn.click();
      }
    }
  }

  /**
   * Initialize a video/ audio element.
   *
   * @param {Element} el
   *   A video/ audio element.
   */
  function init(el) {
    $.on(el, 'playing', pauseOthers);
  }

  $.multimedia = {
    init: init,
    // listeners: listeners,
    // toggle: toggle,
    // play: play,
    pause: pause
  };

})(dBlazy, this.document);
