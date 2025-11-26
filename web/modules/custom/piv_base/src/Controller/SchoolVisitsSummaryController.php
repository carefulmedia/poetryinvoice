<?php

namespace Drupal\piv_base\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for School Visits Summary report.
 */
class SchoolVisitsSummaryController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a SchoolVisitsSummaryController object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * Builds the school visits summary page.
   *
   * @return array
   *   A render array.
   */
  public function build() {
    // Build the filter form which also handles displaying results
    $form = $this->formBuilder->getForm('Drupal\piv_base\Form\SchoolVisitsSummaryFilterForm');

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