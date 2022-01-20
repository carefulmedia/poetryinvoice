<?php

namespace Drupal\piv_contest;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Set the competition and school.
   */
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
      'poems' => t('Poems'),
      'videos' => t('Videos'),
      'permission' => t('Permission'),
    ];

    $streams = [];
    foreach ($this->competition->field_competition_streams as $stream_field) {
      $stream = [];
      $stream_entity = $this->entityTypeManager->getStorage('paragraph')->load($stream_field->target_id);

      // Get the competition entry for this stream.
      $entry = $this->entityTypeManager->getStorage('competition_entry')->loadByProperties([
        'field_school' => $this->school->id(),
        'field_competition' => $this->competition->id(),
        'field_stream' => $stream_field->target_id,
      ]);
      $entry = array_pop($entry);

      if (!empty($entry)) {
        $stream['student'] = $entry->field_student_name->value;
        $stream['poems'] = "0 out of X";
        $stream['videos'] = "0 out of Y";
        $stream['permission'] = TRUE;
        // Add link to edit existing entry.
        $stream['link'] = [
          '#type' => 'link',
          '#title' => 'edit',
          '#url' => Url::fromRoute('piv_contest.competition_entry_edit', [
            'user' => $this->teacher->id(),
            'competition' => $this->competition->id(),
            'competition_entry' => $entry->id(),
          ]),
        ];
      }

      // No competition entry for this stream.
      else {
        // Set a default value for each column.
        foreach ($streams_header as $k => $v) {
          $stream[$k] = NULL;
        }
        // Add link to create new entry.
        $stream['link'] = [
          '#type' => 'link',
          '#title' => t('Add new entry'),
          '#url' => Url::fromRoute('piv_contest.competition_entry_add', [
            'user' => $this->teacher->id(),
            'competition' => $this->competition->id(),
            'stream' => $stream_entity->id(),
          ]),
        ];
      }

      $stream['name'] = $stream_entity->field_label->value;
      $streams[] = $stream;
    }

    // Prepend the table headers.
    array_unshift($streams_header, $streams);
    return $streams;
  }

  /**
   * Team competition.
   */
  private function getStreamsTeam() {
    foreach ($this->competition->field_competition_streams as $stream_field) {
      // student name is per recitation for team competitions, not per entry.
    }
  }
}
