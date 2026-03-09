<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Confirmation form to remove a judge from a live competition.
 */
final class RemoveJudgeForm extends ConfirmFormBase {
  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Custom access - only admins or the Live Competition Administrator.
   */
  public function access(AccountInterface $account, NodeInterface $node, UserInterface $user): AccessResultInterface {
    $is_admin = $account->hasRole('administrator');
    $is_competition_admin = !$node->get('field_live_competition_admin')->isEmpty()
      && $node->get('field_live_competition_admin')->entity->id() === $account->id();

    return AccessResult::allowedIf($is_admin || $is_competition_admin)
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($node);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'piv_live_competition_remove_judge_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string|TranslatableMarkup {
    return $this->t('Remove judge');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    $node = $this->getRequest()->attributes->get('node');
    return Url::fromRoute('piv_live_competition.monitor_dashboard', ['node' => $node->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): string|TranslatableMarkup {
    return $this->t('Confirm removal');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?NodeInterface $node = NULL,
    ?UserInterface $user = NULL,
  ): array {
    $form = parent::buildForm($form, $form_state);

    $judge_name = $user->getDisplayName();
    $warning_text = $this->t('You are about to remove Judge <strong>@name</strong>.', ['@name' => $judge_name]) . '<br>';
    $warning_text .= $this->t('This action will remove the judge from the competition.') . '<br>';
    $warning_text .= $this->t('The judge will not be able to rejoin.') . '<br>';
    $warning_text .= $this->t("The judge's scores will not be included in the final results.") . '<br>';

    $form['warning'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['remove-judge-warning']],
      '#weight' => -10,
      'message' => [
        '#markup' => Markup::create($warning_text),
      ],
    ];

    // Store IDs in the form for use in submitForm().
    $form['node_id'] = ['#type' => 'value', '#value' => $node->id()];
    $form['judge_id'] = ['#type' => 'value', '#value' => $user->id()];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node_id = $form_state->getValue('node_id');
    $judge_id = $form_state->getValue('judge_id');

    $node_storage = $this->entityTypeManager->getStorage('node');
    $node = $node_storage->load($node_id);
    if (!$node) {
      $this->messenger()->addError($this->t('Competition not found.'));
      return;
    }

    $judge_name = $this->entityTypeManager->getStorage('user')->load($judge_id)?->getDisplayName() ?? $judge_id;
    $removed = FALSE;

    // Remove from performance judges (field_judges — paragraph field).
    $judges_field = $node->get('field_judges');
    $new_judges = [];
    foreach ($judges_field as $item) {
      $paragraph = $item->entity;
      if (!$paragraph) {
        continue;
      }
      if ((int) $paragraph->field_judge->target_id === (int) $judge_id) {
        $removed = TRUE;
        // Delete the paragraph entity itself.
        $paragraph->delete();
      }
      else {
        $new_judges[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
      }
    }
    $node->set('field_judges', $new_judges);

    // Remove from accuracy EN judges (direct user reference).
    $accuracy_en = $node->get('field_accuracy_judge_en');
    $new_accuracy_en = [];
    foreach ($accuracy_en as $item) {
      if ((int) $item->target_id === (int) $judge_id) {
        $removed = TRUE;
      }
      else {
        $new_accuracy_en[] = ['target_id' => $item->target_id];
      }
    }
    $node->set('field_accuracy_judge_en', $new_accuracy_en);

    // Remove from accuracy FR judges (direct user reference).
    $accuracy_fr = $node->get('field_accuracy_judge_fr');
    $new_accuracy_fr = [];
    foreach ($accuracy_fr as $item) {
      if ((int) $item->target_id === (int) $judge_id) {
        $removed = TRUE;
      }
      else {
        $new_accuracy_fr[] = ['target_id' => $item->target_id];
      }
    }
    $node->set('field_accuracy_judge_fr', $new_accuracy_fr);

    $node->save();

    // Remove the judge's scores from all team_regionals_entry nodes
    // associated with this competition.
    $this->removeJudgeScores((int) $node_id, (int) $judge_id);

    if ($removed) {
      $this->messenger()->addStatus($this->t('Judge @name has been removed from the competition.', ['@name' => $judge_name]));
    }
    else {
      $this->messenger()->addWarning($this->t('Judge @name was not found in this competition.', ['@name' => $judge_name]));
    }

    $form_state->setRedirect('piv_live_competition.monitor_dashboard', ['node' => $node_id]);
  }

  /**
   * Remove all score paragraphs submitted by a judge from entries in a competition.
   */
  private function removeJudgeScores(int $competition_id, int $judge_id): void {
    $node_storage = $this->entityTypeManager->getStorage('node');

    $team_regional_ids = $node_storage->getQuery()
      ->condition('type', 'team_regionals_entry')
      ->condition('field_contest_association', $competition_id)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($team_regional_ids)) {
      return;
    }

    $team_regional_entries = $node_storage->loadMultiple($team_regional_ids);

    foreach ($team_regional_entries as $entry) {
      foreach ($entry->field_tr_student->referencedEntities() as $recitation) {
        $changed = FALSE;

        // Remove accuracy scores by this judge.
        $accuracy_scores = $recitation->get('field_accuracy_scores');
        $new_accuracy = [];
        foreach ($accuracy_scores as $item) {
          $score = $item->entity;
          if (!$score) {
            continue;
          }
          if ((int) $score->judge->target_id === $judge_id) {
            $score->delete();
            $changed = TRUE;
          }
          else {
            $new_accuracy[] = ['target_id' => $score->id(), 'target_revision_id' => $score->getRevisionId()];
          }
        }
        if ($changed) {
          $recitation->set('field_accuracy_scores', $new_accuracy);
        }

        // Remove performance scores by this judge.
        $performance_scores = $recitation->get('field_performance_scores');
        $new_performance = [];
        $perf_changed = FALSE;
        foreach ($performance_scores as $item) {
          $score = $item->entity;
          if (!$score) {
            continue;
          }
          if ((int) $score->judge->target_id === $judge_id) {
            $score->delete();
            $perf_changed = TRUE;
          }
          else {
            $new_performance[] = ['target_id' => $score->id(), 'target_revision_id' => $score->getRevisionId()];
          }
        }
        if ($perf_changed) {
          $recitation->set('field_performance_scores', $new_performance);
          $changed = TRUE;
        }

        if ($changed) {
          $recitation->save();
        }
      }
    }
  }

}
