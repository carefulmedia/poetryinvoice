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

  // Hook on tabledrag drop.
  const original = Drupal.tableDrag.prototype.onDrop;
  Drupal.tableDrag.prototype.onDrop = function () {
    const result = original();
    if (this.changed) {
      $(this.$table).each(function() {
        $('.tabledrag-handle', this).each(function(it) {
          // Need to replace the text only, there is no tag so need to replace
          // the text node only.
          console.log($(this).parent().contents());
          $(this).parent().contents().filter(function() {
            return this.nodeType == Node.TEXT_NODE;
          }).each(function(){
            this.textContent = it + 1;
          });
        });
      });
    }
    return result;
  }

})(jQuery, Drupal);

