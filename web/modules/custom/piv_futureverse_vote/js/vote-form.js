(function(once) {
  once('futureverse-vote-dialog', document.body).forEach(function() {
    window.addEventListener('dialog:aftercreate', function(e) {
      if (e.target.querySelector('#futureverse-vote-form')) {
        e.target.closest('.ui-dialog').classList.add('futureverse-vote-dialog');
      }
    });
  });
})(once);
