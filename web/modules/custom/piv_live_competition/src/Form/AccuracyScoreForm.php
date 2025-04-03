<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\piv_contest_score\ScoreInterface;
use Drupal\Paragraphs\ParagraphInterface;
use Drupal\Core\Url;

/**
 * Provides a PIV Live Competition form.
 */
final class AccuracyScoreForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'piv_live_competition_acurracy_score';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?ParagraphInterface $recitation = NULL,
    ?ScoreInterface $score_entity = NULL,
    bool $can_score_next_recitation = FALSE,
    bool $is_last_recitation = FALSE,
  ): array {

    $form['#attributes']['class'][] = 'piv-contest-score';
    $is_locked = $score_entity->field_locked->value == 1;
    $is_new = $score_entity->isNew();

    $form['recitation'] = [
      '#type' => 'value',
      '#value' => $recitation,
    ];

    $form['score_entity'] = [
      '#type' => 'value',
      '#value' => $score_entity,
    ];

    $form['show_message'] = [
      '#type' => 'value',
      '#value' => !$can_score_next_recitation && !$is_last_recitation,
    ];

    $form['score'] = [
      '#type' => 'radios',
      '#options' => array_combine(range(1, 8), range(1, 8)),
      '#required' => TRUE,
      '#default_value' => $score_entity->field_scores->value ?? NULL,
      '#title' => $this->t('Accuracy score'),
      '#disabled' => $is_locked,
    ];

    $form['navigation'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['score-controller__navigation'],
      ],
    ];

    // Display the submit button if can submit, otherwise a link to
    // refresh the page.
    if ($is_new) {
      $form['navigation']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit score'),
        '#attributes' => [
          'class' => [
            'score-controller__navigation__next',
          ],
        ],
      ];
    }
    else {
      $label = $can_score_next_recitation
        ? $this->t('Next poem')
        : $this->t('Waiting for next poem');
      $form['navigation']['next'] = [
        '#type' => 'link',
        '#url' => Url::fromRoute('<current>'),
        '#title' => $label,
        '#attributes' => [
          'class' => [
            'button',
            'score-controller__navigation__next',
          ],
        ],
      ];
      if (!$can_score_next_recitation) {
        $form['navigation']['next']['#attributes']['class'][] = 'is-disabled';
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $score_value = $form_state->getValue('score');
    $score_entity = $form_state->getValue('score_entity');
    $recitation = $form_state->getValue('recitation');
    $score_entity->field_locked = 1;
    $is_new = $score_entity->isNew();
    $score_entity->field_scores = $score_value;
    $score_entity->save();

    $show_message = $form_state->getValue('show_message', TRUE);
    if ($show_message) {
      $this->messenger()
        ->addMessage($this->t('Your score for this recitation has been saved. Please get ready to score the next recitation once the next round begins!'));
    }

    if ($is_new) {
      $recitation->field_accuracy_scores->appendItem($score_entity->id());
      $recitation->save();
    }
  }

}
