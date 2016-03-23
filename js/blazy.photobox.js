/**
 * @file
 * Provides Photobox integration for Image and Media fields.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.blazyPhotobox = {
    attach: function (context) {
      $('div[data-blazy]', context).once('blazy-photobox').each(function () {
        $(this).photobox('a[data-photobox]', {thumbAttr: 'data-thumb'});
      });
    }
  };

}(jQuery, Drupal));
