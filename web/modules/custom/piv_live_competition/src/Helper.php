<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Helper class for Live Competitions ('competition' nodes).
 */
final class Helper {

  /**
   * Get judges in order, keyed by id.
   */
  public function getJudges(NodeInterface $node): array {
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
   * Get prompters in order, keyed by id.
   */
  public function getPrompters(NodeInterface $node): array {
    // Return an array of entities keyed by id.
    $keyed = function ($entities) : array {
      $map = [];
      foreach ($entities as $entity) {
        $map[$entity->id()] = $entity;
      }
      return $map;
    };

    return [
      $keyed($node->field_prompters_en->referencedEntities()),
      $keyed($node->field_prompters_fr->referencedEntities()),
    ];
  }

  /**
   * Constructs a Helper object.
   */
  public function __construct(
    private readonly CacheBackendInterface $cacheDefault,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Get the options from the Language Stream field.
   */
  public function getStreams() {
    // Get all existing streams based on the options set on
    // "Language Stream (for finals))" field.
    $field_language_stream = $this->entityFieldManager
      ->getFieldStorageDefinitions('node')['field_language_stream'];
    $streams = $field_language_stream->getSetting('allowed_values');
    $streams['_none'] = '- None -';
    return $streams;
  }

  /**
   * Get a list of recitations in the correct sort order.
   */
  public function getRecitationsInOrder(NodeInterface $node) : array {
    $cid = "piv_live_competition:recitations_in_order:{$node->id()}";
    if ($cache = $this->cacheDefault->get($cid)) {
      return $cache->data;
    }

    $team_regionals = $this->entityTypeManager->getStorage('node')
      ->loadByProperties([
        'type' => 'team_regionals_entry',
        'field_contest_association' => $node->id(),
      ]);
    $entries = [];
    foreach ($team_regionals as $team_regional) {
      $entries = array_merge($entries, $team_regional->field_tr_student->referencedEntities());
    }

    // Sort by order, if its the same value then use the id, if no value
    // is set then its infinite (push to last).
    usort($entries, function ($a, $b) {
      $a_order = $a->field_recitation_order->value ?? INF;
      $b_order = $b->field_recitation_order->value ?? INF;
      if ($a_order == $b_order) {
        return $a->id() <=> $b->id();
      }
      return $a_order <=> $b_order;
    });

    $tags = ['paragraph_list:tr_student'];
    $this->cacheDefault->set($cid, $entries, CacheBackendInterface::CACHE_PERMANENT, $tags);
    return $entries;
  }

  /**
   * Check if all judges have scored the active round recitation.
   *
   * Returns TRUE if every applicable judge (matched by recitation language)
   * has submitted a score for the active round. Returns FALSE if the round
   * is not yet started or already completed.
   *
   * @param \Drupal\Node\NodeInterface $competition
   *   The Live Competition to check.
   */
  public function isActiveRoundComplete(NodeInterface $competition) : bool {
    $active_round = $competition->field_active_round->value;
    if (!is_numeric($active_round) || $active_round <= 0) {
      return FALSE;
    }

    $recitations = $this->getRecitationsInOrder($competition);
    $total_rounds = count($recitations);
    if ($active_round > $total_rounds) {
      return FALSE;
    }

    // Get the recitation for the active round (delta = round - 1).
    $recitation_cached = $recitations[$active_round - 1] ?? NULL;
    if (!$recitation_cached) {
      return FALSE;
    }

    // Reload fresh from storage to get up-to-date score references.
    $recitation = $this->entityTypeManager->getStorage('paragraph')
      ->loadUnchanged($recitation_cached->id());
    if (!$recitation) {
      return FALSE;
    }

    [$performance_en, $performance_fr, $accuracy_en, $accuracy_fr] = $this->getJudges($competition);
    $recitation_langcode = $recitation->field_poem->entity?->langcode->value ?? 'en';

    // Determine which judges apply for this recitation's language.
    if ($recitation_langcode === 'en') {
      $applicable_judges = array_merge(array_keys($performance_en), array_keys($accuracy_en));
    }
    elseif ($recitation_langcode === 'fr') {
      $applicable_judges = array_merge(array_keys($performance_fr), array_keys($accuracy_fr));
    }
    else {
      $applicable_judges = array_keys($performance_en + $performance_fr + $accuracy_en + $accuracy_fr);
    }

    if (empty($applicable_judges)) {
      return FALSE;
    }

    // Collect all judge IDs that have scored this recitation.
    $scores = array_merge(
      $recitation->field_accuracy_scores->referencedEntities(),
      $recitation->field_performance_scores->referencedEntities(),
    );
    $scored_judge_ids = [];
    foreach ($scores as $score) {
      $scored_judge_ids[] = $score->judge->target_id;
    }

    // Every applicable judge must have scored.
    foreach ($applicable_judges as $judge_id) {
      if (!in_array($judge_id, $scored_judge_ids)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Auto-advance the active round for Team Regional competitions.
   *
   * If all judges have submitted scores for the active round, increment
   * field_active_round so the auto-reload JS can pick up the change.
   *
   * @param \Drupal\Node\NodeInterface $competition
   *   The Live Competition to check.
   */
  public function maybeAutoAdvanceRound(NodeInterface $competition) : void {
    if ($competition->field_level->value !== 'Team Regional') {
      return;
    }

    // Reload fresh to avoid stale field_active_round value.
    $competition = $this->entityTypeManager->getStorage('node')->loadUnchanged($competition->id());
    if (!$competition) {
      return;
    }

    $recitations = $this->getRecitationsInOrder($competition);
    $total_rounds = count($recitations);
    $active_round = $competition->field_active_round->value;

    // Don't advance past the last round.
    if ($active_round >= $total_rounds) {
      return;
    }

    if ($this->isActiveRoundComplete($competition)) {
      $new_round = $active_round + 1;
      $competition->field_active_round = $new_round;
      $competition->save();
    }
  }

  /**
   * Get student name from recitation.
   */
  public function getStudentName(ParagraphInterface $recitation) : ?string {
    return $recitation->field_stage_name->value == 1 && !empty($recitation->field_student_name_1)
      ? $recitation->field_student_name_1->value
      : $recitation->field_legal_name->value;
  }

  /**
   * Automatically generate and save recitation order for a competition.
   *
   * Assigns field_recitation_order values to all tr_student paragraphs so
   * that recitations are grouped by round (delta), with schools alternating
   * within each round. The school that went last in round N cannot go first
   * in round N+1.
   */
  public function generateRecitationOrder(NodeInterface $node): void {
    $team_regionals = array_values($this->entityTypeManager->getStorage('node')
      ->loadByProperties([
        'type' => 'team_regionals_entry',
        'field_contest_association' => $node->id(),
      ]));

    if (empty($team_regionals)) {
      return;
    }

    // Cache student counts to avoid repeated field item list traversals.
    $student_counts = [];
    foreach ($team_regionals as $entry) {
      $student_counts[$entry->id()] = count($entry->field_tr_student);
    }

    $max_rounds = max($student_counts) ?: 0;
    if ($max_rounds === 0) {
      return;
    }

    $last_entry_id = NULL;
    $order = 1;

    for ($round = 0; $round < $max_rounds; $round++) {
      // Only include entries that have a student at this round delta.
      $entries_in_round = array_values(array_filter(
        $team_regionals,
        fn($entry) => $student_counts[$entry->id()] > $round
      ));

      shuffle($entries_in_round);

      // Constraint: the school that went last in round N cannot go first in
      // round N+1. Only applies when there are at least 2 entries.
      $count = count($entries_in_round);
      if ($last_entry_id !== NULL && $count > 1) {
        if ($entries_in_round[0]->id() === $last_entry_id) {
          $swap_idx = random_int(1, $count - 1);
          [$entries_in_round[0], $entries_in_round[$swap_idx]] = [$entries_in_round[$swap_idx], $entries_in_round[0]];
        }
      }

      foreach ($entries_in_round as $entry) {
        $recitation = $entry->field_tr_student->get($round)->entity;
        if ($recitation) {
          $recitation->field_recitation_order = $order;
          $recitation->save();
          $order++;
        }
      }

      $last = end($entries_in_round);
      $last_entry_id = $last ? $last->id() : NULL;
    }

    // Explicitly invalidate the cache for this competition's recitation order.
    $this->cacheDefault->delete("piv_live_competition:recitations_in_order:{$node->id()}");
  }

  /**
   * Check if competition has started.
   */
  public function hasCompetitionStarted(NodeInterface $competition): bool {
    return ((int) $competition->field_active_round->value > 0);
  }

  /**
   * Check if competition closing date has passed.
   */
  public function hasCompetitionClosingDatePassed(NodeInterface $competition): bool {
    $deadline = $competition->field_submission_deadline?->date;
    if (!$deadline) {
      return FALSE;
    }

    return $deadline->getTimestamp() < $this->time->getRequestTime();
  }

  /**
   * Get a teacher's school.
   */
  public function getTeacherSchool(AccountInterface $account): ?NodeInterface {
    if ($account->isAnonymous()) {
      return NULL;
    }

    $user = $this->entityTypeManager
      ->getStorage('user')
      ->load($account->id());

    return $user ? $user->field_school?->entity : NULL;
  }

  /**
   * Check if the teacher's school is invited to a competition.
   */
  public function isTeacherSchoolInvited(NodeInterface $competition, AccountInterface $teacher): bool {
    $invited_school_ids = $this->getInvitedSchoolIds($competition);

    if (empty($invited_school_ids)) {
      // No invitation required.
      return TRUE;
    }

    $teacher_school = $this->getTeacherSchool($teacher);
    if (!$teacher_school) {
      return FALSE;
    }

    return in_array($teacher_school->id(), $invited_school_ids);
  }

  /**
   * Check if a competition is maxed out for a teacher's school.
   */
  public function isCompetitionMaxedOutForTeacherSchool(NodeInterface $competition, AccountInterface $teacher, $exclude_entry_id = NULL): bool {
    $max_entries_per_school = $competition->field_maximum_entries_per_school->value;
    if (empty($max_entries_per_school) || $max_entries_per_school <= 0) {
      // No maximum required.
      return FALSE;
    }

    $teacher_school = $this->getTeacherSchool($teacher);
    if (!$teacher_school) {
      return FALSE;
    }

    $existing_entries_count = $this->countSchoolEntries($competition->id(), $teacher_school->id(), $exclude_entry_id);
    return $existing_entries_count >= $max_entries_per_school;
  }

  /**
   * Get invited school IDs from a competition.
   */
  private function getInvitedSchoolIds(NodeInterface $competition): array {
    if (empty($competition->field_invited_schools)) {
      return [];
    }

    return array_column($competition->field_invited_schools->getValue(), 'target_id');
  }

  /**
   * Count team_regionals_entry nodes for a school and competition.
   */
  private function countSchoolEntries($competition_id, $school_id, $exclude_entry_id = NULL): int {
    $user_query = $this->entityTypeManager->getStorage('user')->getQuery();
    $teacher_uids = $user_query->condition('field_school', $school_id)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($teacher_uids)) {
      return 0;
    }

    $entry_query = $this->entityTypeManager->getStorage('node')->getQuery();
    $entry_query->condition('type', 'team_regionals_entry')
      ->condition('field_contest_association', $competition_id)
      ->condition('uid', $teacher_uids, 'IN')
      ->accessCheck(FALSE);

    if (is_numeric($exclude_entry_id)) {
      $entry_query->condition('nid', $exclude_entry_id, '<>');
    }

    return (int) $entry_query->count()->execute();
  }

}
