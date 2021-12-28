/**
 * @file
 * Provides Media module integration.
 */

(function (Drupal, once, _db, _doc) {
  'use strict';

  var _md = 'media';
  var _id = 'blazy-' + _md;
  var _player = _md + '--player';
  var _element = '.' + _player;
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
    var iframe = _db.find(el, _iFrame);
    var btn = _db.find(el, _elIconPlay);

    // Media player toggler is disabled, just display iframe.
    if (_db.isNull(btn)) {
      return;
    }

    var url = _db.attr(btn, _dataUrl);
    var title = _db.attr(btn, _dataIFrameTitle);
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
      var playing = _db.find(_doc, '.' + _isPlaying);
      var iframe = _db.find(player, _iFrame);
      var video = _db.find(_doc, 'video');

      url = _db.attr(target, _dataUrl);
      title = _db.attr(target, _dataIFrameTitle);

      // First, reset any (local) video to avoid multiple videos from playing.
      if (!_db.isNull(video) && !video.paused) {
        video.pause();
      }
      if (!_db.isNull(playing)) {
        var played = _db.find(_doc, '.' + _isPlaying + ' ' + _iFrame);
        // Remove the previous iframe.
        _db.remove(played);
        playing.className = playing.className.replace(/(\S+)playing/, '');
      }

      // Appends the iframe.
      player.classList.add(_isPlaying);

      // Remove the existing iframe on the current clicked iframe.
      _db.remove(iframe);

      // Cache iframe for the potential repeating clicks.
      if (!newIframe) {
        newIframe = _doc.createElement(_iFrame);
        newIframe.className = _md + '__iframe ' + _md + '__element';

        _db.attr(newIframe, {
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
      var iframe = _db.find(player, _iFrame);

      if (player.className.match(_isPlaying)) {
        player.className = player.className.replace(/(\S+)playing/, '');
      }

      _db.remove(iframe);
    }

    // Remove iframe to avoid browser requesting them till clicked.
    // The iframe is there as Blazy supports non-lazyloaded/ non-JS iframes.
    _db.remove(iframe);

    // Plays the media player.
    _db.on(el, 'click.' + _id, _elIconPlay, play);

    // Closes the video.
    _db.on(el, 'click.' + _id, _elIconClose, stop);

    el.classList.add(_player + '--on');
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
    var img = _db.find(elm, 'img');
    var data = _db.parse(_db.attr(elm, 'data-' + _md));
    var alt = Drupal.checkPlain(_db.attr(img, 'alt', 'Video preview', true));
    var width = data.width ? parseInt(data.width, 10) : 640;
    var height = data.height ? parseInt(data.height, 10) : 360;
    var pad = data ? ((height / width) * 100).toFixed(2) : 100;
    var imgUrl = _db.attr(elm, 'data-box-url');
    var href = _db.attr(elm, 'href');
    var oembedUrl = _db.attr(elm, 'data-oembed-url', href, true);
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

    return _db.template(html, {
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
      context = _db.context(context);

      once(_id, _element, context).forEach(blazyMedia);
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
