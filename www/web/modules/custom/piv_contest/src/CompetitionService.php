<?php

namespace Drupal\piv_contest;

use Drupal\Core\Entity\EntityTypeManagerInterface;
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

}
