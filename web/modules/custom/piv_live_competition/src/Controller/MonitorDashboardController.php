<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\piv_live_competition\Helper;

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
   * Get judges in order.
   */
  private function getJudges(NodeInterface $node): array {
    // Performance judges are added to arrays keyed by language.
    $performance_judges = ['en' => [], 'fr' => []];
    foreach ($node->field_judges->referencedEntities() as $judge_paragraph) {
      if (!($judge = $judge_paragraph->field_judge->entity)) {
        continue;
      }
      $lang_id = $judge->preferred_langcode->value;
      $performance_judges[$lang_id][] = $judge;
    }

    // Merge all together.
    return [
      // Performance.
      array_merge(
        $performance_judges['en'],
        $performance_judges['fr'],
      ),
      // Accuracy.
      array_merge(
        $node->field_accuracy_judge_en->referencedEntities(),
        $node->field_accuracy_judge_fr->referencedEntities(),
      ),
    ];
  }

  /**
   * Builds the response.
   */
  public function __invoke(NodeInterface $node): array {
    $target_svg = '<svg width="20px" height="20px" fill="#FFFFFF" version="1.1" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><path d="M31 15h-3.045c-0.481-5.829-5.127-10.47-10.955-10.952v-3.048c0-0.552-0.448-1-1-1s-1 0.448-1 1v3.048c-5.828 0.482-10.474 5.123-10.956 10.952h-3.045c-0.552 0-1 0.448-1 1s0.448 1 1 1h3.045c0.481 5.828 5.128 10.47 10.956 10.952v3.048c0 0.552 0.448 1 1 1s1-0.448 1-1v-3.048c5.828-0.482 10.474-5.123 10.955-10.952h3.045c0.552 0 1-0.448 1-1s-0.448-1-1-1zM15 6.050v8.95h-8.951c0.469-4.725 4.226-8.482 8.951-8.95zM6.048 17h8.951v8.95c-4.725-0.469-8.482-4.226-8.951-8.95zM17 25.951v-8.951h8.951c-0.469 4.725-4.226 8.482-8.951 8.95zM17 15v-8.95c4.725 0.469 8.483 4.226 8.951 8.95z"/></svg>';

    [$performance_judges, $accuracy_judges] = $this->getJudges($node);
    $header = [];
    foreach ($performance_judges as $judge) {
      $header[$judge->id()] = $judge->getDisplayName();
    }
    foreach ($accuracy_judges as $judge) {
      $markup = [
        '#markup' => $target_svg . ' ' . $judge->getDisplayName(),
        '#allowed_tags' => ['svg', 'path'],
      ];
      $header[$judge->id()] = ['data' => $markup];
    }

    // Students.
    $recitations = $this->helper->getRecitationsInOrder($node);
    $rows = [];
    foreach ($recitations as $recitation) {
      $judged_scores = [];
      $scores = array_merge(
        $recitation->field_accuracy_scores->referencedEntities(),
        $recitation->field_performance_scores->referencedEntities(),
      );
      foreach ($scores as $score) {
        $judged_scores[] = $score->judge->target_id;
      }
      $student_name = $this->helper->getStudentName($recitation);
      $row = [$student_name];
      foreach (array_keys($header) as $judge_id) {
        $row[] = '';
      }
      $rows[] = $row;
    }
    $build['table'] = [
      '#type' => 'table',
      '#header' => array_merge([$this->t('Student')], $header),
      '#rows' => $rows,
    ];

    return $build;
  }

}
