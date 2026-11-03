(function (Drupal, once) {
  /**
   * Show a Bootstrap modal, or fall back to manual display if Bootstrap JS is missing.
   */
  function showSubmissionModal(element) {
    const Modal = window.bootstrap?.Modal;
    if (Modal) {
      Modal.getOrCreateInstance(element).show();
      return;
    }

    element.classList.add('show');
    element.style.display = 'block';
    element.removeAttribute('aria-hidden');
    element.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');

    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show';
    backdrop.setAttribute('data-piv-submission-modal-backdrop', 'true');
    document.body.appendChild(backdrop);

    const close = () => {
      element.classList.remove('show');
      element.style.display = '';
      element.setAttribute('aria-hidden', 'true');
      element.removeAttribute('aria-modal');
      document.body.classList.remove('modal-open');
      backdrop.remove();
    };

    element.querySelectorAll('[data-bs-dismiss="modal"], .btn-close').forEach((btn) => {
      btn.addEventListener('click', close, { once: true });
    });
  }

  Drupal.behaviors.pivFutureverseSubmissionModal = {
    attach(context) {
      once('piv-futureverse-submission-modal', '#piv-futureverse-submission-modal', context).forEach((element) => {
        showSubmissionModal(element);
      });
    },
  };
})(Drupal, once);
