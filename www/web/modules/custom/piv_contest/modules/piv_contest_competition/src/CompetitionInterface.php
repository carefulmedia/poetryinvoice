<?php

namespace Drupal\piv_contest_competition;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a competition entity type.
 */
interface CompetitionInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the competition title.
   *
   * @return string
   *   Title of the competition.
   */
  public function getTitle();

  /**
   * Sets the competition title.
   *
   * @param string $title
   *   The competition title.
   *
   * @return \Drupal\piv_contest_competition\CompetitionInterface
   *   The called competition entity.
   */
  public function setTitle($title);

  /**
   * Gets the competition creation timestamp.
   *
   * @return int
   *   Creation timestamp of the competition.
   */
  public function getCreatedTime();

  /**
   * Sets the competition creation timestamp.
   *
   * @param int $timestamp
   *   The competition creation timestamp.
   *
   * @return \Drupal\piv_contest_competition\CompetitionInterface
   *   The called competition entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the competition status.
   *
   * @return bool
   *   TRUE if the competition is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the competition status.
   *
   * @param bool $status
   *   TRUE to enable this competition, FALSE to disable.
   *
   * @return \Drupal\piv_contest_competition\CompetitionInterface
   *   The called competition entity.
   */
  public function setStatus($status);

}
