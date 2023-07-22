/**
 * @file
 * Provides Multimedia integration.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 */

(function ($) {

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

  /**
   * Pause a video/ audio element.
   *
   * @param {String} type
   *   A media type for querySelectorAll, default to both audio and video.
   * @param {Document|Element} ctx
   *   An element to use as context for querySelectorAll, default to document.
   *
   * @return {Object}
   *   The current dBlazy collection object.
   */
  function pause(type, ctx) {
    type = type || 'audio, video';

    var els = $.findAll(ctx, type);
    var chainCallback = function (el) {
      if ($.isElm(el)) {
        if (el.playing) {
          el.pause();
        }
      }
    };

    return $.chain(els, chainCallback);
  }

  $.multimedia = {
    // init: init,
    // listeners: listeners,
    // toggle: toggle,
    // play: play,
    pause: pause
  };

})(dBlazy);
