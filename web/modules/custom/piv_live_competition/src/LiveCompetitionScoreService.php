<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service for calculating live competition scores and results.
 */
final class LiveCompetitionScoreService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageManagerInterface $languageManager,
    #[Autowire(service: 'piv_live_competition.helper')]
    private readonly Helper $helper,
  ) {}

  /**
   * Calculate competition results for a given competition and stream.
   *
   * @param \Drupal\node\NodeInterface $competition
   *   The competition node.
   * @param string|int $stream
   *   The language stream value or '_none'.
   *
   * @return array
   *   Array of results with structure:
   *   - standings: Array of school standings with rank, score, school info
   *   - total_schools: Total number of unique schools
   */
  public function calculateCompetitionResults(NodeInterface $competition, string|int $stream): array {
    // Get accuracy judges.
    $judges_fr = array_column($competition->get('field_accuracy_judge_fr')->getValue(), 'target_id');
    $judges_en = array_column($competition->get('field_accuracy_judge_en')->getValue(), 'target_id');

    // Query team regional entries.
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'team_regionals_entry')
      ->condition('field_contest_association', $competition->id())
      ->accessCheck(FALSE);

    if ($stream !== '_none') {
      $query->condition('field_language_stream', $stream);
    }
    else {
      $query->notExists('field_language_stream');
    }

    $team_regional_ids = $query->execute();
    $team_regional_entries = $team_regional_ids
      ? $this->entityTypeManager->getStorage('node')->loadMultiple($team_regional_ids)
      : [];

    if (empty($team_regional_entries)) {
      return [
        'standings' => [],
        'total_schools' => 0,
      ];
    }

    $user_storage = $this->entityTypeManager->getStorage('user');

    // Accuracy scores values keyed by regional entry.
    $accuracy_scores = [];

    // Individual scores for poems used for sorting.
    $poems = [];

    // Raw data for the tables rows, keyed by judge.
    $rows = [];

    // Map students per team regional entry (NOT per school).
    $students_map = [];

    foreach ($team_regional_entries as $team_regional_entry) {
      $current_language = $this->languageManager->getCurrentLanguage()->getId();
      if ($team_regional_entry->hasTranslation($current_language)) {
        $team_regional_entry = $team_regional_entry->getTranslation($current_language);
      }

      $tr_id = $team_regional_entry->id();
      $accuracy_scores[$tr_id]['en'] = 0;
      $accuracy_scores[$tr_id]['fr'] = 0;

      if (empty($poems[$tr_id])) {
        $poems[$tr_id] = [];
      }

      // Get school ID once per entry.
      $school_id = $team_regional_entry->getOwner()?->field_school->target_id;

      foreach ($team_regional_entry->field_tr_student->referencedEntities() as $student_entry) {
        $student_entry_id = $student_entry->id();
        if (empty($poems[$tr_id][$student_entry_id])) {
          $poems[$tr_id][$student_entry_id] = [
            'score' => 0,
            'overall' => 0,
          ];
        }

        // Map students by team regional entry ID, not by school ID.
        if (empty($students_map[$tr_id])) {
          $students_map[$tr_id] = [];
        }
        $students_map[$tr_id][] = $this->helper->getStudentName($student_entry);

        // Accuracy scores.
        foreach ($student_entry->field_accuracy_scores->referencedEntities() as $score) {
          $judge_id = $score->judge->target_id;

          // Determine judge's language based on which accuracy judge list they're in.
          if (in_array($judge_id, $judges_fr, TRUE)) {
            $langcode = 'fr';
          }
          elseif (in_array($judge_id, $judges_en, TRUE)) {
            $langcode = 'en';
          }
          else {
            // Fallback if judge isn't in either list. Honestly this shouldn't happen, but better safe than sorry.
            $langcode = 'en';
          }

          $total_score = $score->field_scores?->value ?? 0;
          $accuracy_scores[$tr_id][$langcode] += $total_score;
          $poems[$tr_id][$student_entry_id]['score'] += $total_score;
        }

        // Performance scores.
        foreach ($student_entry->field_performance_scores->referencedEntities() as $score) {
          $judge_id = $score->judge->target_id;

          if (empty($rows[$judge_id][$tr_id])) {
            $school = $team_regional_entry->getOwner()?->field_school->entity?->label();
            $school_label = _piv_live_competition_get_team_label($team_regional_entry, $current_language) ?? $school;
            $rows[$judge_id][$tr_id] = [
              'school' => $school_label,
              'score' => 0,
              'recitation' => 0,
              'accuracy' => 0,
              'overall' => 0,
              'rank' => 0,
              '#team_regional_entry_id' => $tr_id,
              '#school_id' => $school_id,
              '#school_name' => $school,
            ];
          }

          $total_score = array_sum(array_column($score->field_scores->getValue(), 'value'));
          $rows[$judge_id][$tr_id]['score'] += $total_score;
          $rows[$judge_id][$tr_id]['recitation'] += $total_score;
          $poems[$tr_id][$student_entry_id]['score'] += $total_score;

          // Overall performance score (5th item).
          if (count($score->field_scores) >= 5) {
            $overall = $score->field_scores[4]->value ?? 0;
            $rows[$judge_id][$tr_id]['overall'] += $overall;
            $poems[$tr_id][$student_entry_id]['overall'] += $overall;
          }
        }
      }
    }

    if (!$rows) {
      return [
        'standings' => [],
        'total_entries' => 0,
      ];
    }

    // Add accuracy scores.
    foreach ($rows as $judge_id => $judge_rows) {
      foreach ($judge_rows as $tr_id => $row) {
        $judge = $user_storage->load($judge_id);
        if (!$judge) {
          continue;
        }
        $langcode = $judge->preferred_langcode->value ?? 'en';
        $rows[$judge_id][$tr_id]['accuracy'] = $accuracy_scores[$tr_id][$langcode];
        $rows[$judge_id][$tr_id]['score'] += $rows[$judge_id][$tr_id]['accuracy'];
      }
    }

    // Calculate best poems.
    $bests = [];
    foreach ($poems as $entry => $poem_entries) {
      $best_score_poem = NULL;
      $best_overall_poem = NULL;
      $max_score = 0;
      $max_overall = 0;

      foreach ($poem_entries as $poem => $stats) {
        if ($stats['score'] > $max_score) {
          $max_score = $stats['score'];
          $best_score_poem = $poem;
        }
        if ($stats['overall'] > $max_overall) {
          $max_overall = $stats['overall'];
          $best_overall_poem = $poem;
        }
      }

      $bests[$entry] = [
        'score' => $best_score_poem,
        'overall' => $best_overall_poem,
        'max_score' => $max_score,
        'max_overall' => $max_overall,
      ];
    }

    // Sort each judge table.
    foreach ($rows as &$judge_rows) {
      usort($judge_rows, function ($a, $b) {
        if ($a['score'] != $b['score']) {
          return $b['score'] <=> $a['score'];
        }
        if ($a['overall'] != $b['overall']) {
          return $b['overall'] <=> $a['overall'];
        }
        if ($a['accuracy'] != $b['accuracy']) {
          return $b['accuracy'] <=> $a['accuracy'];
        }
        return 0;
      });
    }
    unset($judge_rows);

    // Assign ranks per judge.
    foreach ($rows as $judge_id => $judge_rows) {
      $i = 1;
      $last_score = 0;
      $last_rank = 0;
      foreach ($judge_rows as $delta => $row) {
        $rank = $row['score'] === $last_score ? $last_rank : $i;
        $rows[$judge_id][$delta]['rank'] = $rank;
        $last_rank = $rank;
        $last_score = $row['score'];
        $i++;
      }
    }

    // Aggregate results.
    $aggregated_rows = [];
    foreach ($rows as $judge_id => $judge_rows) {
      foreach ($judge_rows as $row) {
        $tr_id = $row['#team_regional_entry_id'];
        if (empty($aggregated_rows[$tr_id])) {
          $aggregated_rows[$tr_id] = $row;
          $aggregated_rows[$tr_id]['#tr_id'] = $tr_id;
        }
        else {
          $aggregated_rows[$tr_id]['score'] += $row['score'];
          $aggregated_rows[$tr_id]['recitation'] += $row['recitation'];
          $aggregated_rows[$tr_id]['accuracy'] += $row['accuracy'];
          $aggregated_rows[$tr_id]['overall'] += $row['overall'];
          $aggregated_rows[$tr_id]['rank'] += $row['rank'];
        }

        if (empty($aggregated_rows[$tr_id]['best_poem'])) {
          $best_poem_id = $bests[$tr_id]['score'];
          $best_poem_overall_id = $bests[$tr_id]['overall'];
          $best_poem_score = $poems[$tr_id][$best_poem_id]['score'];
          $best_poem_overall_score = $poems[$tr_id][$best_poem_overall_id]['overall'];
          $aggregated_rows[$tr_id]['best_poem'] = $best_poem_score;
          $aggregated_rows[$tr_id]['#best_overall'] = $best_poem_overall_score;
        }
      }
    }

    // Sort aggregated results with tie tracking.
    $ties = [];
    $tie = 1;
    usort($aggregated_rows, static function ($a, $b) use (&$ties, &$tie) {
      if ($a['rank'] !== $b['rank']) {
        return $a['rank'] <=> $b['rank'];
      }
      if ($a['score'] !== $b['score']) {
        return $b['score'] <=> $a['score'];
      }
      if ($a['overall'] !== $b['overall']) {
        return $b['overall'] <=> $a['overall'];
      }
      if ($a['accuracy'] !== $b['accuracy']) {
        return $b['accuracy'] <=> $a['accuracy'];
      }
      if ($a['best_poem'] !== $b['best_poem']) {
        return $b['best_poem'] <=> $a['best_poem'];
      }
      if ($a['#best_overall'] !== $b['#best_overall']) {
        return $b['#best_overall'] <=> $a['#best_overall'];
      }

      // It is a tie.
      $a_id = $a['#tr_id'];
      $b_id = $b['#tr_id'];
      if (isset($ties[$a_id])) {
        $ties[$b_id] = $ties[$a_id];
      }
      else {
        $ties[$a_id] = $tie;
        $ties[$b_id] = $tie;
        $tie++;
      }
      return 0;
    });

    // Build final standings array.
    $standings = [];
    foreach ($aggregated_rows as $row) {
      $tr_id = $row['#tr_id'];
      $school_id = $row['#school_id'];
      $is_tie = isset($ties[$tr_id]);

      $standings[] = [
        'rank' => $row['rank'],
        'tr_id' => $tr_id,
        'school_id' => $school_id,
        'school_name' => $row['school'],
        'school_name_plain' => $row['#school_name'],
        'reciters' => array_unique($students_map[$tr_id] ?? []),
        'score' => $row['score'],
        'overall' => $row['overall'],
        'accuracy' => $row['accuracy'],
        'recitation' => $row['recitation'],
        'best_poem' => $row['best_poem'],
        'best_overall' => $row['#best_overall'],
        'is_tie' => $is_tie,
      ];
    }

    // Count total entries (teams).
    $total_entries = count($standings);

    return [
      'standings' => $standings,
      'total_entries' => $total_entries,
    ];
  }

  /**
   * Get top placements from standings.
   *
   * @param array $standings
   *   Array of standings from calculateCompetitionResults().
   * @param int $total_entries
   *   Total number of entries (teams).
   *
   * @return array
   *   Array of top placements (top 3 or 4 entries).
   */
  public function getTopPlacements(array $standings, int $total_entries): array {
    // Top 4 if exactly 4 entries, otherwise top 3.
    $target_placement_count = $total_entries === 4 ? 4 : 3;

    return array_slice($standings, 0, $target_placement_count);
  }

  /**
   * Get participating entries (not in top placements).
   *
   * @param array $standings
   *   Array of standings from calculateCompetitionResults().
   * @param int $total_entries
   *   Total number of entries (teams).
   *
   * @return array
   *   Array of remaining school names (plain, without team labels).
   */
  public function getParticipatingSchools(array $standings, int $total_entries): array {
    // Determine how many entries are in top placements.
    $target_placement_count = $total_entries === 4 ? 4 : 3;

    // Collect remaining entries after top placements.
    $participating = array_slice($standings, $target_placement_count);

    // Use plain school names (without team labels) and remove duplicates.
    $school_names = array_map(static fn($s) => $s['school_name_plain'], $participating);
    return array_values(array_unique($school_names));
  }

}
