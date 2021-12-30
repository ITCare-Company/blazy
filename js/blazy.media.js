/**
 * @file
 * Provides Media module integration.
 */

(function (Drupal, _d, _doc) {
  'use strict';

  var _md = 'media';
  var _id = 'blazy-' + _md;
  var _player = _md + '--player';
  var _mounted = _player + '--on';
  var _element = '.' + _player + ':not(.' + _mounted + ')';
  var _icon = _md + '__icon';
  var _elIconPlay = '.' + _icon + '--play';
  var _elIconClose = '.' + _icon + '--close';
  var _iFrame = 'iframe';
  var _isPlaying = 'is-playing';
  var _dataIFrameTitle = 'data-iframe-title';
  var _dataUrl = 'data-url';

  /**
   * Blazy media utility functions.
   *
   * @param {HTMLElement} el
   *   The media player HTML element.
   */
  function blazyMedia(el) {
    var iframe = _d.find(el, _iFrame);
    var btn = _d.find(el, _elIconPlay);

    // Media player toggler is disabled, just display iframe.
    if (_d.isNull(btn)) {
      return;
    }

    var url = _d.attr(btn, _dataUrl);
    var title = _d.attr(btn, _dataIFrameTitle);
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
      var playing = _d.find(_doc, '.' + _isPlaying);
      var iframe = _d.find(player, _iFrame);
      var video = _d.find(_doc, 'video');

      url = _d.attr(target, _dataUrl);
      title = _d.attr(target, _dataIFrameTitle);

      // First, reset any (local) video to avoid multiple videos from playing.
      if (!_d.isNull(video) && !video.paused) {
        video.pause();
      }
      if (!_d.isNull(playing)) {
        var played = _d.find(_doc, '.' + _isPlaying + ' ' + _iFrame);
        // Remove the previous iframe.
        _d.remove(played);
        playing.className = playing.className.replace(/(\S+)playing/, '');
      }

      // Appends the iframe.
      _d.addClass(player, _isPlaying);

      // Remove the existing iframe on the current clicked iframe.
      _d.remove(iframe);

      // Cache iframe for the potential repeating clicks.
      if (!newIframe) {
        newIframe = _doc.createElement(_iFrame);
        newIframe.className = _md + '__iframe ' + _md + '__element';

        _d.attr(newIframe, {
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
      var iframe = _d.find(player, _iFrame);

      if (player.className.match(_isPlaying)) {
        player.className = player.className.replace(/(\S+)playing/, '');
      }

      _d.remove(iframe);
    }

    // Remove iframe to avoid browser requesting them till clicked.
    // The iframe is there as Blazy supports non-lazyloaded/ non-JS iframes.
    _d.remove(iframe);

    // Plays the media player.
    _d.on(el, 'click.' + _id, _elIconPlay, play);

    // Closes the video.
    _d.on(el, 'click.' + _id, _elIconClose, stop);

    _d.addClass(el, _mounted);
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
    var elm = settings.el || settings.element;
    var img = _d.find(elm, 'img');
    var data = _d.parse(_d.attr(elm, 'data-' + _md));
    var alt = Drupal.checkPlain(_d.attr(img, 'alt', 'Video preview', true));
    var width = data.width ? parseInt(data.width, 10) : 640;
    var height = data.height ? parseInt(data.height, 10) : 360;
    var pad = data ? ((height / width) * 100).toFixed(2) : 100;
    var imgUrl = _d.attr(elm, 'data-box-url');
    var href = _d.attr(elm, 'href');
    var oembedUrl = _d.attr(elm, 'data-oembed-url', href, true);
    var defClass = _md + '__image ' + _md + '__element';
    var imgClass = settings.imgClass ?
      defClass + ' ' + settings.imgClass :
      defClass;
    var idClass = data.id ? ' ' + _md + '--' + data.id : '';
    var player = data.type === 'video' ? ' ' + _player : '';
    var html;

    html = '<div class="$md $idClass $md--switch $player $md--ratio $md--ratio--fluid" style="padding-bottom: $pad%">';

    html += '<img src="$imgUrl" class="$imgClass" alt="$alt" loading="lazy" decoding="async" />';

    if (player) {
      html += '<span class="$icon $icon--close"></span>';
      html += '<span class="$icon $icon--play" data-url="$oembedUrl" data-iframe-title="$alt"></span>';
    }

    html += '</div>';

    if (!settings.unwrap) {
      html = '<div class="$wrapper $wrapper--inline" style="width: $widthpx">' +
        html +
        '</div>';
    }

    return _d.template(html, {
      md: _md,
      icon: _icon,
      idClass: idClass,
      player: player,
      pad: pad,
      imgUrl: imgUrl,
      imgClass: imgClass,
      alt: alt,
      oembedUrl: oembedUrl,
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
      context = _d.context(context);

      _d.once(blazyMedia, _element, context);
    }
  };
})(Drupal, dBlazy, this.document);
