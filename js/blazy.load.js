/**
 * @file
 * Provides bLazy loader.
 */

/*jshint -W072 */
/*eslint max-params: 0 */
(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.blazy = {
    attach: function (context) {
      var blazy = new Blazy();
    }
  };

})(jQuery, Drupal);
