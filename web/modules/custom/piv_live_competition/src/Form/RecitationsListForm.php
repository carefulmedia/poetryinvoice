<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\piv_live_competition\Helper;
use Drupal\node\NodeInterface;

/**
 * Provides a PIV Live Competition form.
 */
final class RecitationsListForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected readonly Helper $helper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('piv_live_competition.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'piv_live_competition_recitations_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $streams = $this->helper->getStreams();
    // This is a form but there is no filtering (yet).
    $recitations = $node ? $this->helper->getRecitationsInOrder($node) : [];
    $form['table'] = [
      '#type' => 'table',
      '#header' => [
        'team_regional_entry' => $this->t('Team Regional Entry'),
        'stream' => $this->t('Stream'),
        'student' => $this->t('Student'),
        'poem' => $this->t('Poem'),
        'school' => $this->t('School'),
        'order' => $this->t('Order'),
        'performance_scores' => $this->t('Performance scores'),
        'accuracy_scores' => $this->t('Accuracy scores'),
      ],
      '#rows' => [],
      '#empty' => $this->t('No recitations for this competition yet.'),
    ];

    foreach ($recitations as $recitation) {
      $performance_scores = [];
      foreach ($recitation->field_performance_scores->referencedEntities() as $score) {
        $label = $score->judge->entity?->getDisplayName() ?? $score->label();
        $link = $score->toLink($label, 'edit-form')->toRenderable() + [
          '#suffix' => '<br>',
          '#prefix' => '· ',
          '#attributes' => ['target' => '_blank'],
        ];
        $performance_scores[] = $link;
      }
      $accuracy_scores = [];
      foreach ($recitation->field_accuracy_scores->referencedEntities() as $score) {
        $label = $score->judge->entity?->getDisplayName() ?? $score->label();
        $link = $score->toLink($label, 'edit-form')->toRenderable() + [
          '#suffix' => '<br>',
          '#prefix' => '· ',
          '#attributes' => ['target' => '_blank'],
        ];
        $accuracy_scores[] = $link;
      }

      $team_regional_entry = $recitation->getParentEntity();
      $stream = $team_regional_entry->field_language_stream->value;
      $school_label = $team_regional_entry ?
        $team_regional_entry->field_team_label?->value ?? $team_regional_entry->getOwner()?->field_school->entity?->label()
        : '';
      $school = $school_label;
      $form['table'][] = [
        'team_regional_entry' => $team_regional_entry
          ? $team_regional_entry->toLink(NULL, 'edit-form')->toRenderable()
          : '',
        'stream' => ['#markup' => $streams[$stream] ?? $this->t('- None -')],
        'student' => ['#markup' => $this->helper->getStudentName($recitation)],
        'poem' => ['#markup' => $recitation->field_poem->entity?->label()],
        'school' => ['#markup' => $school],
        'order' => ['#markup' => $recitation->field_recitation_order->value],
        'performance_scores' => $performance_scores,
        'accuracy_scores' => $accuracy_scores,
      ];
    }

    return $form;
  }

  /**
   * Return a generated title.
   */
  public function title(NodeInterface $node) {
    return $this->t('Recitations List - @label', [
      '@label' => $node->label(),
    ]);
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
