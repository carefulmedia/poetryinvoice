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
            let scrollRef = 0;
        // Assistance for Animate On Scroll issue not working properly
        window.addEventListener('scroll', function() {
          // increase value up to 10, then refresh AOS
          scrollRef <= 10 ? scrollRef++ : AOS.refresh();
        });
    }
  };

})(jQuery, Drupal);
