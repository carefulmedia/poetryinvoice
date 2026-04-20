(function ($, Drupal, once, settings) {
  'use strict';

  Drupal.behaviors.competitionsAdmin = {
    attach: function (context) {

      const updateTitles = () => {
        // Get an array of labels from the levels.
        var levels = [];
        const inputs = document.querySelectorAll('[type="text"][name^="field_competition_levels"]');
        inputs.forEach(function(el) {
          if (el.value.trim() != '') levels.push(el.value); 
        });
        
        // Update them in the notifications paragraphs.
        document.querySelectorAll('.js-notification-label').forEach(function(el, i) {
          if (levels.length > i) {
            el.innerHTML = `<h3>${levels[i]}</h3>`;
          }
          else {
            el.innerHTML = '';
          }
        });
      };

      // Listen to change events.
      once('copylabels', '[type="text"][name^="field_competition_levels"]', context).forEach((el) => {
        el.addEventListener('change', e => updateTitles());
      });
      // Run on start or if the form is reloaded by some ajax.
      if (context == document || context.tagName == 'FORM') {
        updateTitles();
      }
    }
  };
  
})(jQuery, Drupal, once, drupalSettings);
