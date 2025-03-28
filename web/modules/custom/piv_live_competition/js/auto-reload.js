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
    }
  };

})(jQuery, Drupal);
