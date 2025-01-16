/**
 * @file
 * Global utilities.
 *
 */
 (function ($, Drupal) {

  'use strict';

  Drupal.behaviors.lvp_barrio = {
    attach: function (context, settings) {
      // Prevent running twice.
        if (once('footer-bg', 'body', context).length == 0) {
          return;
        }

        // Change logo for k-6
        if ($("body").hasClass("elementary")) {
          $('.print-icon').attr('src','/themes/custom/lvp_barrio/images/print-icon.svg');
          if (window.location.href.indexOf("lesvoix") > -1) { 
              $('.header-logo').attr('src','/themes/custom/lvp_barrio/images/LVP-elementary-logo.svg');
              $('.me-auto').attr('href','/primaire');
              $('.dive-in-label').html('Écris');
              
            }
          else {
            $('.header-logo').attr('src','/themes/custom/lvp_barrio/images/PIV-elementary-logo.svg');
            $('.me-auto').attr('href','/elementary');
            $('.dive-in-label').html('Write');

          }
        }       
        else {
          // Get URL of image in the header image node's image field, if
          // it is using picture, get the smallest image since there is
          // blur.
          $(".site-footer").hide(); 
          
          var imgSrc = null;       
          var picture = $('.highlighted picture').first();
          if (picture.length) {
            var smallest = Infinity;
            for (var source of picture.find('source')) {
              if (source.width && source.width < smallest) {
                imgSrc = source.srcset.split(' ').shift().trim();
              }
            }
          }
          else {
            imgSrc = $('.highlighted img').attr('src');
          }
          
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
