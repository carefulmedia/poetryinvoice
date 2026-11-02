(function (Drupal, once) {
  Drupal.behaviors.pivFutureverseSubmissionModal = {
    attach(context) {
      once('piv-futureverse-submission-modal', '#piv-futureverse-submission-modal', context).forEach((element) => {
        const Modal = window.bootstrap?.Modal;
        if (!Modal) {
          return;
        }
        Modal.getOrCreateInstance(element).show();
      });
    },
  };
})(Drupal, once);
