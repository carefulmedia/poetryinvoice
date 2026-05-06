<?php

namespace Drupal\piv_futureverse\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormState;

/**
 * Controller for journal poems.
 */
class JournalPoemsController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke() {
    $build = [];
    $form_state = (new FormState())
      ->setMethod('get')
      ->setAlwaysProcess()
      ->disableRedirect();
    $build['form'] = $this->formBuilder()
      ->buildForm('Drupal\piv_futureverse\Form\JournalPoemsForm', $form_state);
    return $build;
  }

}
