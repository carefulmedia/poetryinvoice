(function($, Drupal) {

  "use strict";

  Drupal.behaviors.pivLiveCompetitionAutoReload = {
    attach: function(context, settings) {

      once('auto-reload', '.monitor-dashboard', context).forEach(function() {
        const reload = () => {
          const animation_time = 1000;

          // Fix the wrapper min height to prevent flickering, if the
          // table gets taller in the next call then the min height gets
          // updated too.
          const wrapper = $('#monitor-dashboard-wrapper');
          wrapper.css('min-height', wrapper.height());

          // Clone the content.
          const clone = $('#monitor-dashboard-table-wrapper > .table-responsive')
            .css('position', 'absolute');
          clone.parent().before(clone);
          setTimeout(() => { clone.remove(); }, animation_time * 1.1);

          // Using the html method prevents us from replacing the
          // wrapper, which is not returned from ajax calls in this
          // case.
          let ajax = Drupal.ajax({
            url: Drupal.url(drupalSettings.path.currentPath + '/table'),
            wrapper: 'monitor-dashboard-table-wrapper',
            method: 'html',
            effect: 'fade',
            speed: animation_time,
            progress: { type: 'throbber', message: 'reloading' },
          });
          ajax.execute();
        };
        // Reload 5 seconds after loading, it gets reattached on every
        // ajax load.
        setTimeout(reload, 5000);
      });
    }
  };

})(jQuery, Drupal);
