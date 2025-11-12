<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\user\UserInterface;

/**
 * Controller for displaying user's journal poems.
 */
final class MyPoemsController extends ControllerBase {

  /**
   * Custom access verification.
   */
  public function access(UserInterface $user) {
    $current_user = $this->currentUser();

    // Admin users can see all users' pages.
    if ($current_user->hasPermission('administer users')) {
      return AccessResult::allowed();
    }

    // Users with student_poet role can only see their own page.
    if ($current_user->hasRole('student_poet') && $current_user->id() == $user->id()) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * Displays the user's journal poems grouped by journal year.
   */
  public function __invoke(UserInterface $user): array {
    $build = [];

    // Get all journal poems for this user.
    $poem_storage = $this->entityTypeManager()->getStorage('node');
    $poems = $poem_storage->loadByProperties([
      'type' => 'journal_poem',
      'uid' => $user->id(),
    ]);

    if (empty($poems)) {
      $build['no_poems'] = [
        '#markup' => '<p>' . $this->t('No journal poems found.') . '</p>',
      ];
      return $build;
    }

    // Group poems by journal year.
    $poems_by_year = [];
    $journal_month_storage = $this->entityTypeManager()->getStorage('journal_month');
    $journal_year_storage = $this->entityTypeManager()->getStorage('journal_year');

    foreach ($poems as $poem) {
      // Find the journal month that contains this poem.
      $journal_months = $journal_month_storage->loadByProperties([
        'field_journal_poems' => $poem->id(),
      ]);

      if ($journal_months) {
        $journal_month = reset($journal_months);

        // Find the journal year that contains this month.
        $journal_years = $journal_year_storage->loadByProperties([
          'field_journal_months' => $journal_month->id(),
        ]);

        if ($journal_years) {
          $journal_year = reset($journal_years);
          $year_label = $journal_year->label();

          if (!isset($poems_by_year[$year_label])) {
            $poems_by_year[$year_label] = [
              'year_entity' => $journal_year,
              'poems' => [],
            ];
          }

          $poems_by_year[$year_label]['poems'][] = $poem;
        }
      }
    }

    // Sort by year descending.
    krsort($poems_by_year);

    // Build the render array.
    foreach ($poems_by_year as $year_label => $year_data) {
      $build[$year_label] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Journal Year: @label', [
          '@label' => $year_label,
        ]),
        '#open' => TRUE,
        'poems' => [],
      ];

      foreach ($year_data['poems'] as $poem) {
        $poem_link = $poem->toLink($poem->getTitle(), 'canonical');
        $build[$year_label]['poems'][] = [
          '#theme' => 'item_list',
          '#items' => [[
            '#type' => 'inline_template',
            '#template' => '<div style="padding:10px"><strong>{{ link }}</strong><br>{{ "Acceptance Level"|t }}: {{ acceptance }}</div>',
            '#context' => [
              'link' => $poem_link->toString(),
              'acceptance' => $poem->field_acceptance_level->value ?? 'Not read',
            ],
          ],
          ],
        ];
      }
    }

    return $build;
  }

}
