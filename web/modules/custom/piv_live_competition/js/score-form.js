(function($, Drupal) {

  "use strict";

  Drupal.behaviors.pivLiveCompetitionScoreForm = {
    attach: function(context, settings) {

      const enable_button = (wrapper) => {
        $(wrapper).find('a.is-disabled')
          .removeClass('is-disabled')
          .text(Drupal.t('Next poem'));
      };

      // Disable submit until all radio groups have a selection.
      once('score-submit-guard', '.piv-contest-score', context).forEach(function(el) {
        const $form = $(el).closest('form');
        const $submit = $form.find('input[type="submit"], button[type="submit"]');

        if (!$submit.length) {
          return;
        }

        const allAnswered = () => {
          const names = {};
          $form.find('input[type="radio"]').each(function() {
            const name = $(this).attr('name');
            if (!(name in names)) {
              names[name] = false;
            }
            if ($(this).is(':checked')) {
              names[name] = true;
            }
          });
          const groups = Object.values(names);
          return groups.length > 0 && groups.every(Boolean);
        };

        // Wrap the submit button so tippy works on disabled elements.
        $submit.wrap('<span class="score-controller__submit-wrapper"></span>');
        const $wrapper = $submit.parent();
        const tooltip = tippy($wrapper[0], {
          content: Drupal.t('You must complete all scores before you can submit.'),
          trigger: 'mouseenter',
        });

        const refresh = () => {
          const answered = allAnswered();
          $submit.prop('disabled', !answered);
          if (answered) {
            tooltip.disable();
          } else {
            tooltip.enable();
          }
        };

        $form.on('change', 'input[type="radio"]', refresh);
        refresh();
      });

      once('score-update', '.score-wrapper', context).forEach(function(el) {
        const endpoint = $(el).data('endpoint');
        const round = $(el).data('round');
        var timer = null;

        if ($(el).hasClass('is-locked')) {
          $('body').addClass('score-form-is-locked');
        }

        const update = () => {
          $.get(endpoint, function(data) {
            const active_round = data?.active_round || 0;
            if (active_round > round) {
              enable_button(el);
              location.replace(location.href);
              clearInterval(timer);
            }
          });
        };
        timer = setInterval(update, 5000);
      });

    }
  };

})(jQuery, Drupal);
