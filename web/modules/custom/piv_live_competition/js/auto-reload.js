(function($, Drupal) {

  "use strict";

  Drupal.behaviors.pivLiveCompetitionAutoReload = {
    attach: function(context, settings) {

      once('auto-reload', '.monitor-dashboard', context).forEach(function(el) {
        const reload = () => {
          const animation_time = 1000;

          // Fix the wrapper min height to prevent flickering, if the
          // table gets taller in the next call then the min height gets
          // updated too.
          const wrapper = $('#monitor-dashboard-wrapper');
          wrapper.css('min-height', wrapper.height());

          // Clone the content, the "once" is already processing what
          // we want to clone.
          const clone = $(el).css('position', 'absolute');
          wrapper.prepend(clone);
          setTimeout(() => { clone.remove(); }, animation_time * 1.1);

          // Using the html method prevents us from replacing the
          // wrapper, which is not returned from ajax calls in this
          // case.
          let ajax = Drupal.ajax({
            httpMethod: 'GET',
            url: Drupal.url(drupalSettings.path.currentPath),
            wrapper: 'monitor-dashboard-table-wrapper',
            method: 'replaceWith',
            effect: 'fade',
            speed: animation_time,
          });
          ajax.execute();
        };
        // Reload 5 seconds after loading, it gets reattached on every
        // ajax load.
        setTimeout(reload, 5000);
      });

      // Poll the round API endpoint once on initial page load only,
      // to track the active round and show a modal if it auto-advances.
      once('round-poller', 'body', context).forEach(function() {
        const apiUrl = drupalSettings.pivLiveCompetition?.roundApiUrl;
        if (!apiUrl) {
          return;
        }

        let lastKnownRound = null;

        const pollRound = () => {
          $.getJSON(apiUrl, function(data) {
            const round = data.active_round ?? 0;
            if (lastKnownRound !== null && round > lastKnownRound) {
              const content = '<p>' + Drupal.t('Cue reciter # @round to enter the stage', {'@round': round}) + '</p>'
                + '<strong>' + (data.student || '') + '</strong><br>'
                + (data.school || '') + '<br>'
                + '<em>' + (data.poem || '') + '</em><br>';
              Drupal.dialog($('<div>' + content + '</div>')[0], {
                title: Drupal.t('JUDGES READY'),
                width: 400,
                classes: { 'ui-dialog': 'piv-round-advanced-dialog' },
                buttons: [{
                  text: Drupal.t('OK'),
                  click: function() { $(this).dialog('close'); },
                }],
              }).showModal();
            }
            lastKnownRound = round;
          }).always(function() {
            setTimeout(pollRound, 5000);
          });
        };

        pollRound();
      });

    }
  };

})(jQuery, Drupal);
