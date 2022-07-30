/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.lvp_barrio = {
    attach: function (context, settings) {

    // Get URL of image in the header image node's image field.
    var imgSrc = $('.highlighted img').attr('src');

    // Set background image of parent block to this image URL.
    $('.site-footer').css('background-image', 'url(' + imgSrc + ')');
    }
  };

})(jQuery, Drupal);
