<?php

namespace Drupal\piv_contest_judging_session;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a judging session entity type.
 */
interface JudgingSessionInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the judging session title.
   *
   * @return string
   *   Title of the judging session.
   */
  public function getTitle();

  /**
   * Sets the judging session title.
   *
   * @param string $title
   *   The judging session title.
   *
   * @return \Drupal\piv_contest_judging_session\JudgingSessionInterface
   *   The called judging session entity.
   */
  public function setTitle($title);

  /**
   * Gets the judging session creation timestamp.
   *
   * @return int
   *   Creation timestamp of the judging session.
   */
  public function getCreatedTime();

  /**
   * Sets the judging session creation timestamp.
   *
   * @param int $timestamp
   *   The judging session creation timestamp.
   *
   * @return \Drupal\piv_contest_judging_session\JudgingSessionInterface
   *   The called judging session entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the judging session status.
   *
   * @return bool
   *   TRUE if the judging session is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the judging session status.
   *
   * @param bool $status
   *   TRUE to enable this judging session, FALSE to disable.
   *
   * @return \Drupal\piv_contest_judging_session\JudgingSessionInterface
   *   The called judging session entity.
   */
  public function setStatus($status);

}
