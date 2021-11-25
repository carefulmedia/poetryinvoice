<?php

namespace Drupal\piv_contest_competition_entry;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a competition entry entity type.
 */
interface CompetitionEntryInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the competition entry title.
   *
   * @return string
   *   Title of the competition entry.
   */
  public function getTitle();

  /**
   * Sets the competition entry title.
   *
   * @param string $title
   *   The competition entry title.
   *
   * @return \Drupal\piv_contest_competition_entry\CompetitionEntryInterface
   *   The called competition entry entity.
   */
  public function setTitle($title);

  /**
   * Gets the competition entry creation timestamp.
   *
   * @return int
   *   Creation timestamp of the competition entry.
   */
  public function getCreatedTime();

  /**
   * Sets the competition entry creation timestamp.
   *
   * @param int $timestamp
   *   The competition entry creation timestamp.
   *
   * @return \Drupal\piv_contest_competition_entry\CompetitionEntryInterface
   *   The called competition entry entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the competition entry status.
   *
   * @return bool
   *   TRUE if the competition entry is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the competition entry status.
   *
   * @param bool $status
   *   TRUE to enable this competition entry, FALSE to disable.
   *
   * @return \Drupal\piv_contest_competition_entry\CompetitionEntryInterface
   *   The called competition entry entity.
   */
  public function setStatus($status);

}
