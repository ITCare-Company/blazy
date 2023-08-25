/**
 * @file
 *
 * A launcher for responsive (remote|local) videos, Responsive|Picture images.
 *
 * Since 2.17, body classes is deprecated for local classes in the #colorbox.
 */

(function ($, _d, Drupal, drupalSettings, _win, _doc) {

  'use strict';

  var _id = 'colorbox';
  var _root = '#' + _id;
  var _bRoot = 'b-' + _id;
  var _nick = 'cbox';
  var _idOnce = 'b-' + _nick;
  var $body = $('body');
  var _mounted = 'is-' + _idOnce;
  var _element = '[data-' + _id + '-trigger]:not(.' + _mounted + ')';
  var _cMediaBox = 'media media--box';
  var _cMediaRatio = _cMediaBox + ' media--ratio';
  var _cboxOn = 'colorbox-on';
  var _sContent = '#cboxContent';
  var _sLoadedContent = '#cboxLoadedContent';
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
    var $root = $(_root);
    var $box = $(box);
    var url = box.href || 'x';
    // @todo remove the second at 3.x:
    var media = $box.data('bMedia') || $box.data('media') || {};
    var provider = media.provider;
    var boxType = media.boxType;
    var isIframe = boxType === 'iframe' && !_sanitizer.isDangerous('href', url);
    var isInstagram = provider === 'instagram';
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
        _win.clearTimeout(cboxTimer);

        // DOM ready fix.
        cboxTimer = _win.setTimeout(function () {
          removeClasses();

          if ($('#cboxOverlay').is(':visible')) {
            $root.addClass(_bRoot + '--' + boxType);
            if (provider) {
              $root.addClass(_bRoot + '--' + provider);
            }

            // @deprecated in 2.17, and is removed in 3.x for local classes.
            $body.addClass(_cboxOn + ' ' + _cboxOn + '--' + media.type);
            if (isIframe || isHtml) {
              // @deprecated in 2.17, and is removed in 3.x for local classes.
              $body.addClass(isIframe ? _cboxOn + '--media' : _cboxOn + '--html');

              resizeBox();
            }
          }
        });
      },
      onCleanup: function () {
        var $media = $(_sContent).find('.media');

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
      // Re-check might be empty for some reasons.
      $root = $(_root);

      // @todo remove at 3.x for local classes.
      $body.removeClass(function (index, css) {
        return (css.match(/(^|\s)colorbox-\S+/g) || []).join(' ');
      });

      $root.removeClass(function (index, css) {
        return (css.match(/(^|\s)b-colorbox-\S+/g) || []).join(' ');
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
      var p = t.closest(_sLoadedContent);
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

    // Instagram oEmbed takes time to make iframes, deferred to onload.
    function instagram($iframe, o) {
      _win.setTimeout(function () {
        var cb = function (obj) {
          if (obj.width > 180) {
            o = dimension(obj.width + 'px', obj.height + 'px');
          }

          resize(o);
        };

        _instagram.show(cb, $iframe[0]);
      }, 101);
    }

    // Padding hack container to make it responsive.
    function hackContainer($container, $iframe, o) {
      $iframe.attr('width', o.width)
        .attr('height', o.height);

      var pad = _d.image.ratio(o) + '%';

      $container.css(hack(pad, 0))
        .addClass(_cMediaRatio);
    }

    /**
     * Resize the colorbox if any of media types (video, picture, etc.) kick in.
     */
    function resizeBox() {
      var mw = _cbox.maxWidth;
      var mh = _cbox.maxHeight;
      var w = media.width || mw;
      var h = media.height || mh;
      var o = dimension(w, h);
      var shouldResize = true;
      var useHack = true;
      var $container = $(_sLoadedContent);
      var container = $container[0];
      var $iframe = $('iframe', container);
      var $media = $('.media', container);
      var $picture = $container.find('picture img');
      var $resimage = $container.find('img[srcset]');
      var isResimage = $resimage.length || $picture.length;
      var isInstagramApi = $media.hasClass('b-instagram') && _instagram;
      var isInstagramVef = !isInstagramApi && isInstagram;

      if (isResimage) {
        responsiveImage($picture, $resimage);

        w = mw || media.width;
        h = mh || media.height;
        o = dimension(w, h);
      }

      if ($iframe.length || $media.length) {
        if (isInstagramApi || isInstagramVef) {
          useHack = false;
        }

        if ($media.length) {
          Drupal.attachBehaviors($media[0]);

          // Instagram dynamic iframe only available after being attached.
          $iframe = $('iframe', container);
        }

        // @todo consider to not use colorbox iframe for consistent .media,
        // and avoid complication given Instagram oEmbed vs. VEF.
        if ($iframe.length) {
          $iframe.addClass('media__element');

          if (isInstagramApi) {
            shouldResize = false;

            instagram($iframe, o);
          }

          // Padding hack to make responsive iframe, unless disabled.
          if (!$media.length) {
            $container.addClass(_cMediaBox + ' media--' + provider);

            if (useHack) {
              hackContainer($container, $iframe, o);
            }
          }
        }
      }
      else {
        $container.css(hack('', o.height))
          .removeClass(_cMediaRatio + ' media--' + provider);
      }

      if (shouldResize) {
        resize(o);
      }
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
        $(_root).attr('aria-label', 'color box')
          .addClass(_bRoot);
      }
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        _d.once.removeSafely(_idOnce, _element, context);
      }
    }
  };

})(jQuery, dBlazy, Drupal, drupalSettings, this, this.document);
