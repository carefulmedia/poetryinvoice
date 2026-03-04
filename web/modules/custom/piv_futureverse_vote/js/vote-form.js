(function($, Drupal, once) {
  Drupal.behaviors.vote_form = {
    attach: function(context, settings) {
      once('vote-form-dialog', document.body).forEach(function() {
        window.addEventListener('dialog:aftercreate', function(e) {
          if (!window.visualViewport) return;

          var uiDialog = $(e.target).closest('.ui-dialog')[0];
          if (!uiDialog) return;

          function updatePosition() {
            uiDialog.style.top = window.visualViewport.offsetTop + 'px';
            uiDialog.style.height = window.visualViewport.height + 'px';
          }

          // Core's resize.dialogResize handler uses $(window).height() which
          // miscalculates top on mobile when the virtual keyboard opens/closes.
          // It's debounced at 20ms, so we wait 30ms to remove it after its
          // initial run and replace it with our visualViewport handler.
          setTimeout(function() {
            $(window).off('resize.dialogResize scroll.dialogResize');
            $(document).off('drupalViewportOffsetChange.dialogResize');
            updatePosition();
            window.visualViewport.addEventListener('resize', updatePosition);
          }, 30);
        });
      });
    }
  };
})(jQuery, Drupal, once);
