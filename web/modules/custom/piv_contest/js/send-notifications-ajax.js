(function ($) {
  'use strict';

  $.fn.sendNotificationsRestoreState = function (buttonName) {
    if (!buttonName) {
      return this;
    }

    var $wrapper = this;

    // Find which level tab contains the triggering button.
    var $button = $wrapper.find('[name="' + buttonName + '"]');
    if (!$button.length) {
      return this;
    }

    var $details = $button.closest('details');
    while ($details.length && !$details.data('horizontalTab')) {
      $details = $details.parent().closest('details');
    }
    if ($details.length && $details.data('horizontalTab')) {
      $details.data('horizontalTab').focus();
    }

    // Scroll to button.
    setTimeout(function () {
      var el = $wrapper.find('[name="' + buttonName + '"]');
      if (el.length) {
        el[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }, 100);

    return this;
  };

})(jQuery);
