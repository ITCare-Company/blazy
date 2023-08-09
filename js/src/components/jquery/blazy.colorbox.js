/**
 * @file
 *
 * A launcher for responsive (remote|local) videos, Responsive|Picture images.
 */

(function ($, _d, Drupal, drupalSettings, _win, _doc) {

  'use strict';

  var _id = 'colorbox';
  var _nick = 'cbox';
  var _idOnce = 'b-' + _nick;
  var $body = $('body');
  var _mounted = 'is-' + _idOnce;
  var _element = '[data-' + _id + '-trigger]:not(.' + _mounted + ')';
  var _blazy = Drupal.blazy || {};
  var _sanitizer = _d.sanitizer;
  var cboxTimer;

  /**
   * Blazy Colorbox utility functions.
   *
   * @param {HTMLElement} box
   *   The colorbox HTML element.
   */
  function process(box) {
    var _cbox = drupalSettings.colorbox || {};
    var $box = $(box);
    var url = box.href || 'x';
    var media = $box.data('media') || {};
    var isIframe = media.boxType === 'iframe' && !_sanitizer.isDangerous('href', url);
    var isHtml = 'html' in media;
    var runtimeOptions = {
      html: isHtml ? _sanitizer.sanitize(media.html) : null,
      rel: media.rel || null,
      iframe: isIframe,
      title: function () {
        var $caption = $box.next('.litebox-caption');
        if ($caption.length) {
          return _sanitizer.sanitize($caption[0].innerHTML);
        }
        return '';
      },
      onComplete: function () {
        removeClasses();
        $body.addClass('colorbox-on colorbox-on--' + media.type);

        if (isIframe || isHtml) {
          resizeBox();
          $body.addClass(isIframe ? 'colorbox-on--media' : 'colorbox-on--html');
        }
      },
      onClosed: function () {
        var $media = $('#cboxContent').find('.media');
        if ($media.length) {
          Drupal.detachBehaviors($media[0]);
        }
        removeClasses();
      }
    };

    /**
     * Remove the custom colorbox classes.
     */
    function removeClasses() {
      $body.removeClass(function (index, css) {
        return (css.match(/(^|\s)colorbox-\S+/g) || []).join(' ');
      });
    }

    /**
     * Resize the responsive|picture image since the library doesn't get it.
     */
    function resizeImage() {
      var t = $(this);
      var w = t.width();
      var h = t.height();
      var p = t.closest('#cboxLoadedContent');
      var pw = p.width();
      var ph = p.height();

      if (h > ph) {
        t.css('top', -(h - ph) / 2);
      }
      else if (h < ph) {
        t.css({
          height: ph,
          width: 'auto'
        });
        t.css('left', -(t.width() - pw) / 2);
      }
      else if (pw > w) {
        $.colorbox.resize({
          innerWidth: w,
          innerHeight: h
        });
      }
    }

    /**
     * Resize the colorbox if any of media types (video, picture, etc.) kick in.
     */
    function resizeBox() {
      _win.clearTimeout(cboxTimer);

      var mw = _cbox.maxWidth;
      var mh = _cbox.maxHeight;

      var o = {
        width: media.width || mw,
        height: media.height || mh
      };

      // DOM ready fix.
      cboxTimer = _win.setTimeout(function () {
        if ($('#cboxOverlay').is(':visible')) {
          var $container = $('#cboxLoadedContent');
          var $iframe = $('.cboxIframe', $container);
          var $media = $('.media--ratio', $container);
          var $video = $('video', $container);
          var $picture = $container.find('picture img');
          var $resimage = $container.find('img[srcset]');
          var isResimage = $resimage.length || $picture.length;

          if (isResimage) {
            var $img = $picture.length ? $picture : $resimage;
            _win.setTimeout(function () {
              $img.each(function () {
                if (this.complete) {
                  resizeImage.call(this);
                }
                else {
                  $(this).one('load', resizeImage);
                }
              });
            }, 101);

            o = {
              width: mw || media.width,
              height: mh || media.height
            };
          }
          else if ($video.length) {
            if (_blazy.load) {
              _blazy.load($container[0]);
            }
          }

          if (!$iframe.length && $media.length) {
            Drupal.attachBehaviors($media[0]);
          }

          if ($iframe.length || $media.length) {
            // @todo consider to not use colorbox iframe for consistent .media.
            if ($iframe.length) {
              $container.addClass('media media--ratio');
              $iframe.attr('width', o.width).attr('height', o.height).addClass('media__element');
              $container.css({
                paddingBottom: (o.height / o.width) * 100 + '%',
                height: 0
              });
            }
          }
          else {
            $container.removeClass('media media--ratio');
            $container.css({
              paddingBottom: '',
              height: o.height
            }).removeClass('media__element');
          }

          $.colorbox.resize({
            innerWidth: o.width,
            innerHeight: o.height
          });
        }
      }, 10);
    }

    $box.colorbox($.extend({}, _cbox, runtimeOptions));
    $box.addClass(_mounted);
  }

  /**
   * Attaches blazy colorbox behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyColorbox = {
    attach: function (context) {

      var _cbox = drupalSettings.colorbox;

      // Disable Colorbox for small screens.
      if (_d.isUnd(_cbox) || _cbox.mobiledetect && _d.matchMedia(_cbox.mobiledevicewidth)) {
        return;
      }

      var elms = _d.once(process, _idOnce, _element, context);
      if (elms.length) {
        $('#' + _id).attr('aria-label', 'color box');
      }
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        _d.once.removeSafely(_idOnce, _element, context);
      }
    }
  };

})(jQuery, dBlazy, Drupal, drupalSettings, this, this.document);
