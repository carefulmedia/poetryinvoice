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

      const updateRankValues = () => {
        document.querySelectorAll('.paragraph-type--competition-notification-level').forEach((level) => {
          level.querySelectorAll('.paragraph-type--competition-notification-rank').forEach((rank, delta) => {
            const input = rank.querySelector('input[name*="[field_rank]"]');
            if (input) {
              input.value = (delta + 1);
              input.readOnly = true;
            }
          });
        });
      };

      // Update rank values after tabledrag reorder.
      once('rank-drag', '.paragraph-type--competition-notification-level .field--name-field-ranks table', context).forEach((table) => {
        const observer = new MutationObserver(() => updateRankValues());
        observer.observe(table.querySelector('tbody'), { childList: true, subtree: true });
      });

      // Listen to change events.
      once('copylabels', '[type="text"][name^="field_competition_levels"]', context).forEach((el) => {
        el.addEventListener('change', e => updateTitles());
      });
      // Run on start or if the form is reloaded by some ajax.
      if (context == document || context.tagName == 'FORM') {
        updateTitles();
        updateRankValues();
      }
    }
  };
  
})(jQuery, Drupal, once, drupalSettings);
