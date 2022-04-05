<?php

namespace Drupal\piv_contest_judging_session\Service;

use Drupal\piv_contest_judging_session\Entity\JudgingSession;
use Drupal\user\Entity\User;
use Drupal\piv_contest_recitation\Entity\Recitation;
use Drupal\piv_contest_competition_entry\Entity\CompetitionEntry;
use Drupal\Core\Entity\EntityTypeManagerInterface;


class JudgeSession {

  /**
   * The entity type manager service.
   * 
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Check if a competition entry was already scored for accuracy.
   */
  public function entryWasScoredForAccuracy(CompetitionEntry $competition_entry) {
    foreach ($competition_entry->field_recitations->referencedEntities() as $recitation) {
      if ($recitation->field_score->isEmpty()) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Get the recitation score for a session.
   */
  public function getRecitationScore(Recitation $recitation, JudgingSession $judging_session) : int {
    $score_storage = $this->entityTypeManager->getStorage('score');
    $scores = $score_storage->loadByProperties([
      'judging_session' => $judging_session->id(),
      'recitation' => $recitation->id(),
    ]);
    $total = 0;
    foreach ($scores as $score) {
      $values = array_column($score->field_scores->getValue(), 'value');
      $total += array_sum($values);
    }
    return $total;
  }

  /**
   * Order the recitations in a session.
   */
  public function orderRecitationsList(JudgingSession $session, User $judge) : array {
    $languages = [];
    $judge_id = $judge->id();
    $french_judges = array_column($session->field_french_judge->getValue(), 'target_id');
    $english_judges = array_column($session->field_english_judge->getValue(), 'target_id');
    if (in_array($judge_id, $french_judges)) {
      $languages[] = 'fr';
    }
    if (in_array($judge_id, $english_judges)) {
      $languages[] = 'en';
    }

    static $list = [];
    $key = $session->id() . ':' . $judge->id();
    if (empty($list[$key])) {
      $competition_entries_ids = array_column($session->field_competition_entries->getValue(), 'target_id');
      if (!$competition_entries_ids) {
        return [];
      }

      $score_storage = $this->entityTypeManager->getStorage('score');
      foreach ($session->field_competition_entries->referencedEntities() as $competition_entry) {
        $recitations = $competition_entry->field_recitations->referencedEntities();
        $total_recitations = count($recitations);
        foreach ($recitations as $delta => $recitation) {
          // Only consider the languages this judge is assigned to.
          if (!in_array($recitation->langcode->value, $languages)) {
            continue;
          }
          $score = $score_storage->loadByProperties([
            'judge' => $judge->id(),
            'judging_session' => $session->id(),
            'recitation' => $recitation->id(),
          ]);
          $score = reset($score);
          $list[$key][$delta][] = [
            'recitation' => $recitation,
            'score' => $score,
            'last_of_round' => ($total_recitations == ($delta + 1)),
          ];
        }
      }
    }

    // Flatten the array.
    return array_merge(...$list[$key]);
  }

  public function totalNumberOfRecitations(JudgingSession $session, User $judge): int {
    return count($this->orderRecitationsList($session, $judge));
  }

  public function numberOfRecitationsEvaluatedByJudge(JudgingSession $session, User $judge): int {
    $list = $this->orderRecitationsList($session, $judge);
    return count(array_filter($list, fn ($item) => !empty($item['score'])));
  }

  public function nextRecitation(JudgingSession $session, User $judge) {
    $list = $this->orderRecitationsList($session, $judge);
    foreach ($list as $recitation) {
      if (empty($recitation['score'])) {
        return $recitation;
      }
    }
    return FALSE;
  }

  /**
   * Check if session was evaluated by a judge already.
   */
  public function isSessionEvaluatedByJudge(JudgingSession $session, User $judge): bool {
    return $this->nextRecitation($session, $judge) === FALSE;
  }

}
