<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\piv_contest_score\ScoreInterface;
use Drupal\Core\Url;
use Drupal\Paragraphs\ParagraphInterface;

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
    ?Url $previous = NULL,
    ?Url $next = NULL,
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

    // Previous can be null if on first item.
    if ($previous) {
      $form['navigation']['previous'] = [
        '#type' => 'link',
        '#url' => $previous,
        '#title' => $this->t('Previous'),
        '#attributes' => [
          'class' => [
            'button',
            'score-controller__navigation__previous',
          ],
        ],
      ];
    }

    $form['navigation']['next'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'score-controller__navigation__next',
        ],
      ],
    ];

    // Next will only be displayed if the score is saved and locked.
    if (!$is_new && $next) {
      // Next.
      $form['navigation']['next']['next'] = [
        '#type' => 'link',
        '#url' => $next,
        '#title' => $this->t('Next'),
        '#attributes' => [
          'class' => [
            'button',
          ],
        ],
      ];
    }
    if (!$is_locked) {
      $form['navigation']['next']['submit'] = [
        '#type' => 'submit',
        '#value' => $is_new ? $this->t('Submit score') : $this->t('Update score'),
      ];
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

    if ($is_new) {
      $recitation->field_accuracy_scores->appendItem($score_entity->id());
      $recitation->save();
    }
  }

}
