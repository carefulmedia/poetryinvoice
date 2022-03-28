<?php

namespace Drupal\piv_contest_judging_session\Service;

use Drupal\piv_contest_judging_session\Entity\JudgingSession;
use Drupal\user\Entity\User;

class JudgeSession {

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

      $score_storage = \Drupal::entityTypeManager()->getStorage('score');
      foreach ($session->field_competition_entries->referencedEntities() as $competition_entry) {
        $recitations = $competition_entry->field_recitations->referencedEntities();
        $total_recitations = count($competition_entries);
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

  public function isSessionEvaluatedByJudge(JudgingSession $session, User $judge): bool {
    return $this->nextRecitation($session, $judge) === FALSE;
  }

}
