<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
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
  final public function __construct(
    protected readonly Helper $helper,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('piv_live_competition.helper'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Custom access.
   */
  public function access(AccountInterface $account, NodeInterface $node, ?string $stream = NULL): AccessResultInterface {
    $permission = $account->hasPermission('access competition recitations list');
    return AccessResult::allowedIf($permission)
      ->cachePerUser()
      ->addCacheableDependency($account);
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
    $form_state->set('node', $node);
    $streams = $this->helper->getStreams();
    // This is a form but there is no filtering (yet).
    $recitations = $node ? $this->helper->getRecitationsInOrder($node) : [];

    $competition_started = $node && $this->helper->hasCompetitionStarted($node);
    $form['generate_order'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate recitation order'),
      '#submit' => [[$this, 'generateOrder']],
      '#disabled' => $competition_started,
    ];
    if ($competition_started) {
      $form['competition_started_message'] = [
        '#markup' => '<p>' . $this->t('The competition has already started (active round: @round). The recitation order cannot be changed.', [
          '@round' => $node->field_active_round->value,
        ]) . '</p>',
      ];
    }
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
      // We put a guard clause on the entry, since the rest of this loop doesn't need to be executed if it doesn't exist.
      /** @var \Drupal\node\NodeInterface $team_regional_entry */
      $team_regional_entry = $recitation->getParentEntity();
      if (!$team_regional_entry) {
        continue;
      }

      // Ensure our entry is translated to the current language.
      $current_language = \Drupal::languageManager()->getCurrentLanguage()->getId();
      if ($team_regional_entry->hasTranslation($current_language)) {
        $team_regional_entry = $team_regional_entry->getTranslation($current_language);
      }

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

      $stream = $team_regional_entry?->field_language_stream->value;
      $school_label = _piv_live_competition_get_team_label($team_regional_entry, $current_language)
        ?? $team_regional_entry->getOwner()?->field_school->entity?->label();
      $school = $school_label;
      $team_label = _piv_live_competition_get_team_label($team_regional_entry, $current_language, include_competition_title: TRUE);
      $form['table'][] = [
        'team_regional_entry' => $team_regional_entry->toLink($team_label, 'edit-form')->toRenderable(),
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
   * Generate the recitation order for this competition.
   */
  public function generateOrder(array &$form, FormStateInterface $form_state): void {
    $node = $form_state->get('node');
    if (!$node) {
      return;
    }
    // Reload fresh to avoid stale field_active_round value.
    $node = $this->entityTypeManager->getStorage('node')->loadUnchanged($node->id());
    if (!$node) {
      return;
    }
    // Return early if competition has already started.
    if ($this->helper->hasCompetitionStarted($node)) {
      return;
    }
    $this->helper->generateRecitationOrder($node);
    $this->messenger()->addStatus($this->t('Recitation order has been generated.'));
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
