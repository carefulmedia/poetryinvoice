<?php

namespace Drupal\piv_contest;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\piv_contest_competition\CompetitionInterface;

/**
 * Helper for ranking competition entries.
 */
class CompetitionRankHelper {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Returns ranked entries grouped by judging session.
   *
   * Uses per-judge rank-sum: each judge ranks entries independently,
   * then ranks are summed. Lower rank-sum is better; total score breaks ties.
   *
   * @return array
   *   Keyed by session ID, each value is [rank => [entries]].
   */
  public function getRankedEntriesPerSession(CompetitionInterface $competition, int $level): array {
    $session_storage = $this->entityTypeManager->getStorage('judging_session');

    $sessions = $session_storage->loadByProperties([
      'field_competition' => $competition->id(),
      'field_competition_current_level' => $level,
    ]);

    $result = [];
    foreach ($sessions as $session) {
      $entries = $session->field_competition_entries->referencedEntities();
      if (empty($entries)) {
        continue;
      }
      $entries_keyed = [];
      foreach ($entries as $entry) {
        $entries_keyed[$entry->id()] = $entry;
      }

      $totalled = $this->rankPerJudge([$session], $entries_keyed);
      $ranked = $this->buildFinalRanking($totalled, $entries_keyed);
      $filtered = $this->filterPromoted($ranked, $level);
      if (!empty($filtered)) {
        $result[$session->id()] = $filtered;
      }
    }

    return $result;
  }

  /**
   * Returns ranked promoted entries grouped by stream and judging session.
   *
   * @return array
   *   Keyed by stream target ID, then session ID, each value is [rank => [entries]].
   */
  public function getRankedEntriesPerStream(CompetitionInterface $competition, int $level): array {
    return $this->getEntriesPerStreamAndSession($competition, $level, TRUE);
  }

  /**
   * Returns ranked loser entries grouped by stream and judging session.
   *
   * @return array
   *   Keyed by stream target ID, then session ID, each value is [rank => [entries]].
   */
  public function getLoserEntriesPerStream(CompetitionInterface $competition, int $level): array {
    return $this->getEntriesPerStreamAndSession($competition, $level, FALSE);
  }

  protected function getEntriesPerStreamAndSession(CompetitionInterface $competition, int $level, bool $promoted): array {
    $session_storage = $this->entityTypeManager->getStorage('judging_session');

    $sessions = $session_storage->loadByProperties([
      'field_competition' => $competition->id(),
      'field_competition_current_level' => $level,
    ]);

    $result = [];
    foreach ($sessions as $session) {
      $stream_id = $session->field_stream->target_id;
      $entries = $session->field_competition_entries->referencedEntities();
      if (empty($entries)) {
        continue;
      }
      $entries_keyed = [];
      foreach ($entries as $entry) {
        $entries_keyed[$entry->id()] = $entry;
      }

      $totalled = $this->rankPerJudge([$session], $entries_keyed);
      $ranked = $this->buildFinalRanking($totalled, $entries_keyed);
      $filtered = $promoted
        ? $this->filterPromoted($ranked, $level)
        : $this->filterNotPromoted($ranked, $level);
      if (!empty($filtered)) {
        $result[$stream_id][$session->id()] = $filtered;
      }
    }

    return $result;
  }

