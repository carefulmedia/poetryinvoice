<?php

namespace Drupal\piv_contest_judging_session\Service;

use Drupal\piv_contest_judging_session\Entity\JudgingSession;
use Drupal\user\Entity\User;

class JudgeSession {

  /**
   * Order the recitations in a session.
   */
  public function orderRecitationsList(JudgingSession $session, User $judge) : array {
    static $list = [];
    $key = $session->id() . ':' . $judge->id();
    if (empty($list[$key])) {
      $competition_entries_ids = array_column($session->field_competition_entries->getValue(), 'target_id');
      if (!$competition_entries_ids) {
        return [];
      }

      $score_storage = \Drupal::entityTypeManager()->getStorage('score');
      foreach ($session->field_competition_entries->referencedEntities() as $competition_entry) {
        foreach ($competition_entry->field_recitations->referencedEntities() as $delta => $recitation) {
          $score = $score_storage->loadByProperties([
            'judge' => $judge->id(),
            'judging_session' => $session->id(),
            'recitation' => $recitation->id(),
          ]);
          $score = reset($score);
          $list[$key][$delta][] = [
            'recitation' => $recitation,
            'score' => $score,
            'show_break_panel' => empty($list[$key][$delta]) && $delta > 0,
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
