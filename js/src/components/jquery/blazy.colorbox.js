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
  var _sanitizer = _d.sanitizer;
  var _instagram = _d.instagram || false;
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
    // @todo remove the second at 3.x:
    var media = $box.data('bMedia') || $box.data('media') || {};
    var isIframe = media.boxType === 'iframe' && !_sanitizer.isDangerous('href', url);
    var isHtml = 'html' in media;
    var html = isHtml ? media.html : null;

    // If encoded, then decode it.
    if (html && media.encoded) {
      html = atob(html);
    }

    var runtimeOptions = {
      html: html ? _sanitizer.sanitize(html) : null,
      rel: media.rel || null,
      iframe: isIframe,
      title: function () {
        var $caption = $box.next('.litebox__caption');
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
      onCleanup: function () {
        var $media = $('#cboxContent').find('.media');
        if ($media.length) {
          Drupal.detachBehaviors($media[0]);
        }
      },
      onClosed: function () {
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

    // Resize.
    function resize(o) {
      $.colorbox.resize({
        innerWidth: o.width,
        innerHeight: o.height
      });
    }

    // Dimensions.
    function dimension(w, h) {
      return _d.image.dimension(w, h);
    }

    // Padding hack.
    function hack(a, b) {
      return _d.image.hack(a, b);
    }

    // Responsive image|Picture.
    function responsiveImage($picture, $resimage) {
      var img;

      _win.setTimeout(function () {
        img = $picture.length ? $picture[0] : $resimage[0];
        if (img) {
          if (img.complete) {
            resizeNow.call(img);
          }
          else {
            $(img).one('load', resizeNow);
          }
        }
      }, 101);
    }

    /**
     * Resize the responsive|picture image since the library doesn't get it.
     */
    function resizeNow() {
      var t = $(this);
      var w = t.width();
      var h = t.height();
      var p = t.closest('#cboxLoadedContent');
      var pw = p.width();
      var ph = p.height();
      var o;

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
        o = dimension(w, h);
        resize(o);
      }
    }

    /**
     * Resize the colorbox if any of media types (video, picture, etc.) kick in.
     */
    function resizeBox() {
      _win.clearTimeout(cboxTimer);

      var mw = _cbox.maxWidth;
      var mh = _cbox.maxHeight;
      var w = media.width || mw;
      var h = media.height || mh;
      var o = dimension(w, h);
      var shouldResize = true;
      var pad;

      // DOM ready fix.
      cboxTimer = _win.setTimeout(function () {
        if ($('#cboxOverlay').is(':visible')) {
          var $container = $('#cboxLoadedContent');
          var container = $container[0];
          var $iframe = $('.cboxIframe', container);
          var $media = $('.media--ratio', container);
          var $picture = $container.find('picture img');
          var $resimage = $container.find('img[srcset]');
          var isResimage = $resimage.length || $picture.length;
          var isInstagram = $media.hasClass('b-instagram') && _instagram;

          if (isResimage) {
            responsiveImage($picture, $resimage);

            w = mw || media.width;
            h = mh || media.height;
            o = dimension(w, h);
          }

          if ($iframe.length || $media.length) {
            if ($media.length) {
              Drupal.attachBehaviors($media[0]);

              if (isInstagram) {
                shouldResize = false;
                $iframe = $('iframe', container);
              }
            }

            // @todo consider to not use colorbox iframe for consistent .media.
            // Instagram takes time to make iframes, deferred to onload.
            if ($iframe.length) {
              if (isInstagram) {
                var cb = function (obj) {
                  o = dimension(obj.width + 'px', obj.height + 'px');
                  resize(o);
                };

                _instagram.show(cb, $iframe[0]);
              }

              $iframe.attr('width', o.width)
                .attr('height', o.height)
                .addClass('media__element');

              if (!$media.length) {
                pad = _d.image.ratio(o) + '%';
                $container.css(hack(pad, 0))
                  .addClass('media media--ratio');
              }
            }
          }
          else {
            $container.css(hack('', o.height))
              .removeClass('media media--ratio media__element');
          }

          if (shouldResize) {
            resize(o);
          }
        }
      });
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
