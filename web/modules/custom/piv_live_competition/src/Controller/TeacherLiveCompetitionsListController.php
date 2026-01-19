<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\node\NodeInterface;
use Drupal\User\UserInterface;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Datetime\DrupalDateTime;

final class TeacherLiveCompetitionsListController extends ControllerBase {

  /**
   * Get live competitions and entries for a teacher's school.
   */
  public function getCompetitionsAndEntries($teacher_school_id): array {
    $yesterday = new DrupalDateTime('yesterday');
    $yesterday_formatted = $yesterday->format('Y-m-d\T00:00:00');

    $competition_query = $this->entityTypeManager()->getStorage('node')->getQuery();
    $competition_ids = $competition_query->condition('type', 'competition')
      ->condition('field_winners_announced', $yesterday_formatted, '>')
      ->condition('field_active_round', 0)
      ->sort('field_winners_announced')
      ->accessCheck(TRUE)
      ->execute();

    if (empty($competition_ids)) {
      return ['by_invitation' => [], 'no_invitation' => []];
    }

    $competitions = $this->entityTypeManager()->getStorage('node')->loadMultiple($competition_ids);

    // Get all users (teachers) at this school.
    $user_query = $this->entityTypeManager()->getStorage('user')->getQuery();
    $teacher_uids = $user_query->condition('field_school', $teacher_school_id)
      ->accessCheck(TRUE)
      ->execute();

    // Get all entries from teachers at this school.
    $entries_by_competition = [];
    if (!empty($teacher_uids)) {
      $entry_query = $this->entityTypeManager()->getStorage('node')->getQuery();
      $entry_ids = $entry_query->condition('type', 'team_regionals_entry')
        ->condition('uid', $teacher_uids, 'IN')
        ->accessCheck(TRUE)
        ->execute();

      if (!empty($entry_ids)) {
        $entries = $this->entityTypeManager()->getStorage('node')->loadMultiple($entry_ids);
        foreach ($entries as $entry) {
          if ($entry->hasField('field_contest_association') && !$entry->get('field_contest_association')->isEmpty()) {
            $competition_id = $entry->get('field_contest_association')->target_id;
            if (!isset($entries_by_competition[$competition_id])) {
              $entries_by_competition[$competition_id] = [];
            }
            $entries_by_competition[$competition_id][] = $entry;
          }
        }
      }
    }

    // Organize competitions by invitation status.
    $competitions_by_invitation = [];
    $competitions_no_invitation = [];

    foreach ($competitions as $competition) {
      $competition_id = $competition->id();
      $is_invited = FALSE;

      // Check if the competition has invited schools.
      if ($competition->hasField('field_invited_schools') && !$competition->get('field_invited_schools')->isEmpty()) {
        $invited_schools = $competition->get('field_invited_schools')->referencedEntities();
        $invited_school_ids = array_map(static fn($school) => $school->id(), $invited_schools);

        if (in_array($teacher_school_id, $invited_school_ids, TRUE)) {
          $is_invited = TRUE;
        }
        else {
          // Skip this competition if it has invited schools but this school isn't invited.
          continue;
        }
      }

      // Get entries for this competition.
      $competition_entries = $entries_by_competition[$competition_id] ?? [];

      $competition_data = [
        'competition' => $competition,
        'entries' => $competition_entries,
      ];

      // Group by invitation status.
      if ($is_invited) {
        $competitions_by_invitation[$competition_id] = $competition_data;
      }
      else {
        $competitions_no_invitation[$competition_id] = $competition_data;
      }
    }

    return [
      'by_invitation' => $competitions_by_invitation,
      'no_invitation' => $competitions_no_invitation,
    ];
  }

