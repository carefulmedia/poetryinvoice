<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\piv_contest_score\ScoreInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\piv_contest\ScoreFormBuilder;
use Drupal\piv_contest_score_template\ScoreTemplateInterface;
use Drupal\Core\Url;
use Drupal\Paragraphs\ParagraphInterface;

/**
 * Provides a PIV Live Competition form.
 */
final class PerformanceScoreForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected readonly ScoreFormBuilder $scoreFormBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('piv_contest.score_form_builder'),
    );
  }

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
    ?Url $previous = NULL,
    ?Url $next = NULL,
  ): array {
    if (!$score_template) {
      return $form;
    }

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

    $values = array_column($score_entity?->field_scores->getValue() ?? [], 'value');
    $form['score_template_form'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ] + $this->scoreFormBuilder->getForm($score_template, $values);
    // Disable if locked.
    if ($is_locked) {
      foreach ($form['score_template_form']['criteria'] as &$criteria) {
        $criteria['#disabled'] = TRUE;
      }
    }

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

    if ($is_new) {
      $recitation->field_performance_scores->appendItem($score_entity->id());
      $recitation->save();
    }
  }

}
