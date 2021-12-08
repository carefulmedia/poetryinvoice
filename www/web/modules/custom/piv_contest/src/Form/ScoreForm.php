<?php

namespace Drupal\piv_contest\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\piv_contest_recitation\RecitationInterface;
use Drupal\piv_contest_judging_session\JudgingSessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a Score Form form.
 */
class ScoreForm extends FormBase {

  /**
   * The score form builder service.
   *
   * @var \Drupal\piv_contest\ScoreFormBuilder
   */
  protected $scoreFormBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create($container) {
    $form = new static();
    $form->scoreFormBuilder = $container->get('piv_contest.score_form_builder');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piv_contest_score';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, RecitationInterface $recitation = NULL, JudgingSessionInterface $judging_session = NULL) { 
    if (!$recitation || !$judging_session) {
      throw new NotFoundHttpException();
    }
    $score_template = $judging_session->field_competition->entity->field_score_template->entity;
    if (!$score_template) {
      // This field is required.
      throw new NotFoundHttpException();
    }

    $form['recitation'] = [
      '#type' => 'value',
      '#value' => $recitation,
    ];
    $form['judging_session'] = [
      '#type' => 'value',
      '#value' => $judging_session,
    ];
    $form['score_template_form'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ] + $this->scoreFormBuilder->getForm($score_template);
    
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save score'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('The score has been created.'));
    $values = $form_state->getValues();
    $judging_session = $values['judging_session'];
    $recitation = $values['recitation'];
    $score_template_form_values = $values['score_template_form'];
    $result = $this->scoreFormBuilder
      ->createScore($recitation, $judging_session, $score_template_form_values);
  }

}
