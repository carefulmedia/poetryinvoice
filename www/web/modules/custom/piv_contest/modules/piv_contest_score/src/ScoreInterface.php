<?php

namespace Drupal\piv_contest_score;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a score entity type.
 */
interface ScoreInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the score title.
   *
   * @return string
   *   Title of the score.
   */
  public function getTitle();

  /**
   * Sets the score title.
   *
   * @param string $title
   *   The score title.
   *
   * @return \Drupal\piv_contest_score\ScoreInterface
   *   The called score entity.
   */
  public function setTitle($title);

  /**
   * Gets the score creation timestamp.
   *
   * @return int
   *   Creation timestamp of the score.
   */
  public function getCreatedTime();

  /**
   * Sets the score creation timestamp.
   *
   * @param int $timestamp
   *   The score creation timestamp.
   *
   * @return \Drupal\piv_contest_score\ScoreInterface
   *   The called score entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the score status.
   *
   * @return bool
   *   TRUE if the score is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the score status.
   *
   * @param bool $status
   *   TRUE to enable this score, FALSE to disable.
   *
   * @return \Drupal\piv_contest_score\ScoreInterface
   *   The called score entity.
   */
  public function setStatus($status);

}
