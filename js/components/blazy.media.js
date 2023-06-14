/**
 * @file
 * Provides Media module integration.
 */

(function ($, Drupal, _doc) {

  'use strict';

  var _md = 'media';
  var _id = 'blazy-' + _md;
  var _idOnce = 'b-' + _md;
  var _player = _md + '--player';
  var _element = '.' + _player;
  var _mounted = 'is-' + _player + '-mounted';
  var _icon = _md + '__icon';
  var _elIconPlay = '.' + _icon + '--play';
  var _elIconClose = '.' + _icon + '--close';
  var _iFrame = 'iframe';
  var _isPlaying = 'is-playing';
  var _dataIFrameTitle = 'data-' + _iFrame + '-title';
  var _dataUrl = 'data-url';

  /**
   * Blazy media utility functions.
   *
   * @param {HTMLElement} el
   *   The media player HTML element.
   */
  function process(el) {
    var $el = $(el);
    var iframe = $el.find(_iFrame);
    var btn = $el.find(_elIconPlay);

    // Media player toggler is disabled, just display iframe.
    if (!$.isElm(btn)) {
      return;
    }

    var url = $.attr(btn, _dataUrl);
    var title = $.attr(btn, _dataIFrameTitle);
    var newIframe;

    /**
     * Play the media.
     *
     * @param {Event} e
     *   The event triggered by a `click` event.
     *
     * @return {bool|mixed}
     *   Return false if url is not available.
     */
    function play(e) {
      e.preventDefault();

      // oEmbed/ Soundcloud needs internet, fails on disconnected local.
      if (url === '') {
        return false;
      }

      var target = this;
      var player = target.parentNode;
      var playing = $.find(_doc, '.' + _isPlaying);
      var iframe = $.find(player, _iFrame);
      var video = $.find(_doc, 'video');

      url = $.attr(target, _dataUrl);
      title = $.attr(target, _dataIFrameTitle);

      // First, reset any (local) video to avoid multiple videos from playing.
      if ($.isElm(video) && !video.paused) {
        video.pause();
      }

      // Remove other playing video.
      if ($.isElm(playing)) {
        var played = $.find(_doc, '.' + _isPlaying + ' ' + _iFrame);
        // Remove the previous iframe.
        $.remove(played);
        playing.className = playing.className.replace(/(\S+)playing/, '');
      }

      // Appends the iframe.
      $.addClass(player, _isPlaying);

      // Remove the existing iframe on the current clicked iframe.
      $.remove(iframe);

      // Cache iframe for the potential repeating clicks.
      if (!newIframe) {
        newIframe = $.create(_iFrame, _md + '__iframe ' + _md + '__element');

        $.attr(newIframe, {
          src: url,
          allowfullscreen: true,
          title: title
        });
      }

      player.appendChild(newIframe);
    }

    /**
     * Close the media.
     *
     * @param {Event} e
     *   The event triggered by a `click` event.
     */
    function stop(e) {
      e.preventDefault();

      var target = this;
      var player = target.parentNode;
      var iframe = $.find(player, _iFrame);

      if (player.className.match(_isPlaying)) {
        player.className = player.className.replace(/(\S+)playing/, '');
      }

      $.remove(iframe);
    }

    // Remove iframe if any to avoid browser requesting them till clicked.
    $.remove(iframe);

    // Plays the media player.
    $el.on('click.' + _id, _elIconPlay, play);

    // Closes the video.
    $el.on('click.' + _id, _elIconClose, stop);
    $el.addClass(_mounted);
  }

  /**
   * Returns the available alt.
   *
   * @param {Element} $el
   *   The triggering link element.
   *
   * @return {string}
   *   The alt.
   */
  function extractAlt($el) {
    var img = $el.find('img:not(.b-blur)');
    var alt = $.attr(img, 'alt');

    // If using BG.
    if (!alt) {
      var cn = $el.find('.media');
      alt = $.attr(cn, 'title');
    }

    // If nobody put the important info, add a fallback.
    return alt ? Drupal.checkPlain(alt) : Drupal.t('Video preview');
  }

  /**
   * Theme function for a dynamic inline video.
   *
   * @param {Object} settings
   *   An object containing the link element which triggers the lightbox.
   *   This link must have [data-media] attribute containing video metadata.
   *
   * @return {HTMLElement}
   *   Returns a HTMLElement object.
   */
  Drupal.theme.blazyMedia = function (settings) {
    // PhotoSwipe5 has element, PhotoSwipe4 el, etc.
    var el = settings.el || settings.element;
    var $el = $(el);
    var alt = extractAlt($el);
    var data = $.parse($el.attr('data-' + _md));
    var width = data.width ? parseInt(data.width, 0) : 640;
    var height = data.height ? parseInt(data.height, 0) : 360;
    var pad = data ? ((height / width) * 100).toFixed(2) : 100;
    var imgUrl = $el.attr('data-box-url');
    var href = el.href;
    var oembedUrl = $el.attr('data-oembed-url', href, true);
    var defClass = _md + '__image ' + _md + '__element';
    var imgClass = settings.imgClass ?
      defClass + ' ' + settings.imgClass :
      defClass;
    var idClass = data.id ? ' ' + _md + '--' + data.id : '';
    var player = data.type === 'video' ? ' ' + _player : '';
    var ariaClose = Drupal.t('Stop and close the video');
    var ariaPlay = Drupal.t('Load and play the video');
    var html = '';

    if (imgUrl) {
      html += '<img src="$imgUrl" class="$imgClass" alt="$alt" loading="lazy" decoding="async" />';
    }

    if (player) {
      html += '<span class="$icon $icon--close" aria-label="$ariaClose"></span>';
      html += '<span class="$icon $icon--play" data-url="$oembed" data-iframe-title="$alt" aria-label="$ariaPlay"></span>';
    }

    html = '<div class="$md $idClass $md--switch $player $md--ratio $md--ratio--fluid" style="padding-bottom: $pad%">' + html + '</div>';

    if (!settings.unwrap) {
      html = '<div class="$wrapper $wrapper--inline" style="width: $widthpx">' +
        html +
        '</div>';
    }

    return $.template(html, {
      md: _md,
      icon: _icon,
      ariaClose: ariaClose,
      ariaPlay: ariaPlay,
      idClass: idClass,
      player: player,
      pad: pad,
      imgUrl: imgUrl,
      imgClass: imgClass,
      alt: alt,
      oembed: oembedUrl,
      width: width,
      wrapper: _md + '-wrapper'
    });
  };

  /**
   * Attaches Blazy media behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyMedia = {
    attach: function (context) {
      $.once(process, _idOnce, _element + ':not(.' + _mounted + ')', context);
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        $.once.removeSafely(_idOnce, _element, context);
      }
    }
  };

})(dBlazy, Drupal, this.document);