  /**
   * Ranks entries per judge across the given sessions.
   *
   * Each judge (identified as "judge_id:language") ranks all entries
   * by their total score (accuracy + judge scores). Ranks are accumulated
   * so the final rank-sum reflects all judges across all sessions.
   *
   * @return array
   *   Keyed by entry ID: ['rank_sum' => int, 'total_score' => int].
   */
  protected function rankPerJudge(array $sessions, array $entries_keyed): array {
    $score_storage = $this->entityTypeManager->getStorage('score');

    $judges = [];
    foreach ($sessions as $session) {
      foreach (array_column($session->field_english_judge->getValue(), 'target_id') as $id) {
        $judges["$id:en"] = TRUE;
      }
      foreach (array_column($session->field_french_judge->getValue(), 'target_id') as $id) {
        $judges["$id:fr"] = TRUE;
      }
    }

    // Build score map: judge_key => [entry_id => total_score].
    $judge_scores = array_fill_keys(array_keys($judges), []);
    foreach ($sessions as $session) {
      foreach ($session->field_competition_entries->referencedEntities() as $entry) {
        if (!isset($entries_keyed[$entry->id()])) {
          continue;
        }
        $accuracy_per_language = [];
        $scores_per_judge = [];

        foreach ($entry->field_recitations->referencedEntities() as $recitation) {
          $language = $recitation->field_stream_language->target_id;
          $accuracy_per_language[$language] = ($accuracy_per_language[$language] ?? 0)
            + (int) ($recitation->field_score->value ?? 0);

          $scores = $score_storage->loadByProperties([
            'judging_session' => $session->id(),
            'recitation' => $recitation->id(),
          ]);
          foreach ($scores as $score) {
            $key = $score->judge->target_id . ':' . $language;
            $scores_per_judge[$key] = ($scores_per_judge[$key] ?? 0)
              + array_sum(array_column($score->field_scores->getValue(), 'value'));
          }
        }

        foreach ($judge_scores as $judge_key => $_) {
          [, $lang] = explode(':', $judge_key);
          $accuracy = $accuracy_per_language[$lang] ?? 0;
          $judge_score = $scores_per_judge[$judge_key] ?? 0;
          $judge_scores[$judge_key][$entry->id()] = ($judge_scores[$judge_key][$entry->id()] ?? 0)
            + $accuracy + $judge_score;
        }
      }
    }

    // Per-judge ranking, accumulate rank-sum and total score.
    $totalled = [];
    foreach ($judge_scores as $judge_key => $entry_scores) {
      arsort($entry_scores);

      $i = 1;
      $last_score = NULL;
      $last_rank = 1;
      foreach ($entry_scores as $entry_id => $total_score) {
        $rank = ($total_score === $last_score) ? $last_rank : $i;

        if (!isset($totalled[$entry_id])) {
          $totalled[$entry_id] = ['rank_sum' => 0, 'total_score' => 0];
        }
        $totalled[$entry_id]['rank_sum'] += $rank;
        $totalled[$entry_id]['total_score'] += $total_score;

        $last_score = $total_score;
        $last_rank = $rank;
        $i++;
      }
    }

    return $totalled;
  }

  /**
   * Filters ranked results to only entries promoted past the given level.
   *
   * Ranks are preserved from the full pool so they reflect the original
   * position among all judged entries.
   */
  protected function filterPromoted(array $ranked, int $level): array {
    $result = [];
    foreach ($ranked as $rank => $entries) {
      $promoted = array_filter($entries, function ($entry) use ($level) {
        return (int) $entry->field_competition_current_level->value > $level;
      });
      if (!empty($promoted)) {
        $result[$rank] = array_values($promoted);
      }
    }
    return $result;
  }

  /**
   * Filters ranked results to only entries NOT promoted past the given level.
   */
  protected function filterNotPromoted(array $ranked, int $level): array {
    $result = [];
    foreach ($ranked as $rank => $entries) {
      $not_promoted = array_filter($entries, function ($entry) use ($level) {
        return (int) $entry->field_competition_current_level->value <= $level;
      });
      if (!empty($not_promoted)) {
        $result[$rank] = array_values($not_promoted);
      }
    }
    return $result;
  }

  /**
   * Builds final ranking from per-judge rank-sum totals.
   *
   * Lower rank-sum is better. Higher total score breaks ties.
   *
   * @return array
   *   Keyed by rank (1-based), each value is an array of entry entities.
   */
  protected function buildFinalRanking(array $totalled, array $entries_keyed): array {
    uasort($totalled, function ($a, $b) {
      if ($a['rank_sum'] === $b['rank_sum']) {
        return $b['total_score'] <=> $a['total_score'];
      }
      return $a['rank_sum'] <=> $b['rank_sum'];
    });

    $ranked = [];
    $i = 1;
    $last_rank_sum = NULL;
    $last_total_score = NULL;
    $last_assigned_rank = 1;
    foreach ($totalled as $entry_id => $data) {
      if ($data['rank_sum'] === $last_rank_sum && $data['total_score'] === $last_total_score) {
        $assigned_rank = $last_assigned_rank;
      }
      else {
        $assigned_rank = $i;
      }
      if (isset($entries_keyed[$entry_id])) {
        $ranked[$assigned_rank][] = $entries_keyed[$entry_id];
      }
      $last_rank_sum = $data['rank_sum'];
      $last_total_score = $data['total_score'];
      $last_assigned_rank = $assigned_rank;
      $i++;
    }

    ksort($ranked);
    return $ranked;
  }

}
