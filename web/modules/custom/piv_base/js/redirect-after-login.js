(function($, Drupal) {
  Drupal.behaviors.redirectAfterLogin = {
    attach: function(context, settings) {
      $(once('redirect-after-login', 'a[href="/user/login"]', context)).each(function() {
        const $a = $(this);
        const href = $a.attr('href');
        const uri = window.location.pathname;
        if (uri) { $a.attr('href', `${href}?destination=${uri}`); }
      });
    }
  };
})(jQuery, Drupal);
