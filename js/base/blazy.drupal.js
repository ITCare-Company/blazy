/**
 * @file
 * Provides shared drupal-related methods normally driven by Drupal UI options.
 */

(function ($, Drupal, drupalSettings, _win, _doc) {

  'use strict';

  var _id = 'blazy';
  var _data = 'data';
  var _dataBg = _data + '-b-bg';
  var _loading = 'loading';
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
    windowWidth: 0,
    viewport: {},
    blazySettings: drupalSettings.blazy || {},
    ioSettings: drupalSettings.blazyIo || {},
    revalidate: false,
    options: {},
    clearing: _noop,
    checkResize: _noop,
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

    run: function (opts) {
      // If `No JavaScript` enabled, at least hook into basic IO to DRY.
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
        var elms = $.findAll(cn || _doc, me.selector());

        if (elms.length) {
          $.each(elms, me.update.bind(me));
        }
      }, 100);
    },

    update: function (el, delayed) {
      var me = this;
      var _update = function () {
        if ($.hasAttr(el, _dataBg) && $.isFun($.bg)) {
          $.bg(el, me.winData());
        }
        else {
          if (me.init) {
            me.init.load(el);
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

    isLoaded: function (el) {
      var me = this;
      var opts = me.options;

      // This is only valid when using library where IMG, DIV, etc. onload are
      // taken care properly, not when using Native if `No JavaScript` enabled.
      var success = $.hasClass(el, opts[_successClass]);

      // Refines check for Native, only image/ iframe for now.
      // This was normally taken care of by libraries, until being ditched.
      // @todo iframe may take extremely longer time to load which is not a real
      // issue if using the media player via Media switcher `Image to iframe`.
      if ($.equal(el, ['img', 'iframe']) && !opts.loader) {
        success = $.isLoaded(el) || success;
      }
      return success;
    },

    // Useful to re-calculate image dimensions such as for Masonry.
    onLoaded: function (root, cb, observer) {
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

    isIo: function () {
      var me = this;
      // Will degrade gracefully to old bLazy at Bio initialization.
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
