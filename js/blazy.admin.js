/**
 * @file
 * Provides admin utilities.
 */

(function ($, Drupal, once, _db) {

  'use strict';

  /**
   * Blazy admin utility functions.
   *
   * @param {HTMLElement} form
   *   The Blazy form wrapper HTML element.
   */
  function blazyForm(form) {
    var t = $(form);

    $('.details-legend-prefix', t).removeClass('element-invisible');

    t[$('.form-checkbox--vanilla', t).prop('checked') ? 'addClass' : 'removeClass']('form--vanilla-on');

    t.on('click', '.form-checkbox', function () {
      var $input = $(this);
      $input[$input.prop('checked') ? 'addClass' : 'removeClass']('on');

      if ($input.hasClass('form-checkbox--vanilla')) {
        t[$input.prop('checked') ? 'addClass' : 'removeClass']('form--vanilla-on');
      }
    });

    $('select[name$="[style]"]', t).on('change', function () {
      var $select = $(this);
      var value = $select.val();

      t.removeClass(function (index, css) {
        return (css.match(/(^|\s)form--style-\S+/g) || []).join(' ');
      });

      if (value === '') {
        t.addClass('form--style-off form--style-is-grid');
      }
      else {
        t.addClass('form--style-on form--style-' + value);
        if (value === 'column' || value === 'grid' || value === 'flex' || value === 'nativegrid') {
          t.addClass('form--style-is-grid');
        }
      }
    }).change();

    $('select[name$="[grid]"]', t).on('change', function () {
      var $select = $(this);

      t[$select.val() === '' ? 'removeClass' : 'addClass']('form--grid-on');
    }).change();

    $('select[name$="[responsive_image_style]"]', t).on('change', function () {
      var $select = $(this);
      t[$select.val() === '' ? 'removeClass' : 'addClass']('form--responsive-image-on');
    }).change();

    $('select[name$="[media_switch]"]', t).on('change', function () {
      var $select = $(this);
      var value = $select.val();

      t.removeClass(function (index, css) {
        return (css.match(/(^|\s)form--media-switch-\S+/g) || []).join(' ');
      });

      t[value === '' ? 'removeClass' : 'addClass']('form--media-switch-' + value);
      var nobox = (value === '' || value === 'content' || value === 'media' || value === 'rendered');
      t[nobox ? 'removeClass' : 'addClass']('form--media-switch-lightbox');
    }).change();

    t.on('mouseenter touchstart', '.b-hint', function () {
      $(this).closest('.form-item').addClass('is-hovered');
    });

    t.on('mouseleave touchend', '.b-hint', function () {
      $(this).closest('.form-item').removeClass('is-hovered');
    });

    t.on('click', '.b-hint', function () {
      $('.form-item.is-selected', t).removeClass('is-selected');
      $(this).parent().toggleClass('is-selected');
    });

    t.on('click', '.description, .form-item__description', function () {
      $(this).closest('.is-selected').removeClass('is-selected');
    });

    t.on('focus', '.js-expandable', function () {
      $(this).parent().addClass('is-focused');
    });

    t.on('blur', '.js-expandable', function () {
      $(this).parent().removeClass('is-focused');
    });
  }

  /**
   * Blazy admin tooltip function.
   *
   * @param {HTMLElement} elm
   *   The Blazy form item description HTML element.
   */
  function blazyTooltip(elm) {
    var $tip = $(elm);

    // Claro removed description for BEM form-item__description.
    if (!$tip.hasClass('description')) {
      $tip.addClass('description');
    }

    if (!$tip.siblings('.b-hint').length) {
      $tip.closest('.form-item').append('<span class="b-hint">?</span>');
    }
  }

  /**
   * Blazy admin checkbox function.
   *
   * @param {HTMLElement} elm
   *   The Blazy form item checkbox HTML element.
   */
  function blazyCheckbox(elm) {
    var $elm = $(elm);
    if (!$elm.next('.field-suffix').length) {
      $elm.after('<span class="field-suffix"></span>');
    }
  }

  /**
   * Attaches Blazy form behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyAdmin = {
    attach: function (context) {

      context = _db.context(context);

      once('blazy-tooltip', '.description, .form-item__description', context).forEach(blazyTooltip);
      once('blazy-checkbox', '.form-checkbox', context).forEach(blazyCheckbox);
      once('blazy-admin', '.form--slick', context).forEach(blazyForm);
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        once.filter('blazy-tooltip').remove();
        once.filter('blazy-checkbox').remove();
        once.filter('blazy-admin').remove();
      }
    }
  };

})(jQuery, Drupal, once, dBlazy);
