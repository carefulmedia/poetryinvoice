<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\piv_live_competition\Helper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Score results for live competitions.
 */
final class LiveCompetitionScoreResultsTableController extends ControllerBase {

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
   * Builds the response.
   */
  public function __invoke(NodeInterface $node): array {
    $build = [];

    $build['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $this->t('Final results'),
    ];
    $build['aggregated_table'] = [
      '#type' => 'table',
      '#header' => [
        'school' => $this->t('School'),
        'score' => $this->t('Score'),
        'recitation' => $this->t('Recitation'),
        'accuracy' => $this->t('Accuracy'),
        'overall' => $this->t('Overall Performance'),
        'best_poem' => $this->t('Best Poem'),
        'rank' => $this->t('Rank'),
      ],
    ];

    // Model for judge table.
    $judge_table = [
      '#type' => 'table',
      '#header' => [
        'school' => $this->t('School'),
        // Total all entries performance + accuracy.
        'score' => $this->t('Score'),
        // Total all performance.
        'recitation' => $this->t('Recitation'),
        // Total all accuracy.
        'accuracy' => $this->t('Accuracy'),
        // Total all entries just the "Overall performance" score.
        'overall' => $this->t('Overall Performance'),
        // Rank value among all schools.
        'rank' => $this->t('Rank'),
      ],
      '#rows' => [],
    ];

    $rows = [];
    $team_regional_entries = $this->entityTypeManager()->getStorage('node')
      ->loadByProperties([
        'type' => 'team_regionals_entry',
        'field_contest_association' => $node->id(),
      ]);
    $user_storage = $this->entityTypeManager()->getStorage('user');

    // Accuracy scores values keyes by regional entry.
    $accuracy_scores = [];

    // Individual scores for poems used for sorting.
    $poems = [];
    $best_poem_overall_score = [];
    // Raw data for the tables rows, keyed by judge.
    $rows = [];

    foreach ($team_regional_entries as $team_regional_entry) {
      $tr_id = $team_regional_entry->id();
      $accuracy_scores[$tr_id] = 0;
      if (empty($poems[$tr_id])) {
        $poems[$tr_id] = [];
      }
      foreach ($team_regional_entry->field_tr_student->referencedEntities() as $student_entry) {
        $student_entry_id = $student_entry->id();
        if (empty($poems[$tr_id][$student_entry_id])) {
          $poems[$tr_id][$student_entry_id] = [
            'score' => 0,
            'overall' => 0,
          ];
        }
        // Accuracy are added to the regional entries in the judges
        // tables. There are tables for performance judges only.
        foreach ($student_entry->field_accuracy_scores->referencedEntities() as $score) {
          $judge_id = $score->judge->target_id;
          $total_score = $score->field_scores?->value ?? 0;
          $accuracy_scores[$tr_id] += $total_score;
          $poems[$tr_id][$student_entry_id]['score'] += $total_score;
        }
        // Iterate on the performance judges to create a table for each.
        foreach ($student_entry->field_performance_scores->referencedEntities() as $score) {
          $judge_id = $score->judge->target_id;
          // Init the row.
          if (empty($rows[$judge_id][$tr_id])) {
            $school = $team_regional_entry->getOwner()?->field_school->entity?->label();
            $rows[$judge_id][$tr_id] = [
              'school' => ['#markup' => $school],
              'score' => 0,
              'recitation' => 0,
              'accuracy' => 0,
              'overall' => 0,
              'rank' => 0,
              '#team_regional_entry_id' => $tr_id,
            ];
          }
          $total_score = array_sum(array_column($score->field_scores->getValue(), 'value'));
          $rows[$judge_id][$tr_id]['score'] += $total_score;
          $rows[$judge_id][$tr_id]['recitation'] += $total_score;
          $poems[$tr_id][$student_entry_id]['score'] += $total_score;
          // It is the 5th item in the scores.
          if (count($score->field_scores) >= 5) {
            $overall = $score->field_scores[4]->value ?? 0;
            $rows[$judge_id][$tr_id]['overall'] += $overall;
            $poems[$tr_id][$student_entry_id]['overall'] += $overall;
          }
        }
      }
    }

    // Add accuracy scores.
    foreach ($rows as $judge_id => $judge_rows) {
      foreach ($judge_rows as $tr_id => $row) {
        $rows[$judge_id][$tr_id]['accuracy'] = $accuracy_scores[$tr_id];
        $rows[$judge_id][$tr_id]['score'] += $rows[$judge_id][$tr_id]['accuracy'];
      }
    }
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
      ];
    }

    // Sort each judge table, no best poem or best poem overall.
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

    // Build the tables.
    foreach ($rows as $judge_id => $judge_rows) {
      $build[$judge_id] = [
        '#type' => 'container',
      ];
      $build[$judge_id]['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $user_storage->load($judge_id)?->getDisplayName(),
      ];
      $build[$judge_id]['table'] = $judge_table;
      // Generate ranks and format table.
      $i = 1;
      $last_score = 0;
      $last_rank = 0;
      $tie = [];
      foreach ($judge_rows as $delta => $row) {
        $rank = $row['score'] == $last_score ? $last_rank : $i;
        $rows[$judge_id][$delta]['rank'] = $rank;
        $classes = [];
        if ($rank == $last_rank) {
          $tie[$last_rank] = TRUE;
          $classes[] = 'rank-tie-' . count($tie);
          $build[$judge_id]['table'][$delta - 1]['#attributes']['class'] = $classes;
        }
        $last_rank = $row['rank'];
        $build[$judge_id]['table'][] = [
          '#attributes' => ['class' => $classes],
          'school' => $row['school'],
          'score' => ['#markup' => $row['score']],
          'recitation' => ['#markup' => $row['recitation']],
          'accuracy' => ['#markup' => $row['accuracy']],
          'overall' => ['#markup' => $row['overall']],
          'rank' => ['#markup' => $rank],
        ];
        $last_score = $row['score'];
        $last_rank = $rank;
        $i++;
      }
    }

    // Aggregate.
    $aggregated_rows = [];
    foreach ($rows as $judge_id => $judge_rows) {
      foreach ($judge_rows as $row) {
        $tr_id = $row['#team_regional_entry_id'];
        if (empty($aggregated_rows[$tr_id])) {
          $aggregated_rows[$tr_id] = $row;
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
    // Sort each judge table, no best poem or best poem overall.
    usort($aggregated_rows, function ($a, $b) {
      if ($a['rank'] != $b['rank']) {
        return $a['rank'] <=> $b['rank'];
      }
      if ($a['score'] != $b['score']) {
        return $b['score'] <=> $a['score'];
      }
      if ($a['overall'] != $b['overall']) {
        return $b['overall'] <=> $a['overall'];
      }
      if ($a['accuracy'] != $b['accuracy']) {
        return $b['accuracy'] <=> $a['accuracy'];
      }
      if ($a['best_poem'] != $b['best_poem']) {
        return $b['best_poem'] <=> $a['best_poem'];
      }
      if ($a['#best_overall'] != $b['#best_overall']) {
        return $b['#best_overall'] <=> $a['#best_overall'];
      }
      return 0;
    });

    $last_rank = 0;
    $tie = [];
    foreach ($aggregated_rows as $i => $row) {
      $classes = [];
      if ($row['rank'] == $last_rank) {
        $tie[$last_rank] = TRUE;
        $classes[] = 'rank-tie-' . count($tie);
        $build['aggregated_table'][$i - 1]['#attributes']['class'] = $classes;
      }
      $last_rank = $row['rank'];
      $build['aggregated_table'][] = [
        '#attributes' => ['class' => $classes],
        'school' => $row['school'],
        'score' => ['#markup' => $row['score']],
        'recitation' => ['#markup' => $row['recitation']],
        'accuracy' => ['#markup' => $row['accuracy']],
        'overall' => ['#markup' => $row['overall']],
        'best_poem' => ['#markup' => $row['best_poem']],
        'rank' => ['#markup' => $row['rank']],
      ];
    }

    return $build;
  }

  /**
   * Return a generated title.
   */
  public function title(NodeInterface $node) {
    return $this->t('Score Results - @label', [
      '@label' => $node->label(),
    ]);
  }

}
