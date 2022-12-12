(function($, Drupal) {
  Drupal.behaviors.multiple_recitations_form = {
    attach: function(context, settings) {
      $('.recitation-open-modal', context).click(function(e) {
        e.preventDefault();
        const $dialog = $(this).parent().find('dialog.recitation-form');
        if ($dialog.length > 0) {
          $dialog[0].showModal();
        }
      });
      $('dialog.recitation-form', context).each(function() {
        const dialog = this;
        $('.close-modal', dialog).click(function(e) {
          e.preventDefault();
          dialog.close();
        });
      });
    }
  };
})(jQuery, Drupal);
