/**
 * @file
 * Provides Media module integration.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Attaches Blazy media behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyMedia = {
    attach: function (context) {
      $('.media--player', context).once('blazy-media').each(blazyMedia);
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        $('.media--player', context).removeOnce('blazy-media').off('.media-play .media-close');
      }
    }
  };

  /**
   * Blazy media utility functions.
   *
   * @param {int} i
   *   The index of the current element.
   * @param {HTMLElement} media
   *   The media player HTML element.
   */
  function blazyMedia(i, media) {
    var t = $(media);

    var $slider = t.closest('.slick__slider');
    var $slick = $slider.closest('.slick');
    var iframe = t.find('iframe');
    var newIframe = iframe.clone().addClass('media__cloned');
    var dataMedia = newIframe.data('media');
    var url = newIframe.data('lazy') || newIframe.data('src');
    var $nester = '';

    if ($slick.closest('.slick__slider').length) {
      $nester = $slick.closest('.slick__slider');
    }

    /**
     * Play the media.
     *
     * @param {jQuery.Event} event
     *   The event triggered by a `click` event.
     *
     * @return {bool}|{mixed}
     *   Return false if url is not available.
     */
    function play(event) {
      event.preventDefault();

      var $btn = $(this);

      // Soundcloud needs internet, fails on disconnected local.
      if (url === '') {
        return false;
      }

      // Temp fix for sortable and reslick after being destroyed.
      url = $btn.data('url');

      var auto_play = url.indexOf('auto_play');
      var autoplay = url.indexOf('autoplay');
      var param = url.indexOf('?');

      // Force autoplay, if not provided, which should not.
      if (dataMedia && dataMedia.scheme === 'soundcloud') {
        if (auto_play < 0 || auto_play === false) {
          url = param < 0 ? url + '?auto_play=true' : url + '&amp;auto_play=true';
        }
      }
      else if (autoplay < 0 || autoplay === 0) {
        url = param < 0 ? url + '?autoplay=1' : url + '&amp;autoplay=1';
      }

      // First, reset any video to avoid multiple videos from playing.
      t.removeClass('is-playing');

      // Clean up any pause marker at slider container.
      $('.is-paused').removeClass('is-paused');

      // Last, pause the slide, for just in case autoplay is on, and
      // pauseOnHover is disabled, and then trigger autoplay.
      if ($slider.length) {
        $slider.addClass('is-paused').slick('slickPause');

        if ($nester) {
          $nester.addClass('is-paused').slick('slickPause');
        }
      }

      t.addClass('is-playing').append(newIframe);
      newIframe.attr('src', url);

      // Temp fix for sortable and reslick after being destroyed.
      // Perhaps shouldn't clone it in the first place.
      window.setTimeout(function () {
        if (!t.find('.media__cloned').length) {
          newIframe = $('<iframe />', {class: 'media__iframe media__element media__clone', src: url});

          t.append(newIframe);
        }
      }, 800);
    }

    /**
     * Close the media.
     *
     * @param {jQuery.Event} event
     *   The event triggered by a `click` event.
     */
    function stop(event) {
      event.preventDefault();

      $(event.delegateTarget).removeClass('is-playing').find('iframe').remove();
      $('.is-paused').removeClass('is-paused');
    }

    /**
     * Trigger the media close.
     *
     * @param {jQuery.Event} event
     *   The event triggered by a `click` event.
     */
    function closeOut(event) {
      $(event.delegateTarget).find('.is-playing .media__icon--close').trigger('click.media-close');
    }

    // Remove iframe to avoid browser requesting them till clicked.
    iframe.remove();

    // Plays the media player.
    t.on('click.media-play', '.media__icon--play', play);

    // Closes the video.
    t.on('click.media-close', '.media__icon--close', stop);

    // Turns off any video if any change to the slider.
    if ($slider.length) {
      $slider.on('afterChange', closeOut);

      if ($nester) {
        $nester.on('afterChange', closeOut);
      }
    }
  }

})(jQuery, Drupal);
