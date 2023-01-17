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
    }    
  };

  // Hook on tabledrag drop.
  const original = Drupal.tableDrag.prototype.onDrop;
  Drupal.tableDrag.prototype.onDrop = function () {
    const result = original();
    if (this.changed) {
      $('button[name=save-weight]').first().click();
    }
    return result;
  }
  
})(jQuery, Drupal);

