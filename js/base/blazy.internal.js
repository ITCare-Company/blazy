/**
 * @file
 * Provides non-reusable methods due to being too specific for Blazy.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */

(function ($) {

  'use strict';

  /**
   * Updates CSS background with multi-breakpoint images.
   *
   * @param {Element} el
   *   The container HTML element.
   * @param {Bool} mobileFirst
   *   Whether to use min-width or max-width.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function bg(el, mobileFirst) {
    var data = $.parse($.attr(el, 'data-b-bg'));

    if (data) {
      var _bg = $.activeWidth(data, mobileFirst);
      var _style = el.style;
      if (_bg && _bg !== 'undefined') {
        var _ratio = _bg.ratio;
        _style.backgroundImage = 'url("' + _bg.src + '")';

        // Allows to disable Aspect ratio if it has known/ fixed heights such as
        // gridstack multi-size boxes.
        if (_ratio && !$.hasClass(el, 'b-noratio')) {
          _style.paddingBottom = _ratio + '%';
        }
      }
    }
    return this;
  }

  $.bg = bg;
  $.fn.bg = function (mobileFirst) {
    var me = this;
    return me.chain(function (el) {
      return bg.call(me, el, mobileFirst);
    });
  };

  /**
   * Removes common loading indicator classes.
   *
   * @param {Element} el
   *   The loading HTML element.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function unloading(el) {
    var _loading = 'loading';
    // The .b-lazy element can be attached to IMG, or DIV as CSS background.
    // The .(*)loading can be .media, .grid, .slide__content, .box, etc.
    var loaders = [el, $.closest(el, '[class*="' + _loading + '"]')];

    $.each(loaders, function (loader) {
      if ($.isElm(loader)) {
        var name = loader.className;
        if ($.contains(name, _loading)) {
          loader.className = name.replace(/(\S+)loading/g, '');
        }
      }
    });
    return this;
  }

  $.unloading = unloading;
  $.fn.unloading = function () {
    return this.chain(unloading.bind(this));
  };

  /**
   * Map attributes from data-BLAH to BLAH, and remove data-BLAH if so required.
   *
   * @param {Element} el
   *   The element.
   * @param {String|Array} attr
   *   The attr name, or string array.
   * @param {Bool} remove
   *   True if should remove the original/ temporary holder.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function mapAttr(el, attr, remove) {
    var _mapAttr = function (name) {
      var dataAttr = 'data-' + name;

      if ($.hasAttr(el, dataAttr)) {
        var value = $.attr(el, dataAttr);
        $.attr(el, name, value);

        if (remove) {
          $.removeAttr(el, dataAttr);
        }
      }
    };

    if ($.isArr(attr)) {
      $.each(attr, _mapAttr);
    }
    else {
      _mapAttr(attr);
    }
    return this;
  }

  $.mapAttr = mapAttr;
  $.fn.mapAttr = function (attr, remove) {
    var me = this;
    return me.chain(function (el) {
      return mapAttr.call(me, el, attr, remove);
    });
  };

  /**
   * A simple attributes wrapper, looping based on sources (picture/ video).
   *
   * @param {Element} el
   *   The element.
   * @param {String} attr
   *   The attr name, can be SRC or SRCSET.
   * @param {Bool} remove
   *   True if should remove.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function mapSource(el, attr, remove) {
    if ($.isElm(el)) {
      var parent = el.parentNode;
      var isPicture = $.equal(parent, 'picture');
      var elms = (isPicture ? parent : el).getElementsByTagName('source');

      attr = attr || (isPicture ? 'srcset' : 'src');
      if (elms.length) {
        $(elms).mapAttr(attr, remove);
      }
    }
    return this;
  }

  $.mapSource = mapSource;
  $.fn.mapSource = function (attr, remove) {
    var me = this;
    return me.chain(function (el) {
      return mapSource.call(me, el, attr, remove);
    });
  };

})(dBlazy);
