<?php

namespace Drupal\piv_contest_competition_entry\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\piv_contest_competition_entry\Entity\CompetitionEntry;

class CompetitionLockService {

  /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
  private $storage;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->storage = $entityTypeManager->getStorage('judging_session');
  }

  public function isLocked(CompetitionEntry $competitionEntry): bool {
    // if competition is not active it's locked.
    $competition = $competitionEntry->field_competition->entity;
    if (!$competition->field_active->value) {
      return true;
    }

    // If field_submission_deadline date is in the past it's locked.
    /** @var \Drupal\Core\Datetime\DrupalDateTime $deadline */
    $deadline = $competition->field_submission_deadline->date;
    $now = new \DateTime();
    if ($deadline->getTimestamp() < $now->getTimestamp()) {
      return true;
    }

    // If there's any judging session enabled it's locked.
    $nids = $this->storage->getQuery()
      ->condition('field_competition_entries', $competitionEntry->id())
      ->condition('status', true)
      ->execute();

    if (count($nids) > 0) {
      return true;
    }

    return false;
  }
}
