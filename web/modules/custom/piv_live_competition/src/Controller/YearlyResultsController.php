<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\piv_live_competition\Helper;
use Drupal\piv_live_competition\LiveCompetitionScoreService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for PIV Live Competition routes.
 */
final class YearlyResultsController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'piv_live_competition.helper')]
    protected readonly Helper $helper,
    #[Autowire(service: 'piv_live_competition.score_service')]
    protected readonly LiveCompetitionScoreService $scoreService,
  ) {}

  /**
   * Builds the response.
   */
  public function __invoke(string $year): array {
    // Validate year format.
    if (!preg_match('/^\d{4}$/', $year)) {
      return [
        '#markup' => $this->t('Invalid year format.'),
      ];
    }

    $year_int = (int) $year;

    $start_date = "$year-01-01T00:00:00";
    $end_date = "$year-12-31T23:59:59";
    $now = date('Y-m-d\TH:i:s');

    // Query competitions for our time period.
    // We use the contest date here since it's the date where winners are announced.
    // If there are no entries and scores, they still won't be displayed.
    $query = $this->entityTypeManager()->getStorage('node')->getQuery();
    $query->condition('type', 'competition')
      ->condition('field_winners_announced', $start_date, '>=')
      ->condition('field_winners_announced', $end_date, '<=')
      ->condition('field_winners_announced', $now, '<=')
      ->sort('field_winners_announced', 'ASC')
      ->accessCheck(FALSE);

    $competition_ids = $query->execute();

    if (empty($competition_ids)) {
      return [
        '#markup' => $this->t('No competitions found for @year.', ['@year' => $year]),
      ];
    }

    $competitions = $this->entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($competition_ids);

    $competitions_data = [];
    $streams_options = $this->helper->getStreams();

    foreach ($competitions as $competition) {
      // Get competition metadata.
      $competition_name = $competition->label();
      $host_school = $competition->getOwner()?->field_school->entity?->label() ?? $this->t('Unknown');

      $winners_announced = $competition->field_winners_announced->date;
      $contest_date = $winners_announced ? $winners_announced->format('F j, Y') : $this->t('Unknown');

      $language_stream_value = $competition->field_language_stream->value;

      // Determine which streams to process.
      if ((int) $language_stream_value === 3) {
        // All streams - process 0, 1, 2 separately.
        $streams_to_process = [0, 1, 2];
      }
      else {
        $streams_to_process = [$language_stream_value];
      }

      $stream_results = [];

      foreach ($streams_to_process as $stream) {
        // Check if there are entries for this stream.
        $check_query = $this->entityTypeManager()
          ->getStorage('node')
          ->getQuery()
          ->condition('type', 'team_regionals_entry')
          ->condition('field_contest_association', $competition->id())
          ->condition('field_language_stream', $stream)
          ->accessCheck(FALSE)
          ->range(0, 1);

        $has_entries = (bool) $check_query->execute();

        if (!$has_entries) {
          continue;
        }

        // Calculate results.
        $results = $this->scoreService->calculateCompetitionResults($competition, $stream);

        if (empty($results['standings'])) {
          continue;
        }

        // Get judges for this stream.
        $all_judges = $this->helper->getJudges($competition);
        [$perf_en, $perf_fr, $acc_en, $acc_fr] = $all_judges;

        $judges = [];
        if ($stream == 0) {
          // English stream.
          $judges = array_merge(array_values($perf_en), array_values($acc_en));
        }
        elseif ($stream == 1) {
          // French stream.
          $judges = array_merge(array_values($perf_fr), array_values($acc_fr));
        }
        elseif ($stream == 2) {
          // Bilingual stream.
          $judges = array_merge(
            array_values($perf_en),
            array_values($perf_fr),
            array_values($acc_en),
            array_values($acc_fr)
          );
        }

        $judge_names = array_map(static fn ($j) => $j->getDisplayName(), $judges);

        $placements = $this->scoreService->getTopPlacements(
          $results['standings'],
          $results['total_schools']
        );

        $participating = $this->scoreService->getParticipatingSchools(
          $results['standings'],
          $results['total_schools']
        );

        // Build placements array with placement positions (1st, 2nd, 3rd, 4th).
        $placements_formatted = [];
        $position = 1;
        foreach ($placements as $placement) {
          $placements_formatted[] = [
            'rank' => $position,
            'school_name' => $placement['school_name'],
            'reciters' => $placement['reciters'],
          ];
          $position++;
        }

        $stream_results[] = [
          'language' => $streams_options[$stream] ?? '',
          'judges' => $judge_names,
          'placements' => $placements_formatted,
          'participating_schools' => $participating,
        ];
      }

      // Only add competition if it has results.
      if (!empty($stream_results)) {
        $competitions_data[] = [
          'name' => $competition_name,
          'host_school' => $host_school,
          'contest_date' => $contest_date,
          'contest_language_stream' => $streams_options[$language_stream_value],
          'streams' => $stream_results,
        ];
      }
    }

    return [
      '#theme' => 'piv_live_competition_yearly_results',
      '#year' => $year,
      '#competitions' => $competitions_data,
      '#cache' => [
        'tags' => [
          'node_list:competition',
          'node_list:team_regionals_entry',
        ],
      ],
    ];
  }

  /**
   * Returns a generated title.
   */
  public function title(string $year): TranslatableMarkup {
    return $this->t('Live Competition Results - @year', ['@year' => $year]);
  }

}
