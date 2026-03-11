<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\piv_live_competition\Helper;
use Drupal\piv_live_competition\LiveCompetitionScoreService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Render\Element;

/**
 * Score results for live competitions.
 */
final class LiveCompetitionScoreResultsTableController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected readonly Helper $helper,
    protected readonly LiveCompetitionScoreService $scoreService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('piv_live_competition.helper'),
      $container->get('piv_live_competition.score_service')
    );
  }

  /**
   * Builds the response.
   */
  public function __invoke(NodeInterface $node, ?string $stream = NULL): mixed {
    if ($stream === NULL) {
      $nodes = $this->entityTypeManager()->getStorage('node')
        ->loadByProperties([
          'field_contest_association' => $node->id(),
        ]);
      $streams = array_unique(
        array_filter(
          array_map(static function ($n) {
            return $n->field_language_stream->value ?? NULL;
          }, $nodes),
          static fn ($v) => $v !== NULL,
        ),
      );
      $stream = $streams ? min($streams) : '_none';
      return $this->redirect('piv_live_competition.live_competition_score_results_table', [
        'node' => $node->id(),
        'stream' => $stream,
      ]);
    }

    // Accuracy judges ids.
    $judges_fr = array_column($node->field_accuracy_judge_fr->getValue(), 'target_id');
    $judges_en = array_column($node->field_accuracy_judge_en->getValue(), 'target_id');

    $build = [];

    $streams = $this->helper->getStreams();
    $build['stream'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $streams[$stream] ?? '- None -',
    ];

    // Model for judge table.
    $judge_table = [
      '#type' => 'table',
      '#header' => [
        'school' => $this->t('School'),
        // Rank value among all schools.
        'rank' => $this->t('Rank'),
        // Total all entries performance + accuracy.
        'score' => $this->t('Score'),
        // Total all entries just the "Overall performance" score.
        'overall' => $this->t('Overall Performance'),
        // Total all accuracy.
        'accuracy' => $this->t('Accuracy'),
        // Total all performance.
        'recitation' => $this->t('Recitation'),
      ],
      '#rows' => [],
    ];

    $rows = [];

    $query = $this->entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'team_regionals_entry')
      ->condition('field_contest_association', $node->id())
      ->accessCheck(FALSE);
    if ($stream !== '_none') {
      $query->condition('field_language_stream', $stream);
    }
    else {
      $query->notExists('field_language_stream');
    }
    $team_regional_ids = $query->execute();
    $team_regional_entries = $team_regional_ids
      ? $this->entityTypeManager()->getStorage('node')->loadMultiple($team_regional_ids)
      : [];

    $user_storage = $this->entityTypeManager()->getStorage('user');

    // Accuracy scores values keyes by regional entry.
    $accuracy_scores = [];

    // Individual scores for poems used for sorting.
    $poems = [];
    $best_poem_overall_score = [];
    // Raw data for the tables rows, keyed by judge.
    $rows = [];
    // Map students per team regional entry (NOT per school).
    $students_map = [];

    foreach ($team_regional_entries as $team_regional_entry) {
      // Ensure our entry is translated to the current language.
      $current_language = \Drupal::languageManager()->getCurrentLanguage()->getId();
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

        // Accuracy are added to the regional entries in the judges
        // tables. There are tables for performance judges only.
        foreach ($student_entry->field_accuracy_scores->referencedEntities() as $score) {
          $judge_id = $score->judge->target_id;
          $langcode = in_array($judge_id, $judges_fr) ? 'fr' : 'en';
          $total_score = $score->field_scores?->value ?? 0;
          $accuracy_scores[$tr_id][$langcode] += $total_score;
          $poems[$tr_id][$student_entry_id]['score'] += $total_score;
        }
        // Iterate on the performance judges to create a table for each.
        foreach ($student_entry->field_performance_scores->referencedEntities() as $score) {
          $judge_id = $score->judge->target_id;
          // Init the row.
          if (empty($rows[$judge_id][$tr_id])) {
            $school = $team_regional_entry->getOwner()?->field_school->entity?->label();
            $school_label = _piv_live_competition_get_team_label($team_regional_entry, $current_language) ?? $school;
            $rows[$judge_id][$tr_id] = [
              'school' => ['#markup' => $school_label],
              'score' => 0,
              'recitation' => 0,
              'accuracy' => 0,
              'overall' => 0,
              'rank' => 0,
              '#team_regional_entry_id' => $tr_id,
              '#school_id' => $school_id,
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

    if (!$rows) {
      $build['empty'] = ['#markup' => 'No scores for this stream'];
      return $build;
    }

    $build['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $this->t('Final results'),
      '#attributes' => [
        'class' => ['final-results'],
      ],
    ];
    $build['aggregated_table'] = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['final-results'],
      ],
      '#header' => [
        'place' => $this->t('Place'),
        'school' => $this->t('School'),
        'rank' => $this->t('Rank'),
        'score' => $this->t('Score'),
        'overall' => $this->t('Overall Performance'),
        'accuracy' => $this->t('Accuracy'),
        'recitation' => $this->t('Recitation'),
        'best_poem' => $this->t('Best Poem'),
        'best_poem_overall' => $this->t('Highest Overall'),
      ],
    ];

    // Add accuracy scores.
    $user_storage = $this->entityTypeManager()->getStorage('user');
    foreach ($rows as $judge_id => $judge_rows) {
      foreach ($judge_rows as $tr_id => $row) {
        // This is a performance judge, the language they judge is set
        // on the user preferred language.
        $judge = $user_storage->load($judge_id);
        if (!$judge) {
          continue;
        }
        $langcode = $judge->preferred_langcode->value ?? 'en';
        $rows[$judge_id][$tr_id]['accuracy'] = $accuracy_scores[$tr_id][$langcode];
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
        if ($a['recitation'] != $b['recitation']) {
          return $b['recitation'] <=> $a['recitation'];
        }
        return 0;
      });
    }
    unset($judge_rows);

    // Hide individual judge tables for the competition admin on Team Regional
    // competitions. Regular site admins can still see everything.
    $is_team_regional = $node->field_level->value === 'Team Regional';
    $current_user = $this->currentUser();
    $is_competition_admin = !$node->get('field_live_competition_admin')->isEmpty()
      && $node->get('field_live_competition_admin')->entity->id() === $current_user->id();
    $hide_judge_tables = $is_team_regional && $is_competition_admin
      && !$current_user->hasRole('administrator');

    // Build the tables.
    foreach ($rows as $judge_id => $judge_rows) {
      if ($hide_judge_tables) {
        continue;
      }
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
      $last_rank = 0;
      $tie = [];

      // Aggregate and count scores so we can better adjust rank, the
      // idea is that if there are ties, the ranks are added together
      // and divided by the count of ties, so 3 entries in rank 1 will
      // end up with rank = (1+2+3)/3.
      $rank_count = [];
      foreach ($judge_rows as $delta => $row) {
        // Compare to last row.
        $last = $delta > 0 ? $judge_rows[$delta - 1] : NULL;
        if ($last == NULL) {
          $rank = $i;
        }
        else {
          if ($last['score'] == $row['score']
          && $last['recitation'] == $row['recitation']
          && $last['accuracy'] == $row['accuracy']
          && $last['overall'] == $row['overall']) {
            $rank = $last_rank;
          }
          else {
            $rank = $i;
          }
        }

        $rows[$judge_id][$delta]['rank'] = $rank;
        $classes = [];
        if ($rank == $last_rank) {
          $tie[$last_rank] = TRUE;
          $classes[] = 'rank-tie-' . count($tie);
          $build[$judge_id]['table'][$delta - 1]['#attributes']['class'] = $classes;
        }

        // Count rank to aggregate in the end.
        if (empty($rank_count[$rank])) {
          $rank_count[$rank] = 0;
        }
        $rank_count[$rank]++;

        $build[$judge_id]['table'][] = [
          '#attributes' => ['class' => $classes],
          'school' => $row['school'],
          'rank' => ['#markup' => $rank],
          'score' => ['#markup' => $row['score']],
          'overall' => ['#markup' => $row['overall']],
          'accuracy' => ['#markup' => $row['accuracy']],
          'recitation' => ['#markup' => $row['recitation']],
        ];
        $last_rank = $rank;
        $i++;
      }

      // Redo ranking, use the results above to recalculate ranking.
      $rank_remap = [];
      foreach ($rank_count as $rank => $count) {
        if ($count > 1) {
          // If rank is 4 and there are 3 items in rank 4:
          // 4 * 3 + 1 + 2 = 12 + 1 + 2 = 15.
          // 15 is the same as 4 + 5 + 6.
          $total_rank = $rank * $count + array_sum(range(1, $count - 1));
          $new_rank = (float) $total_rank / $count;
          $rank_remap[$rank] = $new_rank;
        }
      }
      if (count($rank_remap)) {
        // Update the table and the $rows for aggregated results.
        $deltas = Element::children($build[$judge_id]['table']);
        foreach ($deltas as $delta) {
          $row_rank = $build[$judge_id]['table'][$delta]['rank']['#markup'];
          $build[$judge_id]['table'][$delta]['rank']['#markup'] = $rank_remap[$row_rank] ?? $row_rank;
        }
        foreach ($rows[$judge_id] as $delta => $row) {
          $rows[$judge_id][$delta]['rank'] = $rank_remap[$row['rank']] ?? $row['rank'];
        }
      }
    }

    // Aggregate.
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
    // Sort aggregated tables.
    $ties = [];
    $tie = 1;
    usort($aggregated_rows, function ($a, $b) use (&$ties, &$tie) {
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

    $last_rank = 0;
    $place = 1;
    foreach ($aggregated_rows as $i => $row) {
      $classes = [];
      $tr_id = $row['#tr_id'];
      if (isset($ties[$tr_id])) {
        $classes[] = 'rank-tie-' . $ties[$tr_id];
        $build['aggregated_table'][$i]['#attributes']['class'] = $classes;
      }
      $last_rank = $row['rank'];
      $students = implode(', ', array_unique($students_map[$tr_id] ?? [])) . '<br>';
      $row['school']['#markup'] = "$students <i>{$row['school']['#markup']}</i>";
      $build['aggregated_table'][] = [
        '#attributes' => ['class' => $classes],
        'place' => ['#markup' => $place],
        'school' => $row['school'],
        'rank' => ['#markup' => $row['rank']],
        'score' => ['#markup' => $row['score']],
        'overall' => ['#markup' => $row['overall']],
        'accuracy' => ['#markup' => $row['accuracy']],
        'recitation' => ['#markup' => $row['recitation']],
        'best_poem' => ['#markup' => $row['best_poem']],
        'best_poem_overall' => ['#markup' => $row['#best_overall']],
      ];
      $place++;
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

  /**
   * Custom access.
   */
  public function access(AccountInterface $account, NodeInterface $node, ?string $stream = NULL) {
    $nodes = $this->entityTypeManager()->getStorage('node')
      ->loadByProperties([
        'field_contest_association' => $node->id(),
      ]);
    $streams = array_map(function ($n) {
      return $n->field_language_stream->value ?? '_none';
    }, $nodes);
    $stream_is_valid = in_array($stream, $streams);
    $is_competition_admin = !$node->get('field_live_competition_admin')->isEmpty()
      && $node->get('field_live_competition_admin')->entity->id() === $account->id();
    $permission = $account->hasPermission('access live competition score result table');
    // If stream is null the page will redirect to the first valid
    // stream.
    return AccessResult::allowedIf($stream === NULL || (($permission || $is_competition_admin) && $stream_is_valid))
      ->cachePerUser()
      ->addCacheableDependency($account)
      ->addCacheTags(['node_list:team_regionals_entry']);
  }

}
