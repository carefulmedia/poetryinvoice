<?php

namespace Drupal\piv_base\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormState;

/**
 * Controller for School Visits Summary report.
 */
class SchoolVisitsSummaryController extends ControllerBase {

  /**
   * Builds the school visits summary page.
   *
   * @return array
   *   A render array.
   */
  public function build() {
    $form_state = (new FormState())
      ->setMethod('get')
      ->setAlwaysProcess()
      ->disableRedirect();
    // Build the filter form which also handles displaying results.
    $form = $this->formBuilder()->buildForm('Drupal\piv_base\Form\SchoolVisitsSummaryFilterForm', $form_state);

    $build = [
      'form' => $form,
      '#cache' => [
        'tags' => ['node_list:pal_pir_school_visit'],
        'max-age' => 3600,
      ],
    ];

    return $build;
  }

}
