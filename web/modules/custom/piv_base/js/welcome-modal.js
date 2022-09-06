(function($, Drupal) {
  Drupal.behaviors.welcome_modal = {
    attach: function(context, settings) {
      const welcome_modal = document.getElementById('welcome-modal');
      if (!welcome_modal) {
        return;
      }

      // Polyfill for <dialog>.
      dialogPolyfill.registerDialog(welcome_modal);

      $('.open-welcome-modal', context).click(function() {
        welcome_modal.showModal();
      });
      $('.close-welcome-modal', context).click(function() {
        welcome_modal.close();
      });

      $(once('welcome-modal-back', '.welcome-modal-back')).click(function(e) {
        $('#welcome-modal-content').html('');
        $('.piv-base-welcome-modal').fadeIn();
      });

      const show = (element) => element.removeClass('select-hidden');
      const hide = (element) => element.addClass('select-hidden');

      const option1 = $('select[name=option_1]', context);
      const option2 = $('select[name=option_2]', context);
      const option2_label = option2.parent().find('label').first();
      const option3 = $('select[name=option_3]', context);
      const submit = $('.js-form-submit', context);

      if (!option2.val() || option2.val() == '_null') {
        hide(option2.parent());
      }
      if (!option3.val() || option3.val() == '_null') {
        hide(option3.parent());
        hide(submit);
      }

      option1.add(option2).add(option3).each(function() {
        const select = $(this);
        select.parent().find('.piv-select-placeholder').click(function() {
          select.val('_null').change();
          $(this).text('');
        });
      });

      option1.change(function(e) {
        hide(submit);
        option2.val('_null').change();
        switch (this.value) {
          case 'student':
            show(option2.parent());
            option2_label.html(Drupal.t('in grades'));
            break;
          case 'teacher':
            show(option2.parent());
            option2_label.html(Drupal.t('teaching grades'));
            break;
          case 'poet':
          case 'parent_interested_person':
            hide(option2.parent());
            show(option3.parent());
            break;
          default:
            hide(option2.parent());
            hide(option3.parent());
            break;
        }
      });

      option2.change(function(e) {
        hide(submit);
        option3.val('_null').change();
        switch (this.value) {
          case '_null':
            hide(option3.parent());
            break;
          default:
            show(option3.parent());
            break;
        }
      });

      option3.change(function(e) {
        switch (this.value) {
          case '_null':
            hide(submit);
            break;
          default:
            show(submit);
            break;
        }
      });

    }
  };
})(jQuery, Drupal);
