<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a PIV Live Competition form.
 */
final class BlankScoreForm extends FormBase {

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
    bool $can_score_next_recitation = FALSE,
    array $score_form = [],
  ): array {

    $form['#attributes']['class'][] = 'piv-contest-score';
    $form['score'] = $score_form;

    $form['navigation'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['score-controller__navigation'],
      ],
    ];

    $form['navigation']['next'] = [
      '#type' => 'link',
      '#url' => Url::fromRoute('<current>'),
      '#title' => $this->t('Waiting for next poem'),
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
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
