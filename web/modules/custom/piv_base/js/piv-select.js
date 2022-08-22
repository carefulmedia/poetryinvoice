(function($, Drupal) {
  Drupal.behaviors.piv_select = {
    attach: function(context, settings) {
      $('select.piv-select', context).each(function() {
        const $select = $(this);
        const piv_select = $(`<div></div>`)
          .addClass('piv-select');
        const piv_select_options = $(`<div></div>`)
          .addClass('piv-select-options')
        const piv_select_placeholder = $(`<div></div>`)
          .addClass('piv-select-placeholder')
          .appendTo(piv_select)
          .click(function () {
            if (piv_select_options.hasClass('hide')) {
              piv_select_options.removeClass('hide');
            }
          });
        piv_select_options.appendTo(piv_select);
        const selected = $(this).find(':selected');
        if (selected.length) {
          piv_select_placeholder.text(selected.text());
        }
        const update_placeholder = (text = null) => {
          if (text) {
            piv_select_placeholder.text(text);
            piv_select_options.addClass('hide');
          }
          else {
            piv_select_placeholder.text("");
            piv_select_options.removeClass('hide');
          }
        }
        $('option', $select).each(function() {
          const piv_select_option = $(`<div>${this.text}</div>`).click(() => {
            $(this).prop('selected', true).change();
            update_placeholder(this.text);
          })
          .addClass('piv-select-option')
          .appendTo(piv_select_options);
        });
        $select.after(piv_select);
        $select.hide();
      });
    }
  };
})(jQuery, Drupal);
