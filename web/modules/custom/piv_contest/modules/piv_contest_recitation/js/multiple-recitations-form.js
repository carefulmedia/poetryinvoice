(function($, Drupal) {
  Drupal.behaviors.multiple_recitations_form = {
    attach: function(context, settings) {
      $('dialog[ajax-open]', context).first().each(function() {
        dialogPolyfill.registerDialog(this);
        this.showModal();
      });
      $('dialog.recitation-form', context).each(function() {
        const dialog = this;
        // Polyfill for <dialog>.
        dialogPolyfill.registerDialog(dialog);
        $('.close-modal', dialog).click(function(e) {
          e.preventDefault();
          dialog.close();
        });
      });
      $('.recitation-open-modal', context).click(function(e) {
        e.preventDefault();
        const $dialog = $(this).parent().find('dialog.recitation-form');
        if ($dialog.length > 0) {
          $dialog[0].showModal();
        }
      });
      
      // Copy weight to entity form.
      $('form.competition-entry-form', context).submit(function(e) {
        let values = {};
        $('.piv-contest-recitation-multiple-recitations select.table-sort-weight').each(function() {
          values[this.name] = this.value;
        });
        $('[name="recitation_weight"]', $(this)).val(JSON.stringify(values));
      });
    }    
  };
 
})(jQuery, Drupal);

