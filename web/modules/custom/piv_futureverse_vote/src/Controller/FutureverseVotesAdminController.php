<?php

namespace Drupal\piv_futureverse_vote\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormState;
use Drupal\piv_futureverse_vote\Form\FutureverseVotesAdminFilterForm;

/**
 * Provides an admin page to view futureverse votes.
 */
class FutureverseVotesAdminController extends ControllerBase {

  /**
   * Builds the admin page render array.
   */
  public function build(): array {
    // Build the filter form.
    $filter_form_state = (new FormState())
      ->setMethod('get')
      ->setAlwaysProcess()
      ->disableRedirect();
    $build['filter_form'] = $this->formBuilder()
      ->buildForm(FutureverseVotesAdminFilterForm::class, $filter_form_state);

    // Build the table form with the filter state.
    $table_form_state = (new FormState())
      ->addBuildInfo('args', [$filter_form_state]);
    $build['table_form'] = $this->formBuilder()
      ->buildForm('Drupal\piv_futureverse_vote\Form\FutureverseVotesAdminTableForm', $table_form_state);

    return $build;
  }

}
