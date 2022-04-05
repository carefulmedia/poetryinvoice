<?php

namespace Drupal\piv_contest_judging_session\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a PIV Contest Judging Session form.
 */
class AccuracyJudgingForm extends FormBase {

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
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('recitation');
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
    foreach ($scores as $recitation_id => $data) {
      $score = $data['score'];
      $recitation = $data['recitation'];
      $recitation->field_score = $score;
      $recitation->save();
    }
  }

}
