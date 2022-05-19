<?php

namespace Drupal\piv_contest_competition_entry\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\piv_contest_competition_entry\Entity\CompetitionEntry;

/**
 * The competition lock service.
 */
class CompetitionLockService {

  /**
   * The Judging Session storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Construct a CompetitionLockService.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->storage = $entity_type_manager->getStorage('judging_session');
  }

  /**
   * Check if competition entry is locked.
   */
  public function isLocked(CompetitionEntry $competition_entry): bool {
    // If competition is not active then it's locked.
    $competition = $competition_entry->field_competition->entity;
    if (!$competition) {
      return false;
    }

    if (!$competition->field_active->value) {
      return TRUE;
    }

    // If field_submission_deadline date is in the past it's locked.
    /** @var \Drupal\Core\Datetime\DrupalDateTime $deadline */
    $deadline = $competition->field_submission_deadline->date;
    $now = new \DateTime();
    if ($deadline->getTimestamp() < $now->getTimestamp()) {
      return TRUE;
    }

    // If there's any judging session enabled it's locked.
    $nids = $this->storage->getQuery()
      ->condition('field_competition_entries', $competition_entry->id())
      ->condition('status', TRUE)
      ->execute();

    if (count($nids) > 0) {
      return TRUE;
    }

    return FALSE;
  }

}
