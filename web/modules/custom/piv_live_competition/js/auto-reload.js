(function($, Drupal) {
  
  "use strict";
  
  Drupal.behaviors.pivLiveCompetitionAutoReload = {
    attach: function(context, settings) {
      once('auto-reload', '#monitor-dashboard-table', context).forEach(function() {
        const reload = () => {
          Drupal.ajax({
            url: Drupal.url(drupalSettings.path.currentPath),
            wrapper: 'monitor-dashboard-wrapper',
            progress: { type: 'throbber', message: 'reloading' },
            element: document.getElementById('monitor-dashboard-table'),
          }).execute();
        };
        // Reload 5 seconds after loading, it gets reattached on every
        // ajax load.
        setTimeout(reload, 5000);
      });
    }
  };

})(jQuery, Drupal);
