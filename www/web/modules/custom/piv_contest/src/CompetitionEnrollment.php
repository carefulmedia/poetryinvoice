<?php

namespace Drupal\piv_contest;

use Drupal\node\NodeInterface;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Set the competition and school.
   */
  public function init(NodeInterface $school, CompetitionInterface $competition) {
    $this->school = $school;
    $this->competition = $competition;
    $this->is_initiated = TRUE;
  }

  /**
   * Get enrollment progress for each competition stream.
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

      ksm($stream_entity);

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
      }
      // Set a default value for each column.
      else {
        foreach ($streams_header as $k => $v) {
          $stream[$k] = NULL;
        }
      }
      $stream['name'] = $stream_entity->field_label->value;
      $streams[] = $stream;
    }

    // Prepend the headers.
    array_unshift($streams_header, $streams);
    return $streams;
  }

  /**
   * Team competition.
   */
  private function getStreamsTeam() {

    foreach ($this->competition->field_competition_streams as $stream_field) {
      // student name is per recitation.
    }
  }
}
