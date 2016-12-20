/**
 * @file
 * Provides Media module integration.
 */

(function ($, Drupal) {

  'use strict';

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
    var $btn = t.find('.media__icon--play');
    var url = $btn.data('url') || iframe.data('src');
    var $nester = '';
    var newIframe;

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

      // oEmbed/ Soundcloud needs internet, fails on disconnected local.
      if (url === '') {
        return false;
      }

      // Temp fix for sortable and reslick after being destroyed.
      url = $btn.data('url');

      // First, reset any video to avoid multiple videos from playing.
      $('.is-playing').removeClass('is-playing');

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

      // Appends the iframe.
      t.addClass('is-playing');
      newIframe = $('<iframe />', {class: 'media__iframe media__element', src: url, allowfullscreen: true});

      t.append(newIframe);
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
    // The iframe is there as Blazy supports non-lazyloaded/ non-JS iframes.
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

})(jQuery, Drupal);
