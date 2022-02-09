/**
 * @file
 * Provides MagnificPopup integration for Image and Media fields.
 */

(function ($, Drupal) {

  'use strict';

  var _mounted = 'is-mfp-on';
  var _gallery = '[data-mfp-gallery]:not(.' + _mounted + ')';
  var _trigger = '[data-mfp-trigger]';
  var _blazy = Drupal.blazy || {};
  var _mp;

  /**
   * Blazy MagnificPopup utility functions.
   *
   * @param {HTMLElement} box
   *   The [data-mfp-gallery] container HTML element.
   */
  function process(box) {

    init(box);

    $.addClass(box, _mounted);
  }

  function build(elms) {
    var items = [];
    var total = elms.length;
    $.each(elms, function (el, i) {
      var media = $.parse($.attr(el, 'data-media'));
      var type = media.type;
      var caption = el.nextElementSibling;
      var url = $.attr(el, 'href');
      var item = {el: el};
      var boxType = item.boxType = media.boxType;
      var src;
      var style = '';
      var width = media.width;
      var useWidth = false;

      if (boxType === 'image') {
        src = url;
        item.type = item.boxType = 'image';
      }
      else {
        if ('html' in media) {
          useWidth = boxType === 'video';
          src = media.html;
          item.type = 'inline';
        }
        else if (type === 'video') {
          useWidth = true;
          src = Drupal.theme('blazyMedia', {
            el: el
          });
          item.type = 'inline';
          item.boxType = 'video';
        }

        if (src) {
          if (width && useWidth) {
            style = ' style="width:' + width + 'px;"';
          }

          src = '<div class="mfp-html mfp-html--' + boxType + '"' + style + '><div class="mfp-inner">' + src;
          if (caption) {
            src += '<div class="mfp-bottom-bar"><div class="mfp-title">' + caption.innerHTML + '</div>' + counter((i + 1) + '/' + total) + '</div>';
          }
          src += '</div></div>';
        }
      }

      if (src) {
        item.src = src;
      }

      if (caption) {
        item.title = caption.innerHTML;
      }

      items.push(item);
    });
    return items;
  }

  function init(box) {
    var elms = $.findAll(box, _trigger);
    var items = build(elms);
    var $box = $(box);

    function prepare() {
      $box.magnificPopup({
        items: items,
        gallery: {
          enabled: elms.length > 1,
          navigateByImgClick: true,
          tCounter: '%curr%/%total%'
        },
        preloader: true,
        callbacks: {
          beforeClose: function () {
            var currItem = this.currItem;
            if (currItem && currItem.inlineElement) {
              attach(currItem.inlineElement[0]);
            }
          },
          change: function () {
            var content = this.content;
            if (content && content.length) {
              var el = content[0];
              if ($.hasClass(el, 'media media-wrapper mfp-html')) {
                attach(el, true);
              }
            }
          },
          open: function () {
            var $wrap = this.wrap;
            if ($wrap && $wrap.length) {
              // FOUC fix.
              setTimeout(function () {
                $.addClass($wrap[0], 'mfp-on');
              }, 100);
            }
          }
        }
      });
    }

    prepare();

    $.on(box, 'click', _trigger, function (e) {
      var el = e.target;

      // Supports Blazy Grid, Splide/ Slick, GridStack/Mason galleries.
      // @todo add options to avoid guessing.
      var index = $.index(el, ['.box', '.grid', '.field__item', 'li', '.slide']);

      setTimeout(function () {
        _mp = $.magnificPopup.instance;

        if (_mp) {
          _mp.goTo(index);
        }
      });
    });
  }

  function counter(text) {
    return '<div class="mfp-counter">' + text + '</div>';
  }

  function attach(el, op) {
    var $media = $.hasClass(el, 'media') ? el : $.find(el, '.media');
    if ($.isElm($media)) {
      Drupal.detachBehaviors($media);

      if (op) {
        setTimeout(function () {
          Drupal.attachBehaviors($media);

          if (_blazy) {
            _blazy.load($media);
          }
        });
      }
    }
  }

  /**
   * Attaches blazy magnific popup behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyMagnificPopup = {
    attach: function (context) {

      context = $.context(context);

      // Converts jQuery.magnificPopup into dBlazy for consistent vanilla JS.
      if (jQuery && $.isFun(jQuery.fn.magnificPopup) && !$.isFun($.fn.magnificPopup)) {
        var _mfp = jQuery.fn.magnificPopup;

        $.fn.magnificPopup = function (options) {
          var me = $(_mfp.apply(this, arguments));

          if ($.isUnd($.magnificPopup)) {
            $.magnificPopup = jQuery.magnificPopup;
          }

          return me;
        };
      }

      $.once(process, _gallery, context);
    }
  };

}(dBlazy, Drupal));
