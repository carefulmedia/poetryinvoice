<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\piv_live_competition\Helper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for PIV Live Competition routes.
 */
final class MonitorDashboardController extends ControllerBase {

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
   * Get judges in order, keyed by id.
   */
  private function getJudges(NodeInterface $node): array {
    // Performance judges are added to arrays keyed by language.
    $performance_judges = ['en' => [], 'fr' => []];
    foreach ($node->field_judges->referencedEntities() as $judge_paragraph) {
      if (!($judge = $judge_paragraph->field_judge->entity)) {
        continue;
      }
      $lang_id = $judge->preferred_langcode->value;
      $performance_judges[$lang_id][$judge->id()] = $judge;
    }

    // Return an array of entities keyed by id.
    $keyed = function ($entities) : array {
      $map = [];
      foreach ($entities as $entity) {
        $map[$entity->id()] = $entity;
      }
      return $map;
    };

    return [
      $performance_judges['en'],
      $performance_judges['fr'],
      $keyed($node->field_accuracy_judge_en->referencedEntities()),
      $keyed($node->field_accuracy_judge_fr->referencedEntities()),
    ];
  }

  /**
   * Builds the response.
   */
  public function __invoke(Request $request, NodeInterface $node): array {
    $active_round = $node->field_active_round->value;
    $target_svg = '<svg width="20px" height="20px" fill="#FFFFFF" version="1.1" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><path d="M31 15h-3.045c-0.481-5.829-5.127-10.47-10.955-10.952v-3.048c0-0.552-0.448-1-1-1s-1 0.448-1 1v3.048c-5.828 0.482-10.474 5.123-10.956 10.952h-3.045c-0.552 0-1 0.448-1 1s0.448 1 1 1h3.045c0.481 5.828 5.128 10.47 10.956 10.952v3.048c0 0.552 0.448 1 1 1s1-0.448 1-1v-3.048c5.828-0.482 10.474-5.123 10.955-10.952h3.045c0.552 0 1-0.448 1-1s-0.448-1-1-1zM15 6.050v8.95h-8.951c0.469-4.725 4.226-8.482 8.951-8.95zM6.048 17h8.951v8.95c-4.725-0.469-8.482-4.226-8.951-8.95zM17 25.951v-8.951h8.951c-0.469 4.725-4.226 8.482-8.951 8.95zM17 15v-8.95c4.725 0.469 8.483 4.226 8.951 8.95z"/></svg>';
    [$performance_en, $performance_fr, $accuracy_en, $accuracy_fr] = $this->getJudges($node);

    // Icons.
    $icon_complete = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => ['class' => ['score-icon', 'score-complete']],
      '#value' => '✓',
    ];
    $icon_incomplete = [
      '#attributes' => ['class' => ['score-icon', 'score-incomplete']],
      '#value' => '!',
    ] + $icon_complete;

    $header = [];
    foreach (($performance_en + $performance_fr) as $judge) {
      $header[$judge->id()] = $judge->getDisplayName();
    }
    foreach (($accuracy_en + $accuracy_fr) as $judge) {
      $markup = [
        '#markup' => $target_svg . ' ' . $judge->getDisplayName(),
        '#allowed_tags' => ['svg', 'path'],
      ];
      $header[$judge->id()] = ['data' => $markup];
    }

    // Students.
    $recitations = $this->helper->getRecitationsInOrder($node);
    $rows = [];
    $has_incomplete = FALSE;
    foreach ($recitations as $delta => $recitation) {
      // Get the id of all judges that scored this recitation, doesn't
      // matter the language.
      $judges_that_scored_this_recitation = [];
      $scores = array_merge(
        $recitation->field_accuracy_scores->referencedEntities(),
        $recitation->field_performance_scores->referencedEntities(),
      );
      foreach ($scores as $score) {
        $judges_that_scored_this_recitation[] = $score->judge->target_id;
      }

      // Start row.
      $row = [];
      $row_class = [];
      // Check if active round.
      $is_active_round = (($delta + 1) == $active_round);

      // Student name on the first column.
      $student_name = $this->helper->getStudentName($recitation);
      $row = [$student_name];

      $recitation_langcode = $recitation->field_poem->entity?->langcode->value ?? 'en';

      // Check if this row round is in the future compared to active
      // round.
      $is_future_round = ($delta + 1) > $active_round;

      // Iterate on all judges by the id.
      foreach (array_keys($header) as $judge_id) {
        $cell = [];
        // If judge is not a judge for the english language, make cell
        // disabled.
        $disabled = FALSE;
        if ($recitation_langcode == 'en') {
          if (!array_key_exists($judge_id, $performance_en) && !array_key_exists($judge_id, $accuracy_en)) {
            $disabled = TRUE;
          }
        }
        // Same for french.
        elseif ($recitation_langcode == 'fr') {
          if (!array_key_exists($judge_id, $performance_fr) && !array_key_exists($judge_id, $accuracy_fr)) {
            $disabled = TRUE;
          }
        }

        if ($disabled) {
          $cell['data'] = '';
          $cell['class'] = ['disabled'];
        }
        else {
          if ($is_future_round) {
            $cell = '';
          }
          else {
            // There is a score by this judge for this recitation.
            if (in_array($judge_id, $judges_that_scored_this_recitation)) {
              $cell['data'] = $icon_complete;
            }
            else {
              $cell['data'] = $icon_incomplete;
              if ($is_active_round) {
                $has_incomplete = TRUE;
              }
            }
          }
        }
        $row[] = $cell;
      }

      if ($is_active_round) {
        $row_class = $has_incomplete
          ? ['active-incomplete-round']
          : ['active-round'];
      }
      $rows[] = [
        'data' => $row,
        'class' => $row_class,
      ];
    }
    // The main build, the button and the table.
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['monitor-dashboard'],
      ],
      '#prefix' => "<div id='monitor-dashboard-table-wrapper'>",
      '#suffix' => "</div>",
    ];
    $build['form'] = $this->formBuilder()
      ->getForm('Drupal\piv_live_competition\Form\LiveCompetitionAdvanceRoundForm', $node, $has_incomplete);
    $build['table'] = [
      '#type' => 'table',
      '#header' => array_merge([$this->t('Student')], $header),
      '#rows' => $rows,
    ];

    // Return main content only on ajax calls.
    if ($request->isXmlHttpRequest()) {
      return $build;
    }

    // Otherwise, return a wrapper for the table and clone.
    $wrapper = [
      '#type' => 'container',
      '#attributes' => [
        'id' => ['monitor-dashboard-wrapper'],
      ],
      '#attached' => [
        'library' => ['piv_live_competition/auto-reload'],
      ],
      0 => $build,
    ];
    return $wrapper;
  }

  /**
   * Return a generated title.
   */
  public function title(NodeInterface $node) {
    return $this->t('Monitor Dashboard - @label', [
      '@label' => $node->label(),
    ]);
  }

}