  /**
   * Check if the teacher can access this page.
   */
  public function access(AccountInterface $account, UserInterface $user) {
    // Check if the user is accessing their own page.
    if ($account->id() !== $user->id()) {
      return AccessResult::forbidden()->cachePerUser();
    }

    // Check if the user has the teacher role.
    if (!$account->hasRole('teacher')) {
      return AccessResult::forbidden()
        ->cachePerUser()
        ->addCacheableDependency($user);
    }

    // Check if teacher has a school assigned.
    $teacher_school = _piv_live_competition_get_teacher_school($account);
    if (!$teacher_school) {
      return AccessResult::forbidden()
        ->cachePerUser()
        ->addCacheableDependency($user);
    }

    return AccessResult::allowed()
      ->cachePerUser()
      ->addCacheableDependency($user)
      ->addCacheTags(['node_list:team_regionals_entry']);
  }

  /**
   * Helper method to check if competition is editable.
   */
  private function isCompetitionEditable(NodeInterface $competition): bool {
    return $competition->hasField('field_active_round')
      && (int) $competition->get('field_active_round')->value === 0;
  }

  /**
   * Helper method to check if maximum entries per school has been reached.
   *
   * Here we return an array with metadata telling us what the maximum is, and if it is reached.
   */
  private function getMaxEntriesInfo($competition, int $entry_count): array {
    if (!$competition->hasField('field_maximum_entries_per_school')) {
      return ['reached' => FALSE, 'max' => NULL];
    }

    $max_entries = $competition->get('field_maximum_entries_per_school')->value;
    if (empty($max_entries) || $max_entries <= 0) {
      return ['reached' => FALSE, 'max' => NULL];
    }

    return [
      'reached' => $entry_count >= $max_entries,
      'max' => $max_entries,
    ];
  }

  /**
   * Helper method to build URL for adding a new entry.
   */
  private function buildAddEntryUrl(string $competition_id): Url {
    $current_path = \Drupal::request()->getRequestUri();
    return Url::fromRoute(
      'node.add',
      [
        'node_type' => 'team_regionals_entry',
      ],
      [
        'query' => [
          'destination' => $current_path,
          'edit' => [
            'field_contest_association' => ['widget' => $competition_id],
          ],
        ],
      ]
    );
  }

  /**
   * Helper method to build entry item markup.
   */
  private function buildEntryItem(NodeInterface $entry, bool $is_editable, int $index): array {
    $entry_markup = $entry->label() . ' #' . $index + 1;

    if ($is_editable) {
      $current_path = \Drupal::request()->getRequestUri();
      $edit_url = $entry->toUrl('edit-form', [
        'query' => [
          'destination' => $current_path,
        ],
      ]);
      $delete_url = $entry->toUrl('delete-form', [
        'query' => [
          'destination' => $current_path,
        ],
      ]);

      // Add Edit/Delete links.
      $entry_markup .= ' [';
      if ($entry->access('update')) {
        $entry_markup .= '<a href="' . $edit_url->toString() . '">' . $this->t('Edit') . '</a>';
      }

      if ($entry->access('delete')) {
        if ($entry->access('update')) {
          $entry_markup .= ' | ';
        }
        $entry_markup .= '<a href="' . $delete_url->toString() . '">' . $this->t('Delete') . '</a>';
      }
      $entry_markup .= ']';
    }

    return ['#markup' => $entry_markup];
  }

  /**
   * Helper method to build add entry link or max reached message.
   */
  private function buildAddEntryOrMaxMessage(
    string $competition_id,
    bool $is_editable,
    array $max_info,
    bool $has_entries = FALSE,
  ): array {
    $build = [];

    if ($is_editable && !$max_info['reached']) {
      $add_entry_url = $this->buildAddEntryUrl($competition_id);
      $build = [
        '#type' => 'link',
        '#title' => $this->t($has_entries ? 'Create another entry' : 'Create new entry'),
        '#url' => $add_entry_url,
        '#attributes' => ['class' => ['button', 'button--small']],
      ];

      if ($has_entries) {
        $build['#prefix'] = '<p>';
        $build['#suffix'] = '</p>';
      }
      else {
        $build['#prefix'] = ' ';
      }
    }
    elseif ($max_info['reached']) {
      $message = $this->formatPlural(
        $max_info['max'],
        'Maximum of @count entry reached.',
        'Maximum of @count entries reached.',
      );
      if ($has_entries) {
        $build = ['#markup' => '<div><em class="error-text">' . $message . '</em></div>'];
      }
      else {
        $build = ['#markup' => ' <em>(' . $message . ')</em>'];
      }
    }

    return $build;
  }

