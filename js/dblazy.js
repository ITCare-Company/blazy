/**
 * @file
 * This file contains common jQuery replacement methods for vanilla ones to DRY.
 *
 * Cherries by @toddmotto, @cferdinandi, @adamfschwartz, @daniellmb, Cash.
 *
 * Some dup wrappers are meant to DRY with null checks aka poorman null safety.
 * The rest are convenient to avoid object instantiation ($()) and to preserve
 * old behaviors pre Blazy 2.6 till all codebase are migrated as needed.
 * A few dups are still valid for single vs. chained element loop or queries.
 *
 * @todo use Cash for better DOM queries, or any core libraries when available.
 * @todo remove unneeded dup methods once all codebase migrated.
 */

/* global define, module */
(function (_win, _doc) {

  'use strict';

  var extend = Object.assign;
  var _aProto = Array.prototype;
  var _oProto = Object.prototype;
  var _splice = _aProto.splice;
  var _some = _aProto.some;
  var _symbol = Symbol;
  var _add = 'add';
  var _remove = 'remove';
  var _iterator = 'iterator';
  var _events = {};

  /**
   * Object for public APIs where dBlazy stands for drupalBlazy.
   *
   * @namespace
   *
   * @return {dBlazy}
   *   Returns this instance.
   */
  var dBlazy = function () {
    function dBlazy(selector, context) {
      var me = this;

      if (!selector) {
        return;
      }

      if (isMe(selector)) {
        return selector;
      }

      var els = selector;
      if (isStr(selector)) {
        var ctx = (isMe(context) ? context[0] : context) || _doc;
        els = findAll(ctx, selector);
        if (isEmpty(els)) {
          return;
        }
      }
      else if (isFun(selector)) {
        return me.ready(selector);
      }

      if (els.nodeType || els === _win) {
        els = [els];
      }

      var len = me.length = els.length;
      for (var i = 0; i < len; i++) {
        me[i] = els[i];
      }
    }

    dBlazy.prototype.init = function (selector, context) {
      var instance = new dBlazy(selector, context);
      if (isElm(selector)) {
        if (!selector.idblazy) {
          selector.idblazy = instance;
        }

        return selector.idblazy;
      }

      return instance;
    };

    return dBlazy;
  }();

  // Cache our prototype.
  var fn = dBlazy.prototype;
  // Alias instantiation for a shortcut like jQuery $(selector, context).
  var db = fn.init;
  db.fn = db.prototype = fn;

  fn.length = 0;

  // Ensuring a db collection gets printed as array-like in Chrome's devtools.
  fn.splice = _splice;

  if (isFun(_symbol)) {
    // Ensuring a db collection is iterable.
    fn[_symbol[_iterator]] = _aProto[_symbol[_iterator]];
  }

  /**
   * Excecutes chainable callback to avoid unnecessary loop unless required.
   *
   * @private
   *
   * @param {!Function} cb
   *   The calback function.
   *
   * @return {Object}
   *   The current dBlazy collection object.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Optional_chaining
   */
  function chain(cb) {
    var me = this;
    // Ok, this is insanely me.
    me = isMe(me) ? me : db(me);
    var ln = me.length;

    if (!ln || ln === 1) {
      cb(me[0]);
    }
    else {
      me.each(cb);
    }

    return me;
  }

  /**
   * Returns true if the x is a dBlazy.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is an instanceof dBlazy.
   */
  function isMe(x) {
    return x instanceof dBlazy;
  }

  /**
   * Returns true if the x is an array.
   *
   * @private
   *
   * One of the weird behaviors in JavaScript is the typeof Array is Object.
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is an instanceof Array.
   */
  function isArr(x) {
    return !isEmpty(x) && Array.isArray(x);
  }

  /**
   * Returns true if the x is a boolean.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is an instanceof bool.
   */
  function isBool(x) {
    return typeof x === 'boolean';
  }

  /**
   * Returns true if the x is an Element.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is an instanceof Element.
   */
  function isElm(x) {
    return x instanceof Element;
  }

  /**
   * Returns true if the x is a function.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is an instanceof Function.
   */
  function isFun(x) {
    return typeof x === 'function';
  }

  /**
   * Returns true if the x is anything falsy.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if null or empty array.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Nullish_coalescing_operator
   */
  function isEmpty(x) {
    return isNull(x) || isUnd(x) || x === false || (x.length && x.length === 0);
  }

  /**
   * Returns true if the x is a null.
   *
   * To those curious why this very simple comparasion has a method, check
   * out the minified one. It is called 7 times here, but called once at the
   * minifid one to just 1 character + 7 (`=== null`) = 14, saving many byte
   * codes. Otherwise `=== null` x 7 chracters = 49.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if null.
   */
  function isNull(x) {
    return x === null;
  }

  /**
   * Returns true if the x is a number.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if number.
   */
  function isNum(x) {
    return !isNaN(parseFloat(x)) && isFinite(x);
  }

  /**
   * Returns true if the x is an object.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is an instanceof Object.
   */
  function isObj(x) {
    if (typeof x !== 'object' || isEmpty(x)) {
      return false;
    }
    var proto = Object.getPrototypeOf(x);
    return isNull(proto) || proto === _oProto;
  }

  /**
   * Returns true if the x is a string.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is a string.
   */
  function isStr(x) {
    return typeof x === 'string';
  }

  /**
   * Returns true if the x is undefined.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is undefined.
   */
  function isUnd(x) {
    return typeof x === 'undefined';
  }

  /**
   * Returns true if the x is window.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is window.
   */
  function isWin(x) {
    return !!x && x === x.window;
  }

  /**
   * Returns true if the x is valid for querySelector.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is valid for querySelector.
   *
   * 1: Node.ELEMENT_NODE
   * 9: Node.DOCUMENT_NODE
   * 11: Node.DOCUMENT_FRAGMENT_NODE
   * @see https://developer.mozilla.org/en-US/docs/Web/API/Node/nodeType
   */
  function isQuery(x) {
    return [1, 9, 11].indexOf(!!x && x.nodeType) !== -1;
  }

  /**
   * Returns true if the x is valid for event listener.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is valid for event listener.
   */
  function isEvt(x) {
    return isQuery(x) || isWin(x);
  }

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
   * @param {Function} cb
   *   Callback function for each iteration.
   * @param {Array|Object|NodeList} scope
   *   Object/NodeList/Array that forEach is iterating over (aka `this`).
   *
   * @return {Array}
   *   Returns this collection.
   *
   * @todo drop for native [].forEach post D10+ when IE gone from planet earth.
   */
  function each(collection, cb, scope) {
    if (_oProto.toString.call(collection) === '[object Object]') {
      for (var prop in collection) {
        if (_oProto.hasOwnProperty.call(collection, prop)) {
          if (prop === 'length') {
            continue;
          }
          cb.call(scope, collection[prop], prop, collection);
        }
      }
    }
    else if (collection) {
      var len = collection.length;
      for (var i = 0; i < len; i++) {
        cb.call(scope, collection[i], i, collection);
      }
    }

    return collection;
  }

  /**
   * A simple wrapper for JSON.parse() for string within data-* attributes.
   *
   * @private
   *
   * @param {string} str
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
   * @param {Element|string} x
   *   The object to make array.
   *
   * @return {Array}
   *   The resulting array.
   */
  function toArray(x) {
    return isArr(x) ? x : [x];
  }

  /**
   * A forgiving attribute wrapper with fallback mimicking jQuery.attr method.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string|Object} attr
   *   The attr name, can be a string or object.
   * @param {string} defValue
   *   The default value, can be null or undefined for different intentions.
   * @param {string|bool} withDefault
   *   True if should get with defValue. Or a prefix such as data- for removal.
   *
   * @return {Object|string}
   *   The attribute value, or fallback, for getters, or this for setters.
   */
  function _attr(els, attr, defValue, withDefault) {
    var me = this;
    var _undefined = isUnd(defValue);
    var _getter = _undefined || isBool(withDefault);
    var prefix = isStr(withDefault) ? withDefault : '';

    // No defValue defined, or withDefault set, means a getter.
    if (_getter) {
      // @todo figure out multi-element getters. Ok for now, as hardly multiple.
      var el = els && els.length ? els[0] : els;
      if (_undefined) {
        defValue = '';
      }
      return hasAttr(el, attr) ? el.getAttribute(attr) : defValue;
    }

    var chainCallback = function (el) {
      if (!isQuery(el)) {
        return _getter ? '' : me;
      }

      // Passing a key-value pair object means setting multiple attributes once.
      if (isObj(attr)) {
        each(attr, function (value, key) {
          el.setAttribute(prefix + key, value);
        });
      }
      // Since an attribute value null makes no sense, assumes nullify.
      else if (isNull(defValue)) {
        each(toArray(attr), function (value) {
          var name = prefix + value;
          if (el.hasAttribute(name)) {
            el.removeAttribute(name);
          }
        });
      }
      else {
        // Else a setter.
        if (attr === 'src') {
          // To minimize unnecessary mutations.
          el.src = defValue;
        }
        else {
          el.setAttribute(attr, defValue);
        }
      }
    };

    return chain.call(els, chainCallback);
  }

  /**
   * Checks if the element has attribute.
   *
   * @private
   *
   * @param {Element} el
   *   The HTML element.
   * @param {string} name
   *   The attribute name.
   *
   * @return {bool}
   *   True if it has the attribute.
   */
  function hasAttr(el, name) {
    return isQuery(el) && el.hasAttribute(name);
  }

  /**
   * A removeAttribute wrapper.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string|Array} attr
   *   The attr name, or string array.
   * @param {string} prefix
   *   The attribute prefix if any, normally `data-`.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function removeAttr(els, attr, prefix) {
    return _attr(els, attr, null, prefix || '');
  }

  /**
   * Checks if the element has a class name.
   *
   * @private
   *
   * @param {Element} el
   *   The HTML element.
   * @param {string} name
   *   The class name, can be space-delimited for multiple names.
   *
   * @return {bool}
   *   True if it has the class name.
   */
  function hasClass(el, name) {
    var found = 0;

    if (isQuery(el) && isStr(name)) {
      var _list = el.classList;

      each(name.split(' '), function (item) {
        if (_list && _list.contains(item)) {
          found++;
        }
      });
    }
    return found > 0;
  }

  /**
   * Toggles a class, or multiple from an element.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} name
   *   The class name, or space-delimited class names.
   * @param {string} op
   *   Whether to add or remove the class.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function toggleClass(els, name, op) {
    var chainCallback = function (el) {
      if (isQuery(el) && isStr(name)) {
        var _list = el.classList;
        var names = name.split(' ');
        if (_list) {
          if (isUnd(op)) {
            names.map(function (value) {
              _list.toggle(value);
            });
          }
          else {
            _list[op].apply(_list, names);
          }
        }
      }
    };
    return chain.call(els, chainCallback);
  }

  /**
   * Adds a class, or space-delimited class names to an element.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} name
   *   The class name, or space-delimited class names.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function addClass(els, name) {
    return toggleClass(els, name, _add);
  }

  /**
   * Removes a class, or multiple from an element.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} name
   *   The class name, or space-delimited class names.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function removeClass(els, name) {
    return toggleClass(els, name, _remove);
  }

  /**
   * Checks if a string contains substring(s) (ES6 ::includes), only for oldies.
   *
   * @private
   *
   * Cannot use [].every() since it not about all or nothing.
   *
   * @param {string} str
   *   The source string to test for.
   * @param {Array.<string>} substr
   *   The target sub-string to check for, can be a string array.
   *
   * @return {bool}
   *   True if it has the needle.
   *
   * @todo use polyfill core/drupal.string.includes when min D9.3.
   */
  function contains(str, substr) {
    var found = 0;

    if (isStr(str)) {
      each(toArray(substr), function (value) {
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
   * @param {string} string
   *   The original source string.
   *
   * @return {string}
   *   The modified string.
   */
  function escape(string) {
    // $& means the whole matched string.
    return string.replace(/[.*+\-?^${}()|[\]\\]/g, '\\$&');
  }

  /**
   * Checks whether or not a string begins with another string, case-sensitive.
   *
   * @private
   *
   * @param {string} str
   *   The source string to test for.
   * @param {Array.<string>} substr
   *   The target sub-string to check for, can be a string array.
   *
   * @return {bool}
   *   True if it starts with the needle.
   */
  function startsWith(str, substr) {
    var found = 0;

    if (isStr(str)) {
      each(toArray(substr), function (value) {
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
   * @param {string} string
   *   The original source string.
   *
   * @return {string}
   *   The modified string.
   */
  function trimSpaces(string) {
    return string.replace(/\\s+/g, ' ').trim();
  }

  /**
   * A forgiving closest for the lazy.
   *
   * @private
   *
   * @param {Element} el
   *   Starting element.
   * @param {string} selector
   *   Selector to match against (class, ID, data attribute, or tag).
   *
   * @return {Element|Null}
   *   Returns null if no match found, else the element.
   */
  function closest(el, selector) {
    return (isElm(el) && isStr(selector)) ? el.closest(selector) : null;
  }

  /**
   * A forgiving matches for the lazy.
   *
   * @private
   *
   * @param {Element} el
   *   The current element.
   * @param {string} selector
   *   Selector to match against (class, ID, data attribute, or tag).
   *
   * @return {bool}
   *   Returns true if found, else false.
   *
   * @see http://caniuse.com/#feat=matchesselector
   * @see https://developer.mozilla.org/en-US/docs/Web/API/Element/matches
   */
  function matches(el, selector) {
    return isQuery(el) && isStr(selector) && el.matches(selector);
  }

  /**
   * Check if an element matches the specified HTML tag.
   *
   * @private
   *
   * @param {Element} el
   *   The element to compare.
   * @param {string|Array.<string>} tags
   *   HTML tag(s) to match against.
   *
   * @return {bool}
   *   Returns true if matches, else false.
   */
  function equal(el, tags) {
    return _some.call(toArray(tags), function (tag) {
      return isQuery(el) && (el.nodeName.toLowerCase() === tag.toLowerCase());
    });
  }

  /**
   * A simple querySelector wrapper.
   *
   * @private
   *
   * The only different from jQuery is if a single element found, it returns
   * the element so to avoid ugly repeats like elms[0], also to preserve
   * common vanilla practice which normally operates on the element directly.
   * Alternatively flag the asArray to any value if an array is expected, or
   * use the shortcut ::findAll() to be clear.
   *
   * @param {Element} el
   *   The parent HTML element.
   * @param {string} selector
   *   The CSS selector or HTML tag to query.
   * @param {bool|int} asArray
   *   Force returning an array if expected to operate on.
   *
   * @return {?Array.<Element>}
   *   Empty array if not found, else the expected element(s).
   */
  function find(el, selector, asArray) {
    if (isStr(selector) && isQuery(el)) {
      return isUnd(asArray) ? (el.querySelector(selector) || []) : toElms(selector, el);
    }
    return [];
  }

  /**
   * A simple querySelectorAll wrapper.
   *
   * @private
   *
   * @param {Element} el
   *   The parent HTML element.
   * @param {string} selector
   *   The CSS selector or HTML tag to query.
   *
   * @return {?Array.<Element>}
   *   Empty array if not found, else the expected elements.
   */
  function findAll(el, selector) {
    return find(el, selector, 1);
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
    if (isElm(el)) {
      var parent = el.parentNode;
      if (parent) {
        parent.removeChild(el);
      }
    }
  }

  /**
   * Returns true if an IE browser.
   *
   * @private
   *
   * @return {bool}
   *   True if an IE browser.
   */
  function ie() {
    return !isUnd(_doc.documentMode);
  }

  /**
   * Returns device pixel ratio.
   *
   * @private
   *
   * @return {number}
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
   * @return {number}
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
      height: _win.innerHeight || _doc.documentElement.clientHeight
    };
  }

  /**
   * Returns viewport info.
   *
   * @private
   *
   * @param {Element} offset
   *   The offset defined via UI normally related to header fixed position.
   *
   * @return {Object}
   *   Returns the window viewport info.
   */
  function viewport(offset) {
    offset = offset || 0;
    var size = windowSize();
    return {
      top: 0 - offset,
      left: 0 - offset,
      bottom: size.height + offset,
      right: size.width + offset
    };
  }

  /**
   * Returns element visibility.
   *
   * @private
   *
   * @param {Element} el
   *   The HTML element to test.
   * @param {Object} vp
   *   The window viewport.
   *
   * @return {bool}
   *   Returns true if visible.
   */
  function isVisible(el, vp) {
    var rect = el.getBoundingClientRect();

    return ((rect.top > vp.top || rect.bottom > 0) && rect.top < vp.bottom);
  }

  /**
   * Returns data from the current active window.
   *
   * @private
   *
   * When being resized, the browser gave no data about pixel ratio from desktop
   * to mobile, not vice versa. Unless delayed for 4s+, not less, which is of
   * course unacceptable. Hence why Blazy never claims to support resizing. The
   * best efforts were provided using ResizeObserver since 2.2. including this.
   *
   * @param {Object.<int, Object>} dataset
   *   The dataset object must be keyed by window width.
   * @param {Object.<string, int|bool>} winData
   *   Containing ww: windowWidth, and up: to determine min-width or max-width.
   *
   * @return {Mixed}
   *   Returns data from the current active window.
   */
  function activeWidth(dataset, winData) {
    var mobileFirst = winData.up || false;
    var keys = Object.keys(dataset);
    var xs = keys[0];
    var xl = keys[keys.length - 1];
    var ww = winData.ww || windowWidth();
    var pr = (ww * pixelRatio());
    var rw = mobileFirst ? ww : pr;
    var mw = function (w) {
      // The picture wants <= (approximate), non-picture wants >=, wtf.
      return mobileFirst ? parseInt(w, 10) <= rw : parseInt(w, 10) >= rw;
    };

    var data = keys.filter(mw).map(function (v) {
      return dataset[v];
    })[mobileFirst ? 'pop' : 'shift']();

    return isUnd(data) ? dataset[rw >= xl ? xl : xs] : data;
  }

  /**
   * A simple wrapper for event delegation like jQuery.on().
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} eventName
   *   The event name to trigger.
   * @param {string} selector
   *   Child selector to match against (class, ID, data attribute, or tag).
   * @param {Function} cb
   *   The callback function.
   * @param {Object|bool} params
   *   The optional param passed into a custom event.
   * @param {bool} isCustom
   *   True, if a custom event, a namespaced like (blazy.done), but considered
   *   as a whole since there is no event name `blazy`.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function on(els, eventName, selector, cb, params, isCustom) {
    return onoff(els, eventName, selector, cb, params, isCustom, _add);
  }

  /**
   * A simple wrapper for event detachment.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} eventName
   *   The event name to trigger.
   * @param {string} selector
   *   Child selector to match against (class, ID, data attribute, or tag).
   * @param {Function} cb
   *   The callback function.
   * @param {Object|bool} params
   *   The optional param passed into a custom event.
   * @param {bool} isCustom
   *   True, if a custom event.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function off(els, eventName, selector, cb, params, isCustom) {
    return onoff(els, eventName, selector, cb, params, isCustom, _remove);
  }

  /**
   * A simple wrapper for addEventListener.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} eventName
   *   The event name to remove.
   * @param {Function} cb
   *   The callback function.
   * @param {Object|bool} params
   *   The optional param passed into a custom event.
   * @param {bool} isCustom
   *   True, if a custom event.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function bindEvent(els, eventName, cb, params, isCustom) {
    return toEvent(els, eventName, cb, params, isCustom, _add);
  }

  /**
   * A simple wrapper for removeEventListener.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} eventName
   *   The event name to remove.
   * @param {Function} cb
   *   The callback function.
   * @param {Object} params
   *   The optional param passed into a custom event.
   * @param {bool} isCustom
   *   True, if a custom event.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function unbindEvent(els, eventName, cb, params, isCustom) {
    return toEvent(els, eventName, cb, params, isCustom, _remove);
  }

  /**
   * A simple wrapper for addEventListener once.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} eventName
   *   The event name to remove.
   * @param {Function} cb
   *   The callback function.
   * @param {bool} isCustom
   *   True, if a custom event.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function one(els, eventName, cb, isCustom) {
    return bindEvent(els, eventName, cb, {
      once: true
    }, isCustom);
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
    return img.decoded || img.complete;
  }

  /**
   * Checks if image or iframe is decoded/ completely loaded.
   *
   * @private
   *
   * @param {Image|Iframe} el
   *   The Image or Iframe element.
   *
   * @return {bool}
   *   True if the image or iframe is loaded.
   */
  function isLoaded(el) {
    if (isElm(el)) {
      if (equal(el, 'img')) {
        return isDecoded(el);
      }
      if (equal(el, 'iframe')) {
        var doc = el.contentDocument || el.contentWindow.document;
        return doc.readyState === 'complete';
      }
    }
    return false;
  }

  /**
   * Executes the function once.
   *
   * @private
   *
   * @param {Function} cb
   *   The executed function.
   *
   * @return {Object}
   *   The function result.
   */
  function _once(cb) {
    var result;
    var ran = false;
    return function proxy() {
      if (ran) {
        return result;
      }
      ran = true;
      result = cb.apply(this, arguments);
      // For garbage collection.
      cb = null;
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
  function toElms(selector, context) {
    // Assume selector is an array-like element unless a string.
    var elements = toArray(selector);
    if (isStr(selector)) {
      var check = context.querySelector(selector);
      elements = isNull(check) ? [] : context.querySelectorAll(selector);
    }

    // Ensures an array is returned and not a NodeList or an Array-like object.
    // https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array/from
    return _aProto.slice.call(elements);
  }

  /**
   * A not simple wrapper for the namespaced [add|remove]EventListener.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} eventName
   *   The event name, optionally namespaced, to add or remove.
   * @param {Function} cb
   *   The callback function.
   * @param {Object|bool} params
   *   The optional param passed into a custom event.
   * @param {bool} isCustom
   *   Like namespaced, but not to be namespaced since LHS is not any event.
   * @param {string} op
   *   Whether to add or remove the event.
   *
   * @return {Object}
   *   This dBlazy object.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener
   * @see https://caniuse.com/once-event-listener
   */
  function toEvent(els, eventName, cb, params, isCustom, op) {
    var chainCallback = function (el) {
      if (!isEvt(el)) {
        return;
      }

      var defaults = {
        capture: false,
        passive: true
      };

      var _one = false;
      var options = params || false;
      if (isObj(params)) {
        options = extend(defaults, params);
        _one = options.once || false;
      }

      var process = function (e) {
        isCustom = isCustom || startsWith(e, ['blazy.', 'bio.']);
        var add = op === _add;
        var type = (isCustom ? e : e.split('.')[0]).trim();
        cb = cb || _events[e];

        var _cb = cb;
        if (isFun(cb)) {
          // See https://caniuse.com/once-event-listener.
          if (_one && add && ie()) {
            var cbone = function cbone(evt) {
              el.removeEventListener(type, cbone, options);
              _cb.apply(this, arguments);
            };
            cb = cbone;
            add = false;
          }

          el[op + 'EventListener'](type, cb, options);
        }

        if (add) {
          _events[e] = cb;
        }
        else {
          delete _events[e];
        }
      };

      each(eventName.split(' '), process);
    };

    return chain.call(els, chainCallback);
  }

  /**
   * A simple wrapper for event delegation like jQuery.on().
   *
   * @private
   *
   * Inspired by http://stackoverflow.com/questions/30880757/
   * javascript-equivalent-to-on.
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} eventName
   *   The optionally namespaced event name to trigger.
   * @param {string} selector
   *   Child selector to match against (class, ID, data attribute, or tag).
   * @param {Function} cb
   *   The callback function.
   * @param {Object|bool} params
   *   The optional param passed into a custom event.
   * @param {bool} isCustom
   *   True, if a custom event.
   * @param {string} op
   *   Whether to add or remove the event.
   *
   * @return {Object}
   *   This dBlazy object.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener
   */
  function onoff(els, eventName, selector, cb, params, isCustom, op) {
    if (isUnd(params)) {
      params = {
        capture: true,
        passive: false
      };
    }

    var onEvent = function (e) {
      var t = e.target;

      if (matches(t, selector)) {
        cb.call(t, e);
      }
      else {
        while (t && t !== this) {
          if (matches(t, selector)) {
            cb.call(t, e);
            return;
          }
          t = t.parentElement || t.parentNode;
        }
      }
    };

    return toEvent(els, eventName, onEvent, params, isCustom, op);
  }

  /**
   * A not simple wrapper for triggering event like jQuery.trigger().
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} eventName
   *   The event name to trigger.
   * @param {Object} details
   *   The optional detail object passed into a custom event detail property.
   * @param {Object} param
   *   The optional param passed into a custom event.
   *
   * @return {CustomEvent|Event|undefined}
   *   The CustomEvent or Event object to dispatch.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/Guide/Events/Creating_and_triggering_events
   * @see https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/dispatchEvent
   * @todo namespaced event name.
   */
  function trigger(els, eventName, details, param) {
    var chainCallback = function (el) {
      var event;
      if (!isEvt(el)) {
        return event;
      }

      if (isUnd(details)) {
        event = new Event(eventName);
      }
      else {
        // Bubbles to be caught by ancestors. Cancelable to preventDefault.
        var data = {
          bubbles: true,
          cancelable: true,
          detail: details || {}
        };

        if (isObj(param)) {
          data = extend(data, param);
        }

        event = new CustomEvent(eventName, data);
      }

      el.dispatchEvent(event);
      return event;
    };

    return chain.call(els, chainCallback);
  }

  db.trigger = trigger.bind(db);
  fn.trigger = function (eventName, details, param) {
    return trigger(this, eventName, details, param);
  };

  // Type methods.
  // Wonder why ES6 has alt lambda `=>` for `function`? Compact, to save bytes.
  // Kotlin has useless `fun` due to being compiled back to `function`. But ES6
  // lambda is true savings unless being transpiled. So these stupid abbr are.
  // The contract here is no rigid minds, fun, less bytes. Hail to Linux.
  db.isArr = isArr;
  db.isBool = isBool;
  db.isElm = isElm;
  db.isFun = isFun;
  db.isEmpty = isEmpty;
  db.isNull = isNull;
  db.isNum = isNum;
  db.isObj = isObj;
  db.isStr = isStr;
  db.isUnd = isUnd;
  db.isEvt = isEvt;
  db.isQuery = isQuery;
  db.isVisible = isVisible;
  db.isIo = 'IntersectionObserver' in _win;
  db.isMo = 'MutationObserver' in _win;
  db.isRo = 'ResizeObserver' in _win;
  db.isNative = 'loading' in HTMLImageElement.prototype;
  db.isAmd = typeof define === 'function' && define.amd;
  db._er = -1;
  db._ok = 1;

  // Collection methods.
  db.chain = function (els, cb) {
    return chain.call(els, cb);
  };

  fn.chain = function (cb) {
    return chain.call(this, cb);
  };

  // @deprecated for db.each for consistency and save bytes.
  db.forEach = each;
  db.each = each;
  fn.each = function (cb) {
    return each(this, cb);
  };

  db.extend = extend;
  fn.extend = function (plugins) {
    return extend(fn, plugins);
  };

  db.parse = parse;
  db.toArray = toArray;

  // Attribute methods.
  db.hasAttr = hasAttr.bind(db);
  fn.hasAttr = function (name) {
    var me = this;
    return _some.call(me, function (el) {
      return hasAttr.call(me, el, name);
    });
  };

  db.attr = _attr.bind(db);
  fn.attr = function (attr, defValue, withDefault) {
    var me = this;
    if (isNull(defValue)) {
      return me.removeAttr(attr, withDefault);
    }
    return _attr(me, attr, defValue, withDefault);
  };

  db.removeAttr = removeAttr.bind(db);
  fn.removeAttr = function (attr, prefix) {
    return removeAttr(this, attr, prefix);
  };

  // Class name methods.
  db.hasClass = hasClass.bind(db);
  fn.hasClass = function (name) {
    var me = this;
    return _some.call(me, function (el) {
      return hasClass.call(me, el, name);
    });
  };

  db.toggleClass = toggleClass.bind(db);
  fn.toggleClass = function (name, op) {
    return toggleClass(this, name, op);
  };

  db.addClass = addClass.bind(db);
  fn.addClass = function (name) {
    return this.toggleClass(name, _add);
  };

  db.removeClass = removeClass.bind(db);
  fn.removeClass = function (name) {
    var me = this;
    return arguments.length ? me.toggleClass(name, _remove) : me.attr('class', '');
  };

  // String methods.
  db.contains = contains;
  db.escape = escape;
  db.startsWith = startsWith;
  db.trimSpaces = trimSpaces;

  // DOM query methods.
  db.closest = closest;
  fn.closest = function (selector) {
    return closest(this[0], selector);
  };

  db.matches = matches;

  db.equal = equal;
  fn.equal = function (selector) {
    return equal(this[0], selector);
  };

  db.find = find;
  fn.find = function (selector, asArray) {
    return find(this[0], selector, asArray);
  };

  db.findAll = findAll;
  fn.findAll = function (selector) {
    return findAll(this[0], selector);
    // @todo multiple sources for multiple targets.
    // return this.each(function (el) {
    // els.push(findAll(el, selector));
    // });
  };

  fn.first = function (el) {
    return isUnd(el) ? this[0] : el;
  };

  db.remove = remove;
  fn.remove = function () {
    this.each(remove);
  };

  // Window methods.
  db.ie = ie;
  db.pixelRatio = pixelRatio;
  db.windowWidth = windowWidth;
  db.windowSize = windowSize;
  db.activeWidth = activeWidth;
  db.viewport = viewport;
  db.ww = 0;
  db.vp = {};

  db.checkViewport = function (offset) {
    var me = this;
    me.vp = viewport(offset || 100);
    me.ww = me.vp.right;
  };

  db.winData = function (mobileFirst) {
    var me = this;
    return {
      vp: me.vp || {},
      ww: me.ww || 0,
      up: mobileFirst || false
    };
  };

  // Event methods.
  fn.toEvent = function (eventName, cb, params, isCustom, op) {
    return toEvent(this, eventName, cb, params, isCustom, op);
  };

  fn.onoff = function (eventName, selector, cb, params, isCustom, op) {
    return onoff(this, eventName, selector, cb, params, isCustom, op);
  };

  db.on = on.bind(db);
  fn.on = function (eventName, selector, cb, params, isCustom) {
    return this.onoff(eventName, selector, cb, params, isCustom, _add);
  };

  db.off = off.bind(db);
  fn.off = function (eventName, selector, cb, params, isCustom) {
    return this.onoff(eventName, selector, cb, params, isCustom, _remove);
  };

  db.bindEvent = bindEvent.bind(db);
  fn.bindEvent = function (eventName, cb, params, isCustom) {
    return this.toEvent(eventName, cb, params, isCustom, _add);
  };

  db.unbindEvent = unbindEvent.bind(db);
  fn.unbindEvent = function (eventName, cb, params, isCustom) {
    return this.toEvent(eventName, cb, params, isCustom, _remove);
  };

  db.one = one.bind(db);
  fn.one = function (eventName, cb, isCustom) {
    return one(this, eventName, cb, isCustom);
  };

  // Image methods.
  db.isDecoded = isDecoded;
  db.isLoaded = isLoaded;

  // Enqueue operations.
  db.enqueue = function (queue, cb, scope) {
    each(queue, cb.bind(scope));
    queue.length = 0;
  };

  // Similar to core domReady, only public and generic.
  fn.ready = function (callback) {
    var cb = function () {
      return setTimeout(callback, 0, db);
    };

    if (_doc.readyState !== 'loading') {
      cb();
    }
    else {
      _doc.addEventListener('DOMContentLoaded', cb);
    }

    return this;
  };

  /**
   * Decodes the image.
   *
   * @param {Image} img
   *   The Image object.
   *
   * @return {Promise}
   *   The Promise object.
   *
   * @see https://caniuse.com/promises
   * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise
   * @see https://github.com/taylorhakes/promise-polyfill
   */
  db.decode = function (img) {
    if (isDecoded(img)) {
      return Promise.resolve(img);
    }

    if ('decode' in img) {
      img.decoding = 'async';
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
   * A simple wrapper to animate anything using animate.css.
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string|Function} cb
   *   Any custom animation name, fallbacks to [data-animation], or a callback.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function animate(els, cb) {
    var me = this;

    var chainCallback = function (el) {
      if (!isElm(el)) {
        return me;
      }

      var $el = db(el);
      var _set = el.dataset;
      var animation = _set.animation;

      if (isStr(cb)) {
        animation = cb;
      }

      var _ani = 'animation';
      var _animated = 'animated';
      var _aniEnd = _ani + 'end.' + animation;
      var _style = el.style;
      var _blur = 'blur';
      var _bblur = 'b-' + _blur;
      var classes = _animated + ' ' + animation;
      var props = [
        _ani,
        _ani + '-duration',
        _ani + '-delay',
        _ani + '-iteration-count'
      ];

      $el.addClass(classes);

      each(['Duration', 'Delay', 'IterationCount'], function (key) {
        var _aniKey = _ani + key;
        if (_set && _aniKey in _set) {
          _style[_aniKey] = _set[_aniKey];
        }
      });

      // Supports both BG and regular image.
      var cn = closest(el, '.media') || el;
      var bg = $el.hasClass('b-bg');
      var isBlur = animation === _blur;
      var an = el;

      // The animated blur is image not this container, except a background.
      if (isBlur && !bg) {
        an = find(cn, 'img:not(.' + _bblur + ')') || an;
      }

      function ended(e) {
        $el.addClass('is-b-' + _animated)
          .removeClass(classes)
          .removeAttr(props, 'data-');

        each(props, function (key) {
          _style.removeProperty(key);
        });

        if (isFun(cb)) {
          cb(e);
        }
      }

      return one(an, _aniEnd, ended, false);
    };

    return chain.call(els, chainCallback);
  }

  db.animate = animate.bind(db);
  fn.animate = function (animation) {
    return animate(this, animation);
  };

  /**
   * Executes a function once.
   *
   * To make easy conversion till D9.2 is a minimum at sub-modules, one
   * core/once method are adapted.
   *
   * @author Daniel Lamb <dlamb.open.source@gmail.com>
   * @link https://github.com/daniellmb/once.js
   *
   * @param {Function} cb
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
   *
   * @todo until D9.2 is a minimum, adapt core/once for better once at the next
   * optimization sessions.
   */
  db.once = function (cb, selector, context) {
    var els = [];

    // Original once.
    if (isUnd(selector)) {
      _once(cb);
    }
    else {
      // If extra arguments are provided, assumes regular loop over elements.
      // Safe to use fallback _doc since it is normally executed once onready.
      els = findAll(context || _doc, selector);
      if (els.length) {
        _once(each(els, cb));
      }
    }

    return els;
  };

  /**
   * A simple wrapper to delay callback function, taken out of blazy library.
   *
   * Alternative to core Drupal.debounce for D7 compatibility, and easy port.
   *
   * @param {Function} cb
   *   The callback function.
   * @param {number} minDelay
   *   The execution delay in milliseconds.
   * @param {Object} scope
   *   The scope of the function to apply to, normally this.
   *
   * @return {Function}
   *   The function executed at the specified minDelay.
   */
  function throttle(cb, minDelay, scope) {
    minDelay = minDelay || 50;
    var lastCall = 0;
    return function () {
      var now = +new Date();
      if (now - lastCall < minDelay) {
        return;
      }
      lastCall = now;
      cb.apply(scope, arguments);
    };
  }

  db.throttle = throttle;

  /**
   * A simple wrapper to delay callback function on window resize.
   *
   * @link https://github.com/louisremi/jquery-smartresize
   *
   * @param {Function} cb
   *   The callback function.
   * @param {number} t
   *   The timeout.
   *
   * @return {Function}
   *   The callback function.
   *
   * @todo merge it with ResizeObserver.
   */
  db.resize = function (cb, t) {
    _win.onresize = function () {
      clearTimeout(t);
      t = setTimeout(cb, 200);
    };
    return cb;
  };

  /**
   * Replaces string occurances to simplify string templating.
   *
   * @param {string} string
   *   The original source string.
   * @param {Object.<string, string>} map
   *   The mapping object.
   *
   * @return {string}
   *   The modified string.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Template_literals
   * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/replaceAll
   * @see https://caniuse.com/mdn-javascript_builtins_string_replaceall
   * @see https://stackoverflow.com/questions/1144783
   * @todo use template string or replaceAll for D10, or D11 at the latest.
   */
  db.template = function (string, map) {
    for (var key in map) {
      if (_oProto.hasOwnProperty.call(map, key)) {
        string = string.replace(new RegExp(escape('$' + key), 'g'), map[key]);
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
   * @param {HTMLDocument|Element} context
   *   Any element, including weird script element.
   *
   * @return {HTMLDocument|Document}
   *   The HTMLDocument or Document so to avoid failing querySelector, etc.
   */
  db.context = function (context) {
    // Weirdo: context may be null after Colorbox close.
    context = context || _doc;

    // jQuery may pass its array as non-expected context identified by length.
    context = context.length ? context[0] : context;
    return context instanceof HTMLDocument ? context : _doc;
  };

  if (typeof exports !== 'undefined') {
    // Node.js.
    module.exports = db;
  }
  else {
    // Browser.
    _win.dBlazy = db;
  }

})(this, this.document);
