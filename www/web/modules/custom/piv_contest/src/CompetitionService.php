<?php

namespace Drupal\piv_contest;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\piv_contest_competition_entry\CompetitionEntryInterface;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * CompetitionService service.
 */
class CompetitionService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  protected $competition;

  /**
   * Constructs a CompetitionService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Check if a school can manage the entries for a competition.
   *
   * Checks if the competition is active and if the school is invited or the
   * competition is not invite only.
   */
  public function schoolCanManageEntriesForCompetition(NodeInterface $school, CompetitionInterface $competition) {
    if (!$competition->field_active->value) {
      return FALSE;
    }

    $now = new DrupalDateTime('now');
    $open_date = $competition->field_open_date->date;
    $closing_date = $competition->field_submission_deadline->date;
    if (!$open_date || !$closing_date || $open_date > $now || $closing_date < $now) {
      return FALSE;
    }

    if ($competition->field_by_invitation_only->value) {
      $invited_schools = array_column($competition->field_invited_schools->getValue(), 'target_id');
      if (!in_array($school->id(), $invited_schools)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Get the required min and max number of entries per stream on competition.
   *
   * @todo for now lets consider the first stream only.
   */
  public function verifyEntryIsComplete(CompetitionEntryInterface $competition_entry, &$missing = []) {
    $competition = $competition_entry->field_competition->entity;
    if (!$competition) {
      return FALSE;
    }

    $required_recitations = [];
    foreach ($competition->field_competition_streams->referencedEntities() as $stream) {
      // Get the number of recitations required per stream type.
      $stream_types = array_column($stream->field_competition_stream_type->getValue(), 'value');
      foreach ($stream_types as $stream_type) {
        $required_recitations[$stream_type] = $stream->field_min_recitations->value;
      }
      break; // @todo for now we only consider the first stream.
    }

    //ksm($required_recitations);
    // ksm($competition_entry->field_stream);
    // ksm($competition_entry->field_recitations->count());
    foreach ($competition_entry->field_recitations->referencedEntities() as $recitation) {
      //ksm($recitation);
    }

    return;
  }

}
