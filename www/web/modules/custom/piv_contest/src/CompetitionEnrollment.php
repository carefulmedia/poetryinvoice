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
        $url = Url::fromRoute('piv_contest.competition_entry_edit', [
            'user' => $teacher_id,
            'competition' => $competition_id,
            'competition_entry' => $entry->id(),
          ], [
            'query' => [
              'destination' => $destination,
            ]
          ]
        );

        $stream['student'] = $entry->field_student_name->value;
        $stream['recitations'] = "0 out of X";
        $stream['permission'] = TRUE;
        // Add link to edit existing entry.
        $stream['link'] = [
          '#type' => 'link',
          '#title' => 'edit',
          '#url' => $url ,
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
          ], [
            'query' => [
              'destination' => $destination,
            ]
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

  private function getNumberOfRecitations() {

  }

  private function getRequiredRecitationsForStream() {

  }

}
