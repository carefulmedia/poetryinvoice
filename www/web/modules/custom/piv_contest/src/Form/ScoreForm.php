<?php

namespace Drupal\piv_contest\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
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
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    if (!$node || $node->bundle() != 'competition') {
      throw new NotFoundHttpException();
    }
    
    $score_template = $node->field_score_template->entity;
    if (!$score_template) {
      // This field is required.
      return FALSE;
    }
    
    // Node is a competition node.
    $form['node'] = [
      '#type' => 'value',
      '#value' => $node,
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
    $this->messenger()->addStatus($this->t('The message has been sent.'));
    $values = $form_state->getValues();
    $node = $values['node'];
    $score_template_form_values = $values['score_template_form'];
    $result = $this->scoreFormBuilder
      ->createScore($node, $score_template_form_values);
  }

}
