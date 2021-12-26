/**
 * @file
 * Provides Media module integration.
 */

(function (Drupal, once, _db, _doc) {
  'use strict';

  var _md = 'media';
  var _id = 'blazy-' + _md;
  var _element = '.' + _md + '--player';

  /**
   * Blazy media utility functions.
   *
   * @param {HTMLElement} el
   *   The media player HTML element.
   */
  function blazyMedia(el) {
    var iframe = el.querySelector('iframe');
    var btn = el.querySelector('.' + _md + '__icon--play');

    // Media player toggler is disabled, just display iframe.
    if (btn === null) {
      return;
    }

    var url = _db.attr(btn, 'data-url');
    var title = _db.attr(btn, 'data-iframe-title');
    var newIframe;

    /**
     * Play the media.
     *
     * @param {Event} e
     *   The event triggered by a `click` event.
     *
     * @return {bool}|{mixed}
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
      var playing = _doc.querySelector('.is-playing');
      var iframe = player.querySelector('iframe');

      url = _db.attr(target, 'data-url');
      title = _db.attr(target, 'data-iframe-title');

      // First, reset any video to avoid multiple videos from playing.
      if (playing !== null) {
        var played = _doc.querySelector('.is-playing iframe');
        // Remove the previous iframe.
        _db.remove(played);
        playing.className = playing.className.replace(/(\S+)playing/, '');
      }

      // Appends the iframe.
      player.classList.add('is-playing');

      // Remove the existing iframe on the current clicked iframe.
      _db.remove(iframe);

      // Cache iframe for the potential repeating clicks.
      if (!newIframe) {
        newIframe = _doc.createElement('iframe');
        newIframe.className = _md + '__iframe ' + _md + '__element';

        _db.setAttrs(newIframe, {
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
      var iframe = player.querySelector('iframe');

      if (player.className.match('is-playing')) {
        player.className = player.className.replace(/(\S+)playing/, '');
      }

      _db.remove(iframe);
    }

    // Remove iframe to avoid browser requesting them till clicked.
    // The iframe is there as Blazy supports non-lazyloaded/ non-JS iframes.
    _db.remove(iframe);

    // Plays the media player.
    _db.on(el, 'click', '.' + _md + '__icon--play', play);

    // Closes the video.
    _db.on(el, 'click', '.' + _md + '__icon--close', stop);

    el.classList.add(_md + '--player--on');
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
    var img = elm.querySelector('img');
    var data = _db.attr(elm, 'data-' + _md);
    data = data ? _db.parse(data) : {};
    var alt = Drupal.checkPlain(_db.attr(img, 'alt', 'Video preview'));
    var width = data.width ? parseInt(data.width) : 640;
    var height = data.height ? parseInt(data.height) : 360;
    var pad = data ? ((height / width) * 100).toFixed(2) : 100;
    var imgUrl = _db.attr(elm, 'data-box-url');
    var href = _db.attr(elm, 'href');
    var oembedUrl = _db.attr(elm, 'data-oembed-url', href);
    var defClass = _md + '__image ' + _md + '__element';
    var imgClass = settings.imgClass
      ? defClass + ' ' + settings.imgClass
      : defClass;
    var idClass = data.id ? ' ' + _md + '--' + data.id : '';
    var player = data.type === 'video' ? ' ' + _md + '--player' : '';
    var div = 'div';
    var span = 'span';
    var html;

    html =
      '<$div class="$md $idClass $md--switch $player $md--ratio $md--ratio--fluid" style="padding-bottom: $pad%">';

    html +=
      '<img src="$imgUrl" class="$imgClass" alt="$alt" loading="lazy" decoding="async" />';

    if (player) {
      html += '<$span class="$md__icon $md__icon--close"></$span>';
      html +=
        '<$span class="$md__icon $md__icon--play" data-url="$oembedUrl" data-iframe-title="$alt"></$span>';
    }

    html += '</$div>';

    if (!settings.unwrap) {
      html =
        '<$div class="$md-wrapper $md-wrapper--inline" style="width: $widthpx">' +
        html +
        '</$div>';
    }

    return _db.template(html, {
      div: div,
      span: span,
      md: _md,
      idClass: idClass,
      player: player,
      pad: pad,
      imgUrl: imgUrl,
      imgClass: imgClass,
      alt: alt,
      oembedUrl: oembedUrl,
      width: width
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
