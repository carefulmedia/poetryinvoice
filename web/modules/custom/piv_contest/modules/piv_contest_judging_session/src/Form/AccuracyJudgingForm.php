<?php

namespace Drupal\piv_contest_judging_session\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;

/**
 * Provides a PIV Contest Judging Session form.
 */
class AccuracyJudgingForm extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The current route.
   *
   * @var Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CurrentRouteMatch $current_route_match) {
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $current_route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piv_contest_judging_session_accuracy_judging';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $recitations = []) {
    $form['#tree'] = TRUE;
    $view_builder = $this->entityTypeManager->getViewBuilder('recitation');
    foreach ($recitations as $recitation) {
      $form['recitations'][$recitation->id()] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['accuracy-judging__recitation-container'],
        ],
        'view_recitation' => $view_builder->view($recitation, 'accuracy_judging'),
        'score' => [
          '#type' => 'radios',
          '#options' => array_combine(range(1, 8), range(1, 8)),
          '#required' => TRUE,
          '#default_value' => $recitation->field_score->value ?? NULL,
          '#title' => $this->t('Accuracy score'),
        ],
        'recitation' => [
          '#type' => 'value',
          '#value' => $recitation,
        ],
      ];
    }
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Nothing to validate.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $scores = $form_state->getValue('recitations');
    foreach ($scores as $data) {
      $score = $data['score'];
      $recitation = $data['recitation'];
      $recitation->field_score = $score;
      $recitation->save();
    }

    $user = $this->routeMatch->getRawParameter('user');
    $judging_session = $this->routeMatch->getRawParameter('judging_session');
    if ($user && $judging_session) {
      $form_state->setRedirect('piv_contest_judging_session.judge_for_accuracy.recitations_list', [
        'user' => $user,
        'judging_session' => $judging_session,
      ]);
    }
  }

}
