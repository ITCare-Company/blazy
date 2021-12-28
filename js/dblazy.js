/**
 * @file
 * Cherries by @toddmotto, @cferdinandi, @adamfschwartz, @daniellmb.
 *
 * @todo: Use Cash or Underscore when jQuery is dropped by supported plugins.
 */

/* global define, module */
(function (root, factory) {

  'use strict';

  // Inspired by https://github.com/addyosmani/memoize.js/blob/master/memoize.js
  if (typeof define === 'function' && define.amd) {
    // AMD. Register as an anonymous module.
    define([], factory);
  }
  else if (typeof exports === 'object') {
    // Node. Does not work with strict CommonJS, but only CommonJS-like
    // environments that support module.exports, like Node.
    module.exports = factory();
  }
  else {
    // Browser globals (root is window).
    root.dBlazy = factory();
  }
})(this, function () {

  'use strict';

  /**
   * Object for public APIs where dBlazy stands for drupalBlazy.
   *
   * @namespace
   */
  var dBlazy = {};
  var _oProto = Object.prototype;

  // See https://developer.mozilla.org/en-US/docs/Web/API/Element/closest
  if (!Element.prototype.matches) {
    Element.prototype.matches = Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;
  }

  // The namespaced event holders.
  dBlazy._events = dBlazy._events || {};

  /**
   * Check if the given element matches the selector.
   *
   * @name dBlazy.matches
   *
   * @param {Element} el
   *   The current element.
   * @param {String} selector
   *   Selector to match against (class, ID, data attribute, or tag).
   *
   * @return {Boolean}
   *   Returns true if found, else false.
   *
   * @see http://caniuse.com/#feat=matchesselector
   * @see https://developer.mozilla.org/en-US/docs/Web/API/Element/matches
   */
  dBlazy.matches = function (el, selector) {
    return el && el.matches(selector);
  };

  /**
   * Returns device pixel ratio.
   *
   * @return {Integer}
   *   Returns the device pixel ratio.
   */
  dBlazy.pixelRatio = function () {
    return window.devicePixelRatio || 1;
  };

  /**
   * Returns cross-browser window width.
   *
   * @return {Integer}
   *   Returns the window width.
   */
  dBlazy.windowWidth = function () {
    return window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth || window.screen.width;
  };

  /**
   * Returns cross-browser window width and height.
   *
   * @return {Object}
   *   Returns the window width and height.
   */
  dBlazy.windowSize = function () {
    return {
      width: this.windowWidth,
      height: window.innerHeight
    };
  };

  /**
   * Returns data from the current active window.
   *
   * When being resized, the browser gave no data about pixel ratio from desktop
   * to mobile, not vice versa. Unless delayed for 4s+, not less, which is of
   * course unacceptable.
   *
   * @name dBlazy.activeWidth
   *
   * @param {Object} dataset
   *   The dataset object must be keyed by window width.
   * @param {Boolean} mobileFirst
   *   Whether to use min-width, or max-width.
   *
   * @return {mixed}
   *   Returns data from the current active window.
   */
  dBlazy.activeWidth = function (dataset, mobileFirst) {
    var me = this;
    var keys = Object.keys(dataset);
    var xs = keys[0];
    var xl = keys[keys.length - 1];
    var pr = (me.windowWidth() * me.pixelRatio());
    var ww = mobileFirst ? me.windowWidth() : pr;
    var mw = function (w) {
      // The picture wants <= (approximate), non-picture wants >=, wtf.
      return mobileFirst ? parseInt(w) <= ww : parseInt(w) >= ww;
    };

    var data = keys.filter(mw).map(function (v) {
      return dataset[v];
    })[mobileFirst ? 'pop' : 'shift']();

    return me.isUndefined(data) ? dataset[ww >= xl ? xl : xs] : data;
  };

  /**
   * Check if the HTML tag matches a specified string.
   *
   * @name dBlazy.equal
   *
   * @param {Element} el
   *   The element to compare.
   * @param {String} str
   *   HTML tag to match against.
   *
   * @return {Boolean}
   *   Returns true if matches, else false.
   */
  dBlazy.equal = function (el, str) {
    return el && el.nodeName.toLowerCase() === str;
  };

  /**
   * Get the closest matching element up the DOM tree.
   *
   * Inspired by Chris Ferdinandi, http://github.com/cferdinandi/smooth-scroll.
   *
   * @name dBlazy.closest
   *
   * @param {Element} el
   *   Starting element.
   * @param {String} selector
   *   Selector to match against (class, ID, data attribute, or tag).
   *
   * @return {Element|Null}
   *   Returns null if not match found.
   *
   * @see http://caniuse.com/#feat=element-closest
   * @see http://caniuse.com/#feat=matchesselector
   * @see https://developer.mozilla.org/en-US/docs/Web/API/Element/matches
   */
  dBlazy.closest = function (el, selector) {
    var me = this;
    var parent;
    while (el && !me.isNull(el)) {
      parent = el.parentElement;
      if (me.matches(parent, selector)) {
        return parent;
      }
      el = parent;
    }

    return null;
  };

  /**
   * A simple querySelector wrapper.
   *
   * @name dBlazy.find
   *
   * @param {Element} el
   *   The parent HTML element.
   * @param {String} selector
   *   The CSS selector or HTML tag to query.
   *
   * @return {Element|null}
   *   Null if orphan or not found, else the expected element.
   */
  dBlazy.find = function (el, selector) {
    return this.isNull(el) ? null : el.querySelector(selector);
  };

  /**
   * A simple querySelectorAll wrapper.
   *
   * @name dBlazy.find
   *
   * @param {Element} el
   *   The parent HTML element.
   * @param {String} selector
   *   The CSS selector or HTML tag to query.
   *
   * @return {Array}
   *   Empty array if orphan or not found, else the expected element array.
   */
  dBlazy.findAll = function (el, selector) {
    var me = this;
    return me.isNull(me.find(el, selector)) ? [] : el.querySelectorAll(selector);
  };

  /**
   * Returns a new object after merging two, or more objects.
   *
   * Inspired by @adamfschwartz, @zackbloom, http://youmightnotneedjquery.com.
   *
   * @name dBlazy.extend
   *
   * @param {Object} out
   *   The objects to merge together.
   *
   * @return {Object}
   *   Merged values of defaults and options.
   */
  dBlazy.extend = Object.assign || function (out) {
    out = out || {};

    for (var i = 1, len = arguments.length; i < len; i++) {
      if (!arguments[i]) {
        continue;
      }

      for (var key in arguments[i]) {
        if (_oProto.hasOwnProperty.call(arguments[i], key)) {
          out[key] = arguments[i][key];
        }
      }
    }

    return out;
  };

  /**
   * A simple forEach() implementation for Arrays, Objects and NodeLists.
   *
   * @name dBlazy.forEach
   *
   * @author Todd Motto
   * @link https://github.com/toddmotto/foreach
   *
   * @param {Array|Object|NodeList} collection
   *   Collection of items to iterate.
   * @param {Function} callback
   *   Callback function for each iteration.
   * @param {Array|Object|NodeList} scope
   *   Object/NodeList/Array that forEach is iterating over (aka `this`).
   */
  dBlazy.forEach = function (collection, callback, scope) {
    if (_oProto.toString.call(collection) === '[object Object]') {
      for (var prop in collection) {
        if (_oProto.hasOwnProperty.call(collection, prop)) {
          callback.call(scope, collection[prop], prop, collection);
        }
      }
    }
    else if (collection) {
      for (var i = 0, len = collection.length; i < len; i++) {
        callback.call(scope, collection[i], i, collection);
      }
    }
  };

  /**
   * A simple hasClass wrapper.
   *
   * @name dBlazy.hasClass
   *
   * @param {Element} el
   *   The HTML element.
   * @param {String} name
   *   The class name.
   *
   * @return {bool}
   *   True if the method is supported.
   */
  dBlazy.hasClass = function (el, name) {
    return el && el.classList.contains(name);
  };

  /**
   * A forgiving attribute wrapper with fallback.
   *
   * @name dBlazy.attr
   *
   * @param {Element} el
   *   The HTML element.
   * @param {String} attr
   *   The attr name.
   * @param {String} defValue
   *   The default value.
   * @param {Boolean} withDefault
   *   True if should get with defValue.
   *
   * @return {String}
   *   The attribute value, or fallback, or empty.
   */
  dBlazy.attr = function (el, attr, defValue, withDefault) {
    var me = this;
    if (me.isNull(el)) {
      return '';
    }

    // Since an attribute value must be a string, a null means nullify.
    if (me.isNull(defValue)) {
      el.removeAttribute(attr);
    }
    // Passing a key-value pair object means setting multiple attributes once.
    else if (me.isObject(attr)) {
      me.forEach(attr, function (value, key) {
        el.setAttribute(key, value);
      });
    }
    else {
      // No defValue defined, or withDefault set, means a getter.
      if (me.isUndefined(defValue) || typeof withDefault === 'boolean') {
        defValue = defValue || '';
        return el.hasAttribute(attr) ? el.getAttribute(attr) : defValue;
      }

      // Else a setter.
      el.setAttribute(attr, defValue);
    }

    // For consistency, even if useless.
    return '';
  };

  /**
   * A simple attributes wrapper with values based on data attributes.
   *
   * @name dBlazy.setAttr
   *
   * @param {Element} el
   *   The HTML element.
   * @param {String|Array} attr
   *   The attr name, or string array.
   * @param {Boolean} remove
   *   True if should remove the original/ temporary holder.
   *
   * @return {dBlazy}
   *   The dBlazy object.
   *
   * @todo refactor, or move it out for being too specific with data attributes.
   */
  dBlazy.setAttr = function (el, attr, remove) {
    var me = this;
    if (me.isNull(el)) {
      return me;
    }

    // To accommodate multiple attributes at ease.
    if (me.isArray(attr)) {
      me.forEach(attr, function (value) {
        me.setAttr(el, value, remove);
      });
      return me;
    }

    var dataAttr = 'data-' + attr;
    if (el.hasAttribute(dataAttr)) {
      var value = el.getAttribute(dataAttr);
      if (attr === 'src') {
        el.src = value;
      }
      else {
        el.setAttribute(attr, value);
      }

      if (remove) {
        el.removeAttribute(dataAttr);
      }
    }
    return me;
  };

  /**
   * A simple attributes wrapper, looping based on sources (picture/ video).
   *
   * @name dBlazy.setAttrsWithSources
   *
   * @param {Element} el
   *   The starting HTML element.
   * @param {String} attr
   *   The attr name, can be SRC or SRCSET.
   * @param {Boolean} remove
   *   True if should remove.
   */
  dBlazy.setAttrsWithSources = function (el, attr, remove) {
    var me = this;
    var parent = el.parentNode || null;
    var isPicture = me.equal(parent, 'picture');
    var targets = isPicture ? parent.getElementsByTagName('source') : el.getElementsByTagName('source');

    attr = attr || (isPicture ? 'srcset' : 'src');

    if (targets.length) {
      me.forEach(targets, function (source) {
        me.setAttr(source, attr, remove);
      });
    }
  };

  /**
   * A simple removeAttribute wrapper based on ptional data attributes.
   *
   * @name dBlazy.removeAttrs
   *
   * @param {Element} el
   *   The HTML element.
   * @param {Array} attrs
   *   The attr names.
   * @param {String} prefix
   *   The optional prefix.
   */
  dBlazy.removeAttrs = function (el, attrs, prefix) {
    var me = this;
    if (me.isUndefined(prefix)) {
      prefix = 'data-';
    }
    me.forEach(attrs, function (attr) {
      el.removeAttribute(prefix + attr);
    });
  };

  /**
   * Checks if image is decoded/ completely loaded.
   *
   * @name dBlazy.isDecoded
   *
   * @param {Image} img
   *   The Image object.
   *
   * @return {bool}
   *   True if the image is loaded.
   */
  dBlazy.isDecoded = function (img) {
    if ('decoded' in img) {
      return img.decoded;
    }

    return img.complete;
  };

  /**
   * Decodes the image.
   *
   * @name dBlazy.decode
   *
   * @param {Image} img
   *   The Image object.
   *
   * @return {Promise}
   *   The Promise object.
   */
  dBlazy.decode = function (img) {
    var me = this;

    if (me.isDecoded(img)) {
      return Promise.resolve(img);
    }

    if ('decode' in img) {
      return img.decode();
    }

    return new Promise(function (resolve, reject) {
      img.onload = function () {
        resolve(img);
      };
      img.onerror = reject();
    });
  };

  /**
   * Updates CSS background with multi-breakpoint images.
   *
   * @name dBlazy.updateBg
   *
   * @param {Element} el
   *   The container HTML element.
   * @param {Boolean} mobileFirst
   *   Whether to use min-width or max-width.
   */
  dBlazy.updateBg = function (el, mobileFirst) {
    var me = this;
    var backgrounds = me.parse(el.getAttribute('data-backgrounds'));

    if (backgrounds) {
      var bg = me.activeWidth(backgrounds, mobileFirst);
      if (bg && bg !== 'undefined') {
        el.style.backgroundImage = 'url("' + bg.src + '")';

        // Allows to disable Aspect ratio if it has known/ fixed heights such as
        // gridstack multi-size boxes.
        if (bg.ratio && !el.classList.contains('b-noratio')) {
          el.style.paddingBottom = bg.ratio + '%';
        }
      }
    }
  };

  /**
   * A simple removeChild wrapper.
   *
   * @name dBlazy.remove
   *
   * @param {Element} el
   *   The HTML element to remove.
   */
  dBlazy.remove = function (el) {
    if (el && !this.isNull(el.parentNode)) {
      el.parentNode.removeChild(el);
    }
  };

  /**
   * A simple wrapper for event delegation like jQuery.on().
   *
   * @name dBlazy.on
   *
   * @param {Element} elm
   *   The parent HTML element.
   * @param {String} eventName
   *   The event name to trigger.
   * @param {String} childEl
   *   Child selector to match against (class, ID, data attribute, or tag).
   * @param {Function} callback
   *   The callback function.
   * @param {Object|Boolean} params
   *   The optional param passed into a custom event.
   * @param {Boolean} isCustom
   *   True, if a custom event.
   */
  dBlazy.on = function (elm, eventName, childEl, callback, params, isCustom) {
    onoff.call(this, 'add', elm, eventName, childEl, callback, params, isCustom);
  };

  /**
   * A simple wrapper for event detachment.
   *
   * @name dBlazy.off
   *
   * @param {Element} elm
   *   The parent HTML element.
   * @param {String} eventName
   *   The event name to trigger.
   * @param {String} childEl
   *   Child selector to match against (class, ID, data attribute, or tag).
   * @param {Function} callback
   *   The callback function.
   * @param {Object|Boolean} params
   *   The optional param passed into a custom event.
   * @param {Boolean} isCustom
   *   True, if a custom event.
   */
  dBlazy.off = function (elm, eventName, childEl, callback, params, isCustom) {
    onoff.call(this, 'remove', elm, eventName, childEl, callback, params, isCustom);
  };

  /**
   * A simple wrapper for addEventListener.
   *
   * @name dBlazy.bindEvent
   *
   * @param {Element} el
   *   The HTML element.
   * @param {String} eventName
   *   The event name to remove.
   * @param {Function} fn
   *   The callback function.
   * @param {Object|Boolean} params
   *   The optional param passed into a custom event.
   * @param {Boolean} isCustom
   *   True, if a custom event.
   */
  dBlazy.bindEvent = function (el, eventName, fn, params, isCustom) {
    addRemoveEvent.call(this, 'add', el, eventName, fn, params, isCustom);
  };

  /**
   * A simple wrapper for removeEventListener.
   *
   * @name dBlazy.unbindEvent
   *
   * @param {Element} el
   *   The HTML element.
   * @param {String} eventName
   *   The event name to remove.
   * @param {Function} fn
   *   The callback function.
   * @param {Object} params
   *   The optional param passed into a custom event.
   * @param {Boolean} isCustom
   *   True, if a custom event.
   */
  dBlazy.unbindEvent = function (el, eventName, fn, params, isCustom) {
    addRemoveEvent.call(this, 'remove', el, eventName, fn, params, isCustom);
  };

  /**
   * Executes a function once.
   *
   * @name dBlazy.once
   *
   * @author Daniel Lamb <dlamb.open.source@gmail.com>
   * @link https://github.com/daniellmb/once.js
   *
   * @param {Function} fn
   *   The executed function.
   *
   * @return {Object}
   *   The function result.
   *
   * @tbd deprecated in Blazy 2.5 and will be removed in Blazy 3.0.0. Use the
   * core/once library instead. See https://www.drupal.org/node/3254668.
   */
  dBlazy.once = function (fn) {
    var result;
    var ran = false;
    return function proxy() {
      if (ran) {
        return result;
      }
      ran = true;
      result = fn.apply(this, arguments);
      // For garbage collection.
      fn = null;
      return result;
    };
  };

  /**
   * A simple wrapper for JSON.parse() for string within data-* attributes.
   *
   * @name dBlazy.parse
   *
   * @param {String} str
   *   The string to convert into JSON object.
   *
   * @return {Object}
   *   The JSON object, or empty in case invalid.
   */
  dBlazy.parse = function (str) {
    try {
      return JSON.parse(str);
    }
    catch (e) {
      return {};
    }
  };

  /**
   * A simple wrapper to animate anything using animate.css.
   *
   * @name dBlazy.animate
   *
   * @param {Element} el
   *   The animated HTML element.
   * @param {String} animation
   *   Any custom animation name, fallbacks to [data-animation].
   */
  dBlazy.animate = function (el, animation) {
    var me = this;
    var props = [
      'animation',
      'animation-duration',
      'animation-delay',
      'animation-iteration-count'
    ];

    animation = animation || el.dataset.animation;
    el.classList.add('animated', animation);
    me.forEach(['Duration', 'Delay', 'IterationCount'], function (key) {
      if ('animation' + key in el.dataset) {
        el.style['animation' + key] = el.dataset['animation' + key];
      }
    });

    // Supports both BG and regular image.
    var cn = me.closest(el, '.media');
    cn = me.isNull(cn) ? el : cn;
    var blur = me.find(cn, '.b-blur--tmp');

    function animationEnd() {
      me.removeAttrs(el, props);

      el.classList.add('is-b-animated');
      el.classList.remove('animated', animation);

      me.forEach(props, function (key) {
        el.style.removeProperty(key);
      });

      me.remove(blur);

      me.unbindEvent(el, 'animationend', animationEnd);
    }

    me.bindEvent(el, 'animationend', animationEnd);
  };

  /**
   * Removes common loading indicator classes.
   *
   * @name dBlazy.clearLoading
   *
   * @param {Element} el
   *   The loading HTML element.
   */
  dBlazy.clearLoading = function (el) {
    var me = this;
    // The .b-lazy element can be attached to IMG, or DIV as CSS background.
    // The .(*)loading can be .media, .grid, .slide__content, .box, etc.
    var loaders = [el, me.closest(el, '[class*="loading"]')];

    this.forEach(loaders, function (loader) {
      if (!me.isNull(loader)) {
        loader.className = loader.className.replace(/(\S+)loading/g, '');
      }
    });
  };

  /**
   * A simple wrapper to delay callback function, taken out of blazy library.
   *
   * Alternative to core Drupal.debounce for D7 compatibility, and easy port.
   *
   * @name dBlazy.throttle
   *
   * @param {Function} fn
   *   The callback function.
   * @param {Int} minDelay
   *   The execution delay in milliseconds.
   * @param {Object} scope
   *   The scope of the function to apply to, normally this.
   *
   * @return {Function}
   *   The function executed at the specified minDelay.
   */
  dBlazy.throttle = function (fn, minDelay, scope) {
    var lastCall = 0;
    return function () {
      var now = +new Date();
      if (now - lastCall < minDelay) {
        return;
      }
      lastCall = now;
      fn.apply(scope, arguments);
    };
  };

  /**
   * A simple wrapper to delay callback function on window resize.
   *
   * @name dBlazy.resize
   *
   * @link https://github.com/louisremi/jquery-smartresize
   *
   * @param {Function} c
   *   The callback function.
   * @param {Int} t
   *   The timeout.
   *
   * @return {Function}
   *   The callback function.
   */
  dBlazy.resize = function (c, t) {
    window.onresize = function () {
      window.clearTimeout(t);
      t = window.setTimeout(c, 200);
    };
    return c;
  };

  /**
   * Replaces string occurances to simplify string templating.
   *
   * @name dBlazy.template
   *
   * @link https://stackoverflow.com/questions/1144783
   *
   * @param {String} string
   *   The original source string.
   * @param {Object} map
   *   The mapping object.
   *
   * @return {String}
   *   The modified string.
   *
   * @todo use template string or replaceAll for D10, or D11 at the latest.
   * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Template_literals
   * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/replaceAll
   * @see https://caniuse.com/mdn-javascript_builtins_string_replaceall
   */
  dBlazy.template = function (string, map) {
    for (var key in map) {
      if (_oProto.hasOwnProperty.call(map, key)) {
        string = string.replace(new RegExp(this.escapeRegex('$' + key), 'g'), map[key]);
      }
    }
    return this.trimSpaces(string);
  };

  /**
   * Escapes special (meta) characters.
   *
   * @name dBlazy.escapeRegex
   *
   * @link https://stackoverflow.com/questions/1144783
   *
   * @param {String} string
   *   The original source string.
   *
   * @return {String}
   *   The modified string.
   */
  dBlazy.escapeRegex = function (string) {
    // $& means the whole matched string.
    return string.replace(/[.*+\-?^${}()|[\]\\]/g, '\\$&');
  };

  /**
   * Removes extra spaces so to keep readable template.
   *
   * @name dBlazy.trimSpaces
   *
   * @param {String} string
   *   The original source string.
   *
   * @return {String}
   *   The modified string.
   */
  dBlazy.trimSpaces = function (string) {
    return string.replace(/\\s+/g, ' ').trim();
  };

  /**
   * A simple wrapper for context insanity.
   *
   * Context is unreliable with AJAX contents like product variations, etc.
   * This can be null after Colorbox close, or absurd <script> element, likely
   * arbitrary, etc.
   *
   * @name dBlazy.context
   *
   * @param {HTMLDocument|Element} context
   *   Any element, including weird script element.
   *
   * @return {HTMLDocument|Document}
   *   The HTMLDocument or Document so to avoid failing querySelector, etc.
   */
  dBlazy.context = function (context) {
    // Weirdo: context may be null after Colorbox close.
    context = context || document;

    // jQuery may pass its array as non-expected context identified by length.
    context = 'length' in context ? context[0] : context;
    return context instanceof HTMLDocument ? context : document;
  };

  /**
   * A not simple wrapper for triggering event like jQuery.trigger().
   *
   * @name dBlazy.trigger
   *
   * @param {Element} elm
   *   The HTML element.
   * @param {String} eventName
   *   The event name to trigger.
   * @param {Object} details
   *   The optional detail object passed into a custom event detail property.
   * @param {Object} param
   *   The optional param passed into a custom event.
   *
   * @return {CustomEvent|Event}
   *   The CustomEvent or Event object to dispatch.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/Guide/Events/Creating_and_triggering_events
   * @see https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/dispatchEvent
   * @todo namespaced event name.
   */
  dBlazy.trigger = function (elm, eventName, details, param) {
    var me = this;
    var event;

    if (me.isUndefined(details)) {
      event = new Event(eventName);
    }
    else {
      // Bubbles to be caught by ancestors. Cancelable to preventDefault.
      var data = {
        bubbles: true,
        cancelable: true,
        detail: details || {}
      };

      if (me.isObject(param)) {
        data = me.extend(data, param);
      }

      // IE >= 9 compat, else SCRIPT445: Object doesn't support this action.
      // https://msdn.microsoft.com/library/ff975299(v=vs.85).aspx
      if (me.isFunction(CustomEvent)) {
        event = new CustomEvent(eventName, data);
      }
      else {
        event = document.createEvent('CustomEvent');
        event.initCustomEvent(eventName, true, true, data);
      }
    }

    elm.dispatchEvent(event);
    return event;
  };

  /**
   * Returns true if the subject is a null.
   *
   * @param {Misc} subject
   *   The subject to check for its value.
   *
   * @return {Bool}
   *   True if null.
   */
  dBlazy.isNull = function (subject) {
    return subject === null;
  };

  /**
   * Returns true if the subject is an array.
   *
   * One of the weird behavior in JavaScript is the typeof Array is Object.
   *
   * @param {Misc} subject
   *   The subject to check for its value.
   *
   * @return {Bool}
   *   True if subject is an instanceof Array.
   */
  dBlazy.isArray = function (subject) {
    return !this.isNull(subject) && Array.isArray(subject);
  };

  /**
   * Returns true if the subject is a function.
   *
   * @param {Misc} subject
   *   The subject to check for its value.
   *
   * @return {Bool}
   *   True if subject is an instanceof Function.
   */
  dBlazy.isFunction = function (subject) {
    return typeof subject === 'function';
  };

  /**
   * Returns true if the subject is an object.
   *
   * @param {Misc} subject
   *   The subject to check for its value.
   *
   * @return {Bool}
   *   True if subject is an instanceof Object.
   */
  dBlazy.isObject = function (subject) {
    return !this.isNull(subject) && typeof subject === 'object';
  };

  /**
   * Returns true if the subject is object.
   *
   * @param {Misc} subject
   *   The subject to check for its value.
   *
   * @return {Bool}
   *   True if subject is undefined.
   */
  dBlazy.isUndefined = function (subject) {
    return typeof subject === 'undefined';
  };

  /**
   * A simple attributes wrapper looping based on the given attributes.
   *
   * @name dBlazy.setAttrs
   *
   * @param {Element} el
   *   The HTML element.
   * @param {Array} attrs
   *   The attr names.
   * @param {Boolean} remove
   *   True if should remove.
   *
   * @deprecated at 2.5. Use ::setAttr with parameter as array instead.
   */
  dBlazy.setAttrs = function (el, attrs, remove) {
    var me = this;

    me.forEach(attrs, function (value) {
      me.setAttr(el, value, remove);
    });
  };


  /**
   * A simple wrapper for the namespaced [add|remove]EventListener.
   *
   * @param {String} op
   *   Whether to add or remove the event.
   * @param {Element} el
   *   The HTML element.
   * @param {String} eventName
   *   The event name, optionally namespaced, to add or remove.
   * @param {Function} fn
   *   The callback function.
   * @param {Object|Boolean} params
   *   The optional param passed into a custom event.
   * @param {Boolean} isCustom
   *   True, if a custom event.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener
   */
  function addRemoveEvent(op, el, eventName, fn, params, isCustom) {
    var me = this;
    if (el === null) {
      return;
    }

    var defaults = {
      capture: false,
      passive: true
    };

    var options = params || false;
    if (me.isObject(params)) {
      options = me.extend(defaults, params);
    }

    var onEvent = function (e) {
      isCustom = isCustom || e.indexOf('blazy.') === 0 || e.indexOf('bio.') === 0;
      var add = op === 'add';
      var ev = isCustom ? e : e.split('.')[0];
      fn = fn || me._events[e];

      if (me.isFunction(fn)) {
        el[op + 'EventListener'](ev.trim(), fn, options);
      }

      if (add) {
        me._events[e] = fn;
      }
      else {
        delete me._events[e];
      }
    };

    me.forEach(eventName.split(' '), onEvent);
  }

  /**
   * A simple wrapper for event delegation like jQuery.on().
   *
   * Inspired by http://stackoverflow.com/questions/30880757/
   * javascript-equivalent-to-on.
   *
   * @param {String} op
   *   Whether to add or remove the event.
   * @param {Element} elm
   *   The parent HTML element.
   * @param {String} eventName
   *   The optionally namespaced event name to trigger.
   * @param {String} childEl
   *   Child selector to match against (class, ID, data attribute, or tag).
   * @param {Function} callback
   *   The callback function.
   * @param {Object|Boolean} params
   *   The optional param passed into a custom event.
   * @param {Boolean} isCustom
   *   True, if a custom event.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener
   * @fixme failed for future elements.
   */
  function onoff(op, elm, eventName, childEl, callback, params, isCustom) {
    params = params || {capture: true, passive: false};

    var me = this;
    var onEvent = function (e) {
      var t = e.target;
      while (t && t !== this) {
        if (me.matches(t, childEl)) {
          callback.call(t, e);
          return;
        }
        t = t.parentElement;
      }
    };

    addRemoveEvent.call(me, op, elm, eventName, onEvent, params, isCustom);
  }

  return dBlazy;

});
