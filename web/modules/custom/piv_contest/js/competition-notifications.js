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

      const showNewLevelMessage = () => {
        const table = document.querySelector('.field--name-field-competition-levels');
        if (!table) return;
        const id = 'new-level-notification-message';
        const inputs = table.querySelectorAll('input[type="text"][name^="field_competition_levels"]');
        const hasNewLevel = Array.from(inputs).some((input, i) => {
          const wasEmpty = input.dataset.originalValue === '';
          return wasEmpty && input.value.trim() !== '';
        });
        let message = document.getElementById(id);
        if (hasNewLevel && !message) {
          message = document.createElement('div');
          message.id = id;
          message.className = 'tabledrag-changed-warning messages messages--warning';
          message.textContent = Drupal.t('* You have unsaved changes. Save this competition to create notifications for the new level.');
          table.prepend(message);
        }
        else if (!hasNewLevel && message) {
          message.remove();
        }
      };

      // Track original values of level inputs.
      once('track-levels', '[type="text"][name^="field_competition_levels"]', context).forEach((el) => {
        if (!('originalValue' in el.dataset)) {
          el.dataset.originalValue = el.value.trim();
        }
        el.addEventListener('change', () => {
          updateTitles();
          showNewLevelMessage();
        });
      });
      // Run on start or if the form is reloaded by some ajax.
      if (context == document || context.tagName == 'FORM') {
        updateTitles();
        updateRankValues();
      }
    }
  };
  
})(jQuery, Drupal, once, drupalSettings);
