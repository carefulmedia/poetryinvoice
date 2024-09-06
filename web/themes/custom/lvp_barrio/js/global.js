/**
 * @file
 * Global utilities.
 *
 */
 (function ($, Drupal) {

  'use strict';

  Drupal.behaviors.lvp_barrio = {
    attach: function (context, settings) {
        // Change logo for k-6
        if ($("body").hasClass("elementary")) {
          $('.print-icon').attr('src','/themes/custom/lvp_barrio/images/print-icon.svg');
          if (window.location.href.indexOf("lesvoix") > -1) { 
              $('.header-logo').attr('src','/themes/custom/lvp_barrio/images/LVP-elementary-logo.svg');
              $('.me-auto').attr('href','/primaire');

            }
          else {
            $('.header-logo').attr('src','/themes/custom/lvp_barrio/images/PIV-elementary-logo.svg');
            $('.me-auto').attr('href','/elementary');
          }
        }       
        else {
        // Get URL of image in the header image node's image field.
        $(".site-footer").hide();        
        var imgSrc = $('.highlighted img').attr('src');
        // Set background image of parent block to this image URL.
        $('.site-footer').css('background-image', 'url(' + imgSrc + ')');
        $( ".site-footer" ).fadeIn( 1500 );
        }
       // Assistance for Animate On Scroll issue not working properly
        let scrollRef = 0;
        window.addEventListener('scroll', function() {
          // increase value up to 10, then refresh AOS
          scrollRef <= 10 ? scrollRef++ : AOS.refresh();
        });
    }
  };

})(jQuery, Drupal);
