(function ($, Drupal, once) {
  Drupal.behaviors.disable_link = {
    attach: function (context, settings) {
      $('a.disabled', context).click((event) => {
        event.preventDefault();
      })
    }
  };
})(jQuery, Drupal, once);
