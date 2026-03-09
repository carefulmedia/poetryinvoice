<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\piv_contest_score\ScoreInterface;
use Drupal\piv_contest\ScoreFormBuilder;
use Drupal\piv_contest_score_template\ScoreTemplateInterface;
use Drupal\Paragraphs\ParagraphInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\piv_live_competition\Helper;

/**
 * Provides a PIV Live Competition form.
 */
final class PerformanceScoreForm extends FormBase {
  use AutowireTrait;

  public function __construct(
    protected readonly ScoreFormBuilder $scoreFormBuilder,
    protected readonly Helper $helper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'piv_live_competition_score';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?ParagraphInterface $recitation = NULL,
    ?ScoreTemplateInterface $score_template = NULL,
    ?ScoreInterface $score_entity = NULL,
    bool $can_score_next_recitation = FALSE,
    bool $is_last_recitation = FALSE,
    ?NodeInterface $competition = NULL,
  ): array {
    if (!$score_template) {
      return $form;
    }

    $form['#attributes']['class'][] = 'piv-contest-score';
    $is_locked = $score_entity->field_locked->value == 1;
    $is_new = $score_entity->isNew();

    $form['score_template'] = [
      '#type' => 'value',
      '#value' => $score_template,
    ];

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

    $form['competition'] = [
      '#type' => 'value',
      '#value' => $competition,
    ];

    if (!$is_locked) {
      $values = array_column($score_entity?->field_scores->getValue() ?? [], 'value');
      $form['score_template_form'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ] + $this->scoreFormBuilder->getForm($score_template, $values);
      $form['score_template_form']['#theme'] = 'recitation_score_form_default__live_competition';
      // Disable if locked.
      if ($is_locked) {
        foreach ($form['score_template_form']['criteria'] as &$criteria) {
          $criteria['#disabled'] = TRUE;
        }
      }
    }

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
    $values = $form_state->getValue('score_template_form');
    $score_template = $form_state->getValue('score_template');
    $score_entity = $form_state->getValue('score_entity');
    $recitation = $form_state->getValue('recitation');

    $score_form_builder_plugin_instance = $this->scoreFormBuilder
      ->getInstance($score_template);
    $score_form_builder_plugin_instance->save($score_entity, $values);
    $score_entity->field_locked = 1;
    $is_new = $score_entity->isNew();
    $score_entity->save();

    $show_message = $form_state->getValue('show_message', TRUE);
    if ($show_message) {
      $this->messenger()
        ->addMessage($this->t('Your score for this recitation has been saved.'));
    }

    if ($is_new) {
      $recitation->field_performance_scores->appendItem($score_entity->id());
      $recitation->save();
    }

    $competition = $form_state->getValue('competition');
    if ($competition) {
      $this->helper->maybeAutoAdvanceRound($competition);
    }
  }

}
