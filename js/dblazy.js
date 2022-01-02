/**
 * @file
 * Cherries by @toddmotto, @cferdinandi, @adamfschwartz, @daniellmb.
 *
 * Some dup wrappers are meant to DRY with null checks aka poorman null safety.
 *
 * @todo use Cash or Underscore when jQuery is dropped by supported plugins.
 */

/**
 * Provides a few disposable polyfills till IE is gone from planet earth.
 *
 * @todo remove a few when min D9.2+ since they are included as core polyfills
 * and can be made dependencies instead. Unless by then, IE is already gone, and
 * core deprecates them like classList.
 * @todo remove for core/drupal.customevent when min D9.3.
 * @todo remove for core/drupal.element.closest|matches when min D9.2.
 * @see https://www.drupal.org/node/3243406
 * @see https://www.drupal.org/node/3159731
 * @see https://www.drupal.org/node/3211146
 * @see https://www.drupal.org/node/3079238
 *
 */
(function (_win) {

  'use strict';

  var _eProto = Element.prototype;
  var _sProto = String.prototype;

  // See https://developer.mozilla.org/en-US/docs/Web/API/Element/closest
  if (!_eProto.matches) {
    _eProto.matches = _eProto.msMatchesSelector || _eProto.webkitMatchesSelector;
  }

  // https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/startsWith
  if (!_sProto.startsWith) {
    Object.defineProperty(_sProto, 'startsWith', {
      value: function (search, rawPos) {
        var pos = rawPos > 0 ? rawPos | 0 : 0;
        return this.substring(pos, pos + search.length) === search;
      }
    });
  }

  // IE >= 9 compat, else SCRIPT445: Object doesn't support this action.
  // @see https://msdn.microsoft.com/library/ff975299(v=vs.85).aspx.
  if (typeof _win.CustomEvent === 'function') {
    return false;
  }

  function CustomEvent(event, params) {
    params = params || {
      bubbles: false,
      cancelable: false,
      detail: null
    };
    var evt = document.createEvent('CustomEvent');
    evt.initCustomEvent(event, params.bubbles, params.cancelable, params.detail);
    return evt;
  }

  CustomEvent.prototype = _win.Event.prototype;
  _win.CustomEvent = CustomEvent;

})(this);

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
  var _win = this;
  var _doc = _win.document;
  var _oProto = Object.prototype;
  var _src = 'src';
  var _add = 'add';
  var _remove = 'remove';

  // The namespaced event holders.
  var _events = {};

  /**
   * A forgiving attribute wrapper with fallback mimicking jQuery.attr method.
   *
   * @private
   *
   * @param {Element} el
   *   The HTML element.
   * @param {String|Object} attr
   *   The attr name, can be a string or object.
   * @param {String} defValue
   *   The default value, can be null or undefined for different intentions.
   * @param {Boolean} withDefault
   *   True if should get with defValue.
   *
   * @return {String}
   *   The attribute value, or fallback, for getters, or empty for setters.
   */
  function _attr(el, attr, defValue, withDefault) {
    if (isNull(el)) {
      return '';
    }

    // Passing a key-value pair object means setting multiple attributes once.
    if (isObject(attr)) {
      forEach(attr, function (value, key) {
        el.setAttribute(key, value);
      });
    }
    // Since an attribute value null makes no sense, assumes nullify.
    else if (isNull(defValue)) {
      el.removeAttribute(attr);
    }
    else {
      // No defValue defined, or withDefault set, means a getter.
      var _undefined = isUndefined(defValue);
      if (_undefined || typeof withDefault === 'boolean') {
        if (_undefined) {
          defValue = '';
        }
        return hasAttr(el, attr) ? el.getAttribute(attr) : defValue;
      }

      // Else a setter.
      if (attr === _src) {
        // To minimize unnecessary mutations.
        el.src = defValue;
      }
      else {
        el.setAttribute(attr, defValue);
      }
    }

    // For consistency, even if useless.
    return '';
  }

  /**
   * Checks if the element has attribute.
   *
   * @private
   *
   * @param {Element} el
   *   The HTML element.
   * @param {String} attribute
   *   The attribute name.
   *
   * @return {bool}
   *   True if it has the attribute.
   */
  function hasAttr(el, attribute) {
    return el && el.hasAttribute(attribute);
  }

  /**
   * A simple attributes wrapper with values based on data attributes.
   *
   * @private
   *
   * @param {Element} el
   *   The HTML element.
   * @param {String|Array} attr
   *   The attr name, or string array.
   * @param {Boolean} remove
   *   True if should remove the original/ temporary holder.
   */
  function setAttr(el, attr, remove) {
    if (isNull(el)) {
      return;
    }

    // To accommodate multiple attributes at ease.
    if (isArray(attr)) {
      forEach(attr, function (value) {
        setAttr(el, value, remove);
      });
      return;
    }

    var dataAttr = 'data-' + attr;
    if (hasAttr(el, dataAttr)) {
      var value = _attr(el, dataAttr);
      _attr(el, attr, value);

      if (remove) {
        _attr(el, dataAttr, null);
      }
    }
  }

  /**
   * A simple attributes wrapper, looping based on sources (picture/ video).
   *
   * @private
   *
   * @param {Element} el
   *   The starting HTML element.
   * @param {String} attr
   *   The attr name, can be SRC or SRCSET.
   * @param {Boolean} remove
   *   True if should remove.
   */
  function setAttrsWithSources(el, attr, remove) {
    var parent = el.parentNode;
    var _source = 'source';
    var isPicture = equal(parent, 'picture');
    var targets = (isPicture ? parent : el).getElementsByTagName(_source);

    attr = attr || (isPicture ? 'srcset' : _src);

    if (targets.length) {
      forEach(targets, function (source) {
        setAttr(source, attr, remove);
      });
    }
  }

  /**
   * A simple removeAttribute wrapper based on optional data attributes.
   *
   * @private
   *
   * @param {Element} el
   *   The HTML element.
   * @param {Array} attrs
   *   The attr names.
   * @param {String} prefix
   *   The optional prefix.
   */
  function removeAttrs(el, attrs, prefix) {
    if (isUndefined(prefix)) {
      prefix = 'data-';
    }
    forEach(attrs, function (attr) {
      _attr(el, prefix + attr, null);
    });
  }

  /**
   * Checks if the element has a class name.
   *
   * @private
   *
   * @param {Element} el
   *   The HTML element.
   * @param {String} name
   *   The class name, can be space-delimited for multiple names.
   *
   * @return {bool}
   *   True if it has the class name.
   */
  function hasClass(el, name) {
    var found = 0;
    var _list = el.classList;

    if (el && _list) {
      forEach(name.split(' '), function (item) {
        if (_list.contains(item)) {
          found++;
        }
      });
    }

    return found > 0;
  }

  /**
   * Adds a class, or space-delimited class names to an element.
   *
   * @private
   *
   * @param {Element} els
   *   The HTML element, can be many.
   * @param {String} name
   *   The class name, or space-delimited class names.
   */
  function addClass(els, name) {
    addRemoveClass(_add, els, name);
  }

  /**
   * Removes a class, or multiple from an element.
   *
   * @private
   *
   * @param {Element} els
   *   The HTML element, can be many.
   * @param {String} name
   *   The class name, or space-delimited class names.
   */
  function removeClass(els, name) {
    addRemoveClass(_remove, els, name);
  }

  /**
   * Toggles a class, or multiple from an element.
   *
   * @private
   *
   * @param {Element} el
   *   The HTML element.
   * @param {String} name
   *   The class name, or space-delimited class names.
   */
  function toggleClass(el, name) {
    var _list = el.classList;
    if (el && _list) {
      name.split(' ').map(function (value) {
        _list.toggle(value);
      });
    }
  }

  /**
   * Checks if a string contains substring(s) (ES6 ::includes), only for oldies.
   *
   * @private
   *
   * Cannot use [].every() since it not about all or nothing.
   *
   * @param {String} str
   *   The source string to test for.
   * @param {String} substr
   *   The target sub-string to check for, can be a string array.
   *
   * @return {bool}
   *   True if it has the needle.
   *
   * @todo use polyfill core/drupal.string.includes when min D9.3.
   */
  function contains(str, substr) {
    var found = 0;

    if (str.length) {
      forEach(toArray(substr), function (value) {
        if (str.indexOf(value) !== -1) {
          found++;
        }
      });
    }

    return found > 0;
  }

  /**
   * Escapes special (meta) characters.
   *
   * @private
   *
   * @link https://stackoverflow.com/questions/1144783
   *
   * @param {String} string
   *   The original source string.
   *
   * @return {String}
   *   The modified string.
   */
  function escapeRegex(string) {
    // $& means the whole matched string.
    return string.replace(/[.*+\-?^${}()|[\]\\]/g, '\\$&');
  }

  /**
   * Check if the given element matches the selector.
   *
   * @private
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
  function matches(el, selector) {
    return el && el.matches(selector);
  }

  /**
   * Checks whether or not a string begins with another string, case-sensitive.
   *
   * @private
   *
   * @param {String} str
   *   The source string to test for.
   * @param {String} substr
   *   The target sub-string to check for, can be a string array.
   *
   * @return {bool}
   *   True if it starts with the needle.
   */
  function startsWith(str, substr) {
    var found = 0;

    if (str.length) {
      forEach(toArray(substr), function (value) {
        if (str.startsWith(value)) {
          found++;
        }
      });
    }

    return found > 0;
  }

  /**
   * Removes extra spaces so to keep readable template.
   *
   * @private
   *
   * @param {String} string
   *   The original source string.
   *
   * @return {String}
   *   The modified string.
   */
  function trimSpaces(string) {
    return string.replace(/\\s+/g, ' ').trim();
  }

  /**
   * Get the closest matching element up the DOM tree.
   *
   * @private
   *
   * Inspired by Chris Ferdinandi, http://github.com/cferdinandi/smooth-scroll.
   *
   * @param {Element} el
   *   Starting element.
   * @param {String} selector
   *   Selector to match against (class, ID, data attribute, or tag).
   *
   * @return {Element|Null}
   *   Returns null if not match found.
   *
   * @todo remove when min D9.2 for drupal.element.closest|matches.
   * @see https://www.drupal.org/node/3159731
   * @see https://www.drupal.org/node/3211146
   * @see http://caniuse.com/#feat=element-closest
   * @see http://caniuse.com/#feat=matchesselector
   * @see https://developer.mozilla.org/en-US/docs/Web/API/Element/matches
   * @see https://developer.mozilla.org/en-US/docs/Web/API/Node/nodeType
   */
  function closest(el, selector) {
    var parent;
    while (el && el.nodeType === 1) {
      parent = el.parentElement;
      if (matches(parent, selector)) {
        return parent;
      }
      el = parent;
    }

    return null;
  }

  /**
   * Check if the HTML tag matches a specified string.
   *
   * @private
   *
   * @param {Element} el
   *   The element to compare.
   * @param {String} str
   *   HTML tag to match against.
   *
   * @return {Boolean}
   *   Returns true if matches, else false.
   */
  function equal(el, str) {
    return el && el.nodeName.toLowerCase() === str.toLowerCase();
  }

  /**
   * A simple querySelector wrapper.
   *
   * @private
   *
   * Cannot use _doc as fallback to avoid complication with a particular child.
   *
   * @param {Element} el
   *   The parent HTML element.
   * @param {String} selector
   *   The CSS selector or HTML tag to query.
   *
   * @return {Element|null}
   *   Null if orphan or not found, else the expected element.
   *
   * @todo decide if to return array instead like jQuery for consistency.
   */
  function find(el, selector) {
    return !selector || isNull(el) ? null : el.querySelector(selector);
  }

  /**
   * A simple querySelectorAll wrapper.
   *
   * @private
   *
   * @param {Element} el
   *   The parent HTML element.
   * @param {String} selector
   *   The CSS selector or HTML tag to query.
   *
   * @return {Array.<Element>}
   *   Empty array if orphan or not found, else the expected element array.
   *
   * @todo remove if ::find() returns an array.
   */
  function findAll(el, selector) {
    return !selector || isNull(el) ? [] : getElements(selector, el);
  }

  /**
   * A simple removeChild wrapper.
   *
   * @private
   *
   * @param {Element} el
   *   The HTML element to remove.
   */
  function remove(el) {
    var parent = el.parentNode;
    if (el && parent) {
      parent.removeChild(el);
    }
  }

  /**
   * Returns a new object after merging two, or more objects.
   *
   * @private
   *
   * Inspired by @adamfschwartz, @zackbloom, http://youmightnotneedjquery.com.
   *
   * @param {Object} out
   *   The objects to merge together.
   *
   * @return {Object}
   *   Merged values of defaults and options.
   *
   * @todo refactor or remove when min D9.0 for core/drupal.object.assign.
   * @see https://www.drupal.org/node/3113447
   */
  var extend = Object.assign || function (out) {
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
   * @private
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
   *
   * @todo drop for native [].forEach post D10+ when IE gone from planet earth.
   */
  function forEach(collection, callback, scope) {
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
  }

  /**
   * A simple wrapper for JSON.parse() for string within data-* attributes.
   *
   * @private
   *
   * @param {String} str
   *   The string to convert into JSON object.
   *
   * @return {Object}
   *   The JSON object, or empty in case invalid.
   */
  function parse(str) {
    try {
      return str.length === 0 || str === '1' ? {} : JSON.parse(str);
    }
    catch (e) {
      return {};
    }
  }

  /**
   * Converts string/ element to array.
   *
   * @private
   *
   * @param {Element|String} subject
   *   The object to make array.
   *
   * @return {Array}
   *   The fresulting array.
   */
  function toArray(subject) {
    return isArray(subject) ? subject : [subject];
  }

  /**
   * Returns true if the subject is a null.
   *
   * @private
   *
   * @param {mixed} subject
   *   The subject to check for its truthy.
   *
   * @return {Bool}
   *   True if null.
   */
  function isNull(subject) {
    return subject === null;
  }

  /**
   * Returns true if the subject is an array.
   *
   * @private
   *
   * One of the weird behaviors in JavaScript is the typeof Array is Object.
   *
   * @param {Misc} subject
   *   The subject to check for its truthy.
   *
   * @return {Bool}
   *   True if subject is an instanceof Array.
   */
  function isArray(subject) {
    return !isNull(subject) && Array.isArray(subject);
  }

  /**
   * Returns true if the subject is an Element.
   *
   * @private
   *
   * @param {Misc} subject
   *   The subject to check for its truthy.
   *
   * @return {Bool}
   *   True if subject is an instanceof Element.
   */
  function isElement(subject) {
    return subject instanceof Element;
  }

  /**
   * Returns true if the subject is a function.
   *
   * @private
   *
   * @param {Misc} subject
   *   The subject to check for its truthy.
   *
   * @return {Bool}
   *   True if subject is an instanceof Function.
   */
  function isFunction(subject) {
    return typeof subject === 'function';
  }

  /**
   * Returns true if the subject is an object.
   *
   * @private
   *
   * @param {Misc} subject
   *   The subject to check for its truthy.
   *
   * @return {Bool}
   *   True if subject is an instanceof Object.
   */
  function isObject(subject) {
    return !isNull(subject) && typeof subject === 'object';
  }

  /**
   * Returns true if the subject is undefined.
   *
   * @private
   *
   * @param {Misc} subject
   *   The subject to check for its truthy.
   *
   * @return {Bool}
   *   True if subject is undefined.
   */
  function isUndefined(subject) {
    return typeof subject === 'undefined';
  }

  /**
   * Returns device pixel ratio.
   *
   * @private
   *
   * @return {Integer}
   *   Returns the device pixel ratio.
   */
  function pixelRatio() {
    return _win.devicePixelRatio || 1;
  }

  /**
   * Returns cross-browser window width.
   *
   * @private
   *
   * @return {Integer}
   *   Returns the window width.
   */
  function windowWidth() {
    return _win.innerWidth || _doc.documentElement.clientWidth || _doc.body.clientWidth || _win.screen.width;
  }

  /**
   * Returns cross-browser window width and height.
   *
   * @private
   *
   * @return {Object}
   *   Returns the window width and height.
   */
  function windowSize() {
    return {
      width: windowWidth(),
      height: _win.innerHeight
    };
  }

  /**
   * Returns data from the current active window.
   *
   * @private
   *
   * When being resized, the browser gave no data about pixel ratio from desktop
   * to mobile, not vice versa. Unless delayed for 4s+, not less, which is of
   * course unacceptable.
   *
   * @param {Object} dataset
   *   The dataset object must be keyed by window width.
   * @param {Boolean} mobileFirst
   *   Whether to use min-width, or max-width.
   *
   * @return {mixed}
   *   Returns data from the current active window.
   */
  function activeWidth(dataset, mobileFirst) {
    var keys = Object.keys(dataset);
    var xs = keys[0];
    var xl = keys[keys.length - 1];
    var pr = (windowWidth() * pixelRatio());
    var ww = mobileFirst ? windowWidth() : pr;
    var mw = function (w) {
      // The picture wants <= (approximate), non-picture wants >=, wtf.
      return mobileFirst ? parseInt(w, 10) <= ww : parseInt(w, 10) >= ww;
    };

    var data = keys.filter(mw).map(function (v) {
      return dataset[v];
    })[mobileFirst ? 'pop' : 'shift']();

    return isUndefined(data) ? dataset[ww >= xl ? xl : xs] : data;
  }

  /**
   * A simple wrapper for event delegation like jQuery.on().
   *
   * @private
   *
   * @param {Element} elm
   *   The parent HTML element.
   * @param {String} eventName
   *   The event name to trigger.
   * @param {String} selector
   *   Child selector to match against (class, ID, data attribute, or tag).
   * @param {Function} callback
   *   The callback function.
   * @param {Object|Boolean} params
   *   The optional param passed into a custom event.
   * @param {Boolean} isCustom
   *   True, if a custom event.
   */
  function on(elm, eventName, selector, callback, params, isCustom) {
    onoff(_add, elm, eventName, selector, callback, params, isCustom);
  }

  /**
   * A simple wrapper for event detachment.
   *
   * @private
   *
   * @param {Element} elm
   *   The parent HTML element.
   * @param {String} eventName
   *   The event name to trigger.
   * @param {String} selector
   *   Child selector to match against (class, ID, data attribute, or tag).
   * @param {Function} callback
   *   The callback function.
   * @param {Object|Boolean} params
   *   The optional param passed into a custom event.
   * @param {Boolean} isCustom
   *   True, if a custom event.
   */
  function off(elm, eventName, selector, callback, params, isCustom) {
    onoff(_remove, elm, eventName, selector, callback, params, isCustom);
  }

  /**
   * A simple wrapper for addEventListener.
   *
   * @private
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
  function bindEvent(el, eventName, fn, params, isCustom) {
    addRemoveEvent(_add, el, eventName, fn, params, isCustom);
  }

  /**
   * A simple wrapper for removeEventListener.
   *
   * @private
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
  function unbindEvent(el, eventName, fn, params, isCustom) {
    addRemoveEvent(_remove, el, eventName, fn, params, isCustom);
  }

  /**
   * Checks if image is decoded/ completely loaded.
   *
   * @private
   *
   * @param {Image} img
   *   The Image object.
   *
   * @return {bool}
   *   True if the image is loaded.
   */
  function isDecoded(img) {
    if ('decoded' in img) {
      return img.decoded;
    }

    return img.complete;
  }

  /**
   * Executes the function once.
   *
   * @private
   *
   * @param {Function} fn
   *   The executed function.
   *
   * @return {Object}
   *   The function result.
   */
  function _once(fn) {
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
  }

  /**
   * Process arguments, query the DOM if necessary. Adapted from core/once.
   *
   * @private
   *
   * @param {NodeList|Array.<Element>|Element|string} selector
   *   A NodeList, array of elements, or string.
   * @param {Document|Element} [context=document]
   *   An element to use as context for querySelectorAll.
   *
   * @return {Array.<Element>}
   *   An array of elements to process.
   */
  function getElements(selector, context) {
    // Assume selector is an array-like element.
    var elements = toArray(selector);

    if (typeof selector === 'string') {
      elements = context.querySelectorAll(selector);
    }

    // Ensures an array is returned and not a NodeList or an Array-like object.
    // https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array/from
    return Array.prototype.slice.call(elements);
  }

  /**
   * A wrapper for the classList to mimick the familiar jQuery like methods.
   *
   * @private
   *
   * @param {String} op
   *   Whether to add or remove the class.
   * @param {Element} els
   *   The HTML element, can be many.
   * @param {String} name
   *   The class name, or space-delimited class names.
   */
  function addRemoveClass(op, els, name) {
    var names = name.split(' ');
    var _apply = function (elm) {
      var list = elm.classList;
      if (list) {
        list[op].apply(list, names);
      }
    };

    if (els) {
      forEach(toArray(els), _apply);
    }
  }

  /**
   * A simple wrapper for the namespaced [add|remove]EventListener.
   *
   * @private
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
   *   Like namespaced, but not to be namespaced since LHS is not any event.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener
   */
  function addRemoveEvent(op, el, eventName, fn, params, isCustom) {
    if (el === null) {
      return;
    }

    var defaults = {
      capture: false,
      passive: true
    };

    var options = params || false;
    if (isObject(params)) {
      options = extend(defaults, params);
    }

    var onEvent = function (e) {
      isCustom = isCustom || startsWith(e, ['blazy.', 'bio.']);
      var add = op === _add;
      var ev = isCustom ? e : e.split('.')[0];
      fn = fn || _events[e];

      if (isFunction(fn)) {
        el[op + 'EventListener'](ev.trim(), fn, options);
      }

      if (add) {
        _events[e] = fn;
      }
      else {
        delete _events[e];
      }
    };

    forEach(eventName.split(' '), onEvent);
  }

  /**
   * A simple wrapper for event delegation like jQuery.on().
   *
   * @private
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
   * @param {String} selector
   *   Child selector to match against (class, ID, data attribute, or tag).
   * @param {Function} callback
   *   The callback function.
   * @param {Object|Boolean} params
   *   The optional param passed into a custom event.
   * @param {Boolean} isCustom
   *   True, if a custom event.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener
   */
  function onoff(op, elm, eventName, selector, callback, params, isCustom) {
    if (isUndefined(params)) {
      params = {
        capture: true,
        passive: false
      };
    }

    var onEvent = function (e) {
      var t = e.target;

      if (matches(t, selector)) {
        callback.call(t, e);
      }
      else {
        while (t && t !== this) {
          if (matches(t, selector)) {
            callback.call(t, e);
            return;
          }
          t = t.parentElement || t.parentNode;
        }
      }
    };

    addRemoveEvent(op, elm, eventName, onEvent, params, isCustom);
  }

  // Attribute methods.
  dBlazy.attr = _attr;
  dBlazy.hasAttr = hasAttr;
  dBlazy.setAttr = setAttr;
  dBlazy.setAttrsWithSources = setAttrsWithSources;
  dBlazy.removeAttrs = removeAttrs;
  dBlazy.hasClass = hasClass;
  dBlazy.addClass = addClass;
  dBlazy.removeClass = removeClass;
  dBlazy.toggleClass = toggleClass;

  // String methods.
  dBlazy.contains = contains;
  dBlazy.escapeRegex = escapeRegex;
  dBlazy.matches = matches;
  dBlazy.startsWith = startsWith;
  dBlazy.trimSpaces = trimSpaces;

  // DOM query methods.
  dBlazy.closest = closest;
  dBlazy.equal = equal;
  dBlazy.find = find;
  dBlazy.findAll = findAll;
  dBlazy.remove = remove;

  // Collection methods.
  dBlazy.extend = extend;
  dBlazy.forEach = forEach;
  dBlazy.parse = parse;
  dBlazy.toArray = toArray;

  // Type checker methods.
  dBlazy.isNull = isNull;
  dBlazy.isArray = isArray;
  dBlazy.isElement = isElement;
  dBlazy.isFunction = isFunction;
  dBlazy.isObject = isObject;
  dBlazy.isUndefined = isUndefined;

  // Window methods.
  dBlazy.pixelRatio = pixelRatio;
  dBlazy.windowWidth = windowWidth;
  dBlazy.windowSize = windowSize;
  dBlazy.activeWidth = activeWidth;

  // Event methods.
  dBlazy.on = on;
  dBlazy.off = off;
  dBlazy.bindEvent = bindEvent;
  dBlazy.unbindEvent = unbindEvent;

  // Image methods.
  dBlazy.isDecoded = isDecoded;

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
    if (isDecoded(img)) {
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
    var backgrounds = parse(_attr(el, 'data-backgrounds'));

    if (backgrounds) {
      var bg = activeWidth(backgrounds, mobileFirst);
      if (bg && bg !== 'undefined') {
        el.style.backgroundImage = 'url("' + bg.src + '")';

        // Allows to disable Aspect ratio if it has known/ fixed heights such as
        // gridstack multi-size boxes.
        if (bg.ratio && !hasClass(el, 'b-noratio')) {
          el.style.paddingBottom = bg.ratio + '%';
        }
      }
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
    var _ani = 'animation';
    var _set = el.dataset;
    var props = [
      _ani,
      _ani + '-duration',
      _ani + '-delay',
      _ani + '-iteration-count'
    ];

    animation = animation || _set.animation;
    var classes = 'animated ' + animation;

    addClass(el, classes);
    forEach(['Duration', 'Delay', 'IterationCount'], function (key) {

      if (_set && _ani + key in _set) {
        el.style[_ani + key] = _set[_ani + key];
      }
    });

    // Supports both BG and regular image.
    var cn = closest(el, '.media') || el;
    var blur = find(cn, '.b-blur--tmp');

    function animationEnd() {
      removeAttrs(el, props);

      addClass(el, 'is-b-animated');
      removeClass(el, classes);

      forEach(props, function (key) {
        el.style.removeProperty(key);
      });

      remove(blur);

      unbindEvent(el, _ani + 'end', animationEnd);
    }

    bindEvent(el, _ani + 'end', animationEnd);
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
    var _loading = 'loading';
    // The .b-lazy element can be attached to IMG, or DIV as CSS background.
    // The .(*)loading can be .media, .grid, .slide__content, .box, etc.
    var loaders = [el, closest(el, '[class*="' + _loading + '"]')];

    forEach(loaders, function (loader) {
      if (loader) {
        var name = loader.className;
        if (contains(name, _loading)) {
          loader.className = name.replace(/(\S+)loading/g, '');
        }
      }
    });
  };

  /**
   * Executes a function once.
   *
   * To make easy conversion till D9.2 is a minimum at sub-modules, one
   * core/once method are adapted.
   *
   * @name dBlazy.once
   *
   * @author Daniel Lamb <dlamb.open.source@gmail.com>
   * @link https://github.com/daniellmb/once.js
   *
   * @param {Function} fn
   *   The executed function.
   * @param {NodeList|Array.<Element>|Element|string} selector
   *   A NodeList, array of elements, single Element, or a string.
   * @param {Document|Element} [context=document]
   *   An element to use as context for querySelectorAll.
   *
   * @return {Array.<Element>}
   *   An array of elements to process, or empty for old behavior.
   *
   * @tbd deprecated in Blazy 2.5 and will be removed in Blazy 3.+. Use the
   * core/once library instead. See https://www.drupal.org/node/3254668.
   */
  dBlazy.once = function (fn, selector, context) {
    var elms = [];

    // Original once.
    if (isUndefined(selector)) {
      _once(fn);
    }
    else {
      // If extra arguments are provided, assumes regular loop over elements.
      context = context || _doc;
      elms = findAll(context, selector);
      if (elms.length) {
        _once(forEach(elms, fn));
      }
    }

    return elms;
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
    _win.onresize = function () {
      _win.clearTimeout(t);
      t = _win.setTimeout(c, 200);
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
        string = string.replace(new RegExp(escapeRegex('$' + key), 'g'), map[key]);
      }
    }
    return trimSpaces(string);
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
    context = context || _doc;

    // jQuery may pass its array as non-expected context identified by length.
    context = 'length' in context ? context[0] : context;
    return context instanceof HTMLDocument ? context : _doc;
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
    var event;

    if (isUndefined(details)) {
      event = new Event(eventName);
    }
    else {
      // Bubbles to be caught by ancestors. Cancelable to preventDefault.
      var data = extend({
        bubbles: true,
        cancelable: true,
        detail: details || {}
      }, param || {});

      event = new CustomEvent(eventName, data);
    }

    elm.dispatchEvent(event);
    return event;
  };

  return dBlazy;

});
