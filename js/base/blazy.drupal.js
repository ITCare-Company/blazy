/**
 * @file
 * Provides shared drupal-related methods normally driven by Drupal UI options.
 */

(function ($, Drupal, drupalSettings, _win, _doc) {

  'use strict';

  var _id = 'blazy';
  var _data = 'data';
  var _dataBg = _data + '-b-bg';
  var _dataDimensions = _data + '-dimensions';
  var _elBlur = '.b-blur';
  var _successClass = 'successClass';
  var _eventDone = _id + '.done';
  var _noop = function () {};
  var _extensions = {};

  /**
   * Blazy public methods.
   *
   * @namespace
   */
  Drupal.blazy = {
    context: _doc,
    init: null,
    instances: [],
    items: [],
    resizeTick: 0,
    blazySettings: drupalSettings.blazy || {},
    ioSettings: drupalSettings.blazyIo || {},
    options: {},
    clearCompat: _noop,
    clearScript: _noop,
    checkResize: _noop,
    revalidate: _noop,
    mapAttr: _noop,
    onIntersecting: _noop,
    updateRatio: _noop,
    winData: _noop,
    extend: function (plugins) {
      _extensions = $.extend({}, _extensions, plugins);
    },
    globals: function () {
      var me = this;
      var commons = {
        success: me.clearing.bind(me),
        error: me.clearing.bind(me),
        selector: '.b-lazy',
        errorClass: 'b-error',
        successClass: 'b-loaded'
      };

      return $.extend(me.blazySettings, me.ioSettings, commons);
    },

    clearing: function (el) {
      var me = this;
      var ie = $.hasClass(el, 'b-responsive') && $.hasAttr(el, _data + '-pfsrc');

      // Clear loading classes. Also supports future delayed Native loading.
      if ($.isFun($.unloading)) {
        $.unloading(el);
      }

      // Provides event listeners for easy overrides without full overrides.
      // Runs before native to allow native use this on its own onload event.
      $.trigger(el, _eventDone, {
        options: me.options
      });

      // With `No JavaScript` on, facilitate both parties: native vs. script.
      // This is to use the same clearing approach for all parties.
      me.clearCompat(el);
      me.clearScript(el);

      // @see http://scottjehl.github.io/picturefill/
      if (_win.picturefill && ie) {
        _win.picturefill({
          reevaluate: true,
          elements: [el]
        });
      }
    },

    run: function (opts) {
      // If `No JavaScript` enabled, at least hook into core IO to DRY.
      if (!opts.loader) {
        return new Bio(opts);
      }

      // Else regular lazyloader scripts with data-[SRC|SRCSET] to support IEs.
      return this.isIo() ? new BioMedia(opts) : new Blazy(opts);
    },

    mount: function (exe) {
      var me = this;

      // This may be set by lazyload script, but not when `No JavaScript` off.
      me.options = $.extend(me.globals(), me.options);

      // Executes all extensions.
      if (exe) {
        $.each(_extensions, function (fn) {
          if ($.isFun(fn)) {
            fn.call(me);
          }
        });
      }

      return $.extend(me, _extensions);
    },

    selector: function (suffix) {
      suffix = suffix || '';
      var opts = this.options;
      return opts.selector + suffix + ':not(.' + opts[_successClass] + ')';
    },

    // Only do this to fix errors, revalidation.
    load: function (cn) {
      var me = this;

      // DOM ready fix.
      _win.setTimeout(function () {
        // @todo filterout the failing ones.
        var elms = $.findAll(cn || _doc, me.selector());

        if (elms.length) {
          $.each(elms, me.update.bind(me));
        }
      }, 100);
    },

    update: function (el, delayed, winData) {
      var me = this;
      var _update = function () {
        if ($.hasAttr(el, _dataBg) && $.isFun($.bg)) {
          $.bg(el, winData || me.winData());
        }
        else {
          if (me.init) {
            if ($.hasClass(el, 'media')) {
              el = $.find(el, '.b-lazy') || el;
            }
            me.init.load(el, true);
          }
        }
      };

      delayed = delayed || false;
      if (delayed) {
        // DOM ready fix.
        _win.setTimeout(_update, 100);
      }
      else {
        _update();
      }
    },

    // Useful to re-calculate image dimensions such as for Masonry.
    rebind: function (root, cb, observer) {
      var me = this;
      var elms = $.findAll(root, me.options.selector + ':not(' + _elBlur + ')');
      var isMe = elms.length;

      if (!isMe) {
        elms = $.findAll(root, 'img:not(' + _elBlur + ')');
      }

      if (elms.length) {
        $.each(elms, function (el) {
          var type = isMe ? _eventDone : 'load';
          $.one(el, type, cb, isMe);

          if (observer) {
            observer.observe(el);
          }
        });
      }
    },

    isFluid: function (el, cn) {
      return $.equal(el.parentNode, 'picture') && $.hasAttr(cn, _dataDimensions);
    },

    isLoaded: function (el) {
      return $.hasClass(el, this.options[_successClass]);
    },

    isIo: function () {
      var me = this;
      return me.ioSettings && me.ioSettings.enabled;
    },

    isBlazy: function () {
      return !this.isIo() && 'Blazy' in _win;
    }

  };

  function _debounce(cb, scope) {
    Drupal.debounce(cb.bind(scope), 201, true);
  }

  $.debounce = _debounce;

}(dBlazy, Drupal, drupalSettings, this, this.document));