  /**
   * Helper method to build a single competition section.
   */
  private function buildCompetitionSection(
    NodeInterface $competition,
    array $entries,
    string $section_prefix,
  ): array {
    $competition_id = $competition->id();
    $is_editable = $this->isCompetitionEditable($competition);
    $max_info = $this->getMaxEntriesInfo($competition, count($entries));

    $competition_url = Url::fromRoute('piv_live_competition.live_competition_score_results_table', [
      'node' => $competition_id,
    ]);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['competition-group']],
      '#suffix' => '<hr>',
    ];

    $build['title'] = [
      '#type' => 'link',
      '#title' => $competition->label(),
      '#url' => $competition_url,
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    ];

    if (!empty($entries)) {
      $entry_items = array_map(
        fn ($entry, $index) => $this->buildEntryItem($entry, $is_editable, $index),
        $entries,
        array_keys($entries),
      );

      $build['entries'] = [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => $entry_items,
      ];

      $add_entry_element = $this->buildAddEntryOrMaxMessage($competition_id, $is_editable, $max_info, TRUE);
      if (!empty($add_entry_element)) {
        $build['add_another'] = $add_entry_element;
      }
    }
    else {
      $build['add_entry'] = [
        '#markup' => '<div><em>' . $this->t('No entries yet.') . '</em>',
      ];

      $add_entry_element = $this->buildAddEntryOrMaxMessage($competition_id, $is_editable, $max_info, FALSE);
      if (!empty($add_entry_element)) {
        $build['add_entry_link'] = $add_entry_element;
      }

      $build['add_entry']['#suffix'] = '</div>';
    }

    return $build;
  }

  /**
   * Builds a list of live competition entries grouped by competition.
   */
  public function __invoke(UserInterface $user): array {
    $teacher_school = _piv_live_competition_get_teacher_school(\Drupal::currentUser());
    $teacher_school_id = $teacher_school?->id();

    $grouped_data = $teacher_school_id
      ? $this->getCompetitionsAndEntries($teacher_school_id)
      : ['by_invitation' => [], 'no_invitation' => []];

    $build = [];
    $build['#cache']['tags'][] = 'node_list:team_regionals_entry';
    $build['#cache']['tags'][] = 'node_list:competition';

    // Build "By Invitation" section.
    if (!empty($grouped_data['by_invitation'])) {
      $build['by_invitation_title'] = [
        '#markup' => '<h2>' . $this->t('By Invitation') . '</h2>',
      ];

      foreach ($grouped_data['by_invitation'] as $competition_data) {
        $competition = $competition_data['competition'];
        $entries = $competition_data['entries'];
        $competition_id = $competition->id();

        $build['by_invitation_comp_' . $competition_id] = $this->buildCompetitionSection(
          $competition,
          $entries,
          'by_invitation'
        );
      }
    }

    // Build "No Invitation" section.
    if (!empty($grouped_data['no_invitation'])) {
      $build['no_invitation_title'] = [
        '#markup' => '<h2>' . $this->t('Open to all schools') . '</h2>',
      ];

      foreach ($grouped_data['no_invitation'] as $competition_data) {
        $competition = $competition_data['competition'];
        $entries = $competition_data['entries'];
        $competition_id = $competition->id();

        $build['no_invitation_comp_' . $competition_id] = $this->buildCompetitionSection(
          $competition,
          $entries,
          'no_invitation'
        );
      }
    }

    // If no competitions found, show a message.
    if (empty($grouped_data['by_invitation']) && empty($grouped_data['no_invitation'])) {
      $build['empty'] = [
        '#markup' => '<p>' . $this->t('No active competitions.') . '</p>',
      ];
    }

    return $build;
  }

}
