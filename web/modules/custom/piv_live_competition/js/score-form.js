(function($, Drupal) {

  "use strict";

  Drupal.behaviors.pivLiveCompetitionScoreForm = {
    attach: function(context, settings) {

      const enable_button = (wrapper) => {
        $(wrapper).find('a.is-disabled')
          .removeClass('is-disabled')
          .text(Drupal.t('Next poem'));
      };

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
              location.reload();
              clearInterval(timer);
            }
          });
        };
        timer = setInterval(update, 5000);
      });

    }
  };

})(jQuery, Drupal);
