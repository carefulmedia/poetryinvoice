<?php

namespace Drupal\piv_contest;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\piv_contest_competition\Entity\Competition;
use Drupal\piv_contest_competition_entry\Entity\CompetitionEntry;
use Drupal\piv_contest_competition_entry\Service\CompetitionLockService;
use Drupal\user\UserInterface;

/**
 * @file
 *  Service allowing management of competition enrollment for a school.
 */

class CompetitionEnrollment {

  private $is_initiated = FALSE;

  // School node.
  private $school;

  // Competition entity.
  private $competition;

  // Competition entry entities.
  private $entries = [];

  // User object.
  private $teacher;

  /** @var \Drupal\piv_contest_competition_entry\Service\CompetitionLockService $lockService */
  private $lockService;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, CompetitionLockService $lockService) {
    $this->entityTypeManager = $entity_type_manager;
    $this->lockService = $lockService;
  }

  public function init(
    NodeInterface $school,
    CompetitionInterface $competition,
    UserInterface $teacher
  ) {
    $this->school = $school;
    $this->competition = $competition;
    $this->teacher = $teacher;
    $this->is_initiated = TRUE;
  }

  /**
   * Get enrollment progress for each competition stream in an array that is
   * suitable to generate a html table.
   */
  public function getStreams() {
    if ($this->competition->field_team_competition->value) {
      return $this->getStreamsTeam();
    }
    return $this->getStreamsIndividual();
  }

  /**
   * Individual competition.
   */
  private function getStreamsIndividual() {
    // Headers row.
    $streams_header = [
      'name' => t('Stream'),
      'student' => t('Student'),
      'recitations' => t('Recitations'),
      'permission' => t('Permission'),
    ];

    $school_id = $this->school->id();
    $teacher_id = $this->teacher->id();
    $competition_id = $this->competition->id();

    // Set redirection after form submit.
    $destination = 'user/' . $teacher_id . '/competitions/' . $competition_id;

    $streams = [];
    foreach ($this->competition->field_competition_streams as $stream_field) {
      $stream = [];

      $stream_entity = $this->entityTypeManager
        ->getStorage('paragraph')
        ->load($stream_field->target_id);

      // Get the competition entry for this stream.
      $entry = $this->entityTypeManager
      ->getStorage('competition_entry')
      ->loadByProperties([
        'field_school' => $school_id,
        'field_competition' => $competition_id,
        'field_stream' => $stream_field->target_id,
      ]);
      $entry = array_pop($entry);

      if (!empty($entry)) {
        $editUrl = Url::fromRoute('piv_contest.competition_entry_edit', [
            'user' => $teacher_id,
            'competition' => $competition_id,
            'competition_entry' => $entry->id(),
          ], [
            'query' => [
              'destination' => $destination,
            ]
          ]
        );

        $deleteUrl = Url::fromRoute('entity.competition_entry.delete_form', [
          'competition_entry' => $entry->id(),
        ], [
          'query' => [
            'destination' => $destination,
          ]
        ]);

        $stream['student'] = $entry->field_student_name->value;
        $stream['recitations'] = t("@current out of @required", [
          '@current' => $this->getNumberOfValidRecitationsInEntry($entry),
          '@required' => $this->getRequiredRecitationsForStream($stream_entity),
        ]);
        $stream['permission'] = TRUE;
        $stream['is_recitations_completed'] = $this->isRecitationsCompleted($stream_entity, $entry);
        $stream['is_permissions_completed'] = $entry->field_release_form->entity != NULL;
        $stream['is_completed'] = (bool) $entry->field_complete->value;

        $editTitle = t('edit');
        if ($this->lockService->isLocked($entry)) {
          $editTitle = t('view');
        }

        if ($editUrl->access(\Drupal::currentUser())) {
          // Add link to edit existing entry.
          $stream['links'][] = [
            '#type' => 'link',
            '#title' => $editTitle,
            '#url' => $editUrl ,
            '#cache' => [
              'tags' => $entry->getCacheTags(),
            ],
          ];
        }

        if ($deleteUrl->access(\Drupal::currentUser())) {
          // Add link to delete existing entry.
          $stream['links'][] = [
            '#type' => 'link',
            '#title' => t('delete'),
            '#url' => $deleteUrl,
            '#cache' => [
              'tags' => $entry->getCacheTags(),
            ],
          ];
        }
      }

      // No competition entry for this stream.
      else {
        // Set a default value for each column.
        foreach ($streams_header as $k => $v) {
          $stream[$k] = NULL;
        }

        $addUrl = Url::fromRoute('piv_contest.competition_entry_add', [
          'user' => $this->teacher->id(),
          'competition' => $this->competition->id(),
          'stream' => $stream_entity->id(),
        ], [
          'query' => [
            'destination' => $destination,
          ]
        ]);

        if ($addUrl->access(\Drupal::currentUser())) {
          // Add link to create new entry.
          $stream['links'][] = [
            '#type' => 'link',
            '#title' => t('Add new entry'),
            '#url' => $addUrl,
          ];
        }
      }

      $stream['name'] = $stream_entity->field_label->value;
      $streams[] = $stream;
    }

    // Prepend the table headers.
    array_unshift($streams_header, $streams);
    return $streams;
  }

  public function isRecitationsCompleted(EntityInterface $stream, EntityInterface $competitionEntry): bool {
    if ($this->getNumberOfValidRecitationsInEntry($competitionEntry) >= $this->getRequiredRecitationsForStream($stream)) {
      return TRUE;
    }

    return FALSE;
  }

  public function getNumberOfValidRecitationsInEntry(EntityInterface $competitionEntry): int {
    $competition = $competitionEntry->field_competition->entity;
    $is_online = (bool) $competition->field_online_competition->value;

    $number_of_valid_poems = 0;
    foreach ($competitionEntry->field_recitations as $recitation) {
      $entity = $recitation->entity;


      $video = $entity->field_recitation_video->entity;
      // IF it's an online competition recitation must also contain a video.
      if ($is_online && !$video) {
        continue;
      }

      if ($poem = $entity->field_poem->entity) {
        $number_of_valid_poems++;
      }
    }

    return $number_of_valid_poems;
  }

  public function getRequiredRecitationsForStream(EntityInterface $stream): int {
    return count($stream->field_stream_languages) * (int) $stream->field_min_recitations->value;
  }

  public function isCompetitionEntryCompleted(CompetitionEntry $entity): bool {
    $stream = $entity->getStream();
    if (!$stream) {
      return FALSE;
    }

    $number_valid_recitations = $this->getNumberOfValidRecitationsInEntry($entity);
    $required_recitations_stream = $this->getRequiredRecitationsForStream($stream);
    if ($number_valid_recitations >= $required_recitations_stream && $entity->field_release_form->entity != NULL) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Team competition.
   */
  private function getStreamsTeam() {
    foreach ($this->competition->field_competition_streams as $stream_field) {
      // student name is per recitation for team competitions, not per entry.
    }
  }

  private function getNumberOfRecitations() {

  }

}
