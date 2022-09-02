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
    //    $(".site-footer").hide();        
        var imgSrc = $('.highlighted img').attr('src');

        // Set background image of parent block to this image URL.
        $('.site-footer').css('background-image', 'url(' + imgSrc + ')');
       	     let scrollRef = 0;
        $( ".site-footer" ).fadeIn( 1500 );
        // Assistance for Animate On Scroll issue not working properly
        window.addEventListener('scroll', function() {
          // increase value up to 10, then refresh AOS
          scrollRef <= 10 ? scrollRef++ : AOS.refresh();
        });
    }
          // collect all the divs
    var divs = document.querySelectorAll(".mixtape-poem-name a");
    // get window width and height
    var winWidth = $(".views-field-field-mixtape-image").width() - 400;
    var winHeight = $(".views-field-field-mixtape-image").height() - 15;
    var spacer= winHeight / divs.length;
    var randomTop = spacer;
    for (var i = 0; i < divs.length; i++) {

      // shortcut! the current div in the list
      var thisDiv = divs[i];
      randomLeft = getRandomNumber(15, winWidth);
      thisDiv.style.top = randomTop + "px";
      thisDiv.style.left = randomLeft + "px";
      // get random numbers for each element
      randomTop = randomTop + getRandomNumber(10, spacer);


      // update top and left position

    // $(thisDiv).fadeIn(css( "opacity", "1"));
      $( thisDiv ).addClass( "fade-in" );
    }
    // function that returns a random number between a min and max
    function getRandomNumber(min, max) {

      return Math.random() * (max - min) + min;

    }

})(jQuery, Drupal);
