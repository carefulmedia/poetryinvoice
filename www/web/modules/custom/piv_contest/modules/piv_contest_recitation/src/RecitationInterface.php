<?php

namespace Drupal\piv_contest_recitation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a recitation entity type.
 */
interface RecitationInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the recitation title.
   *
   * @return string
   *   Title of the recitation.
   */
  public function getTitle();

  /**
   * Sets the recitation title.
   *
   * @param string $title
   *   The recitation title.
   *
   * @return \Drupal\piv_contest_recitation\RecitationInterface
   *   The called recitation entity.
   */
  public function setTitle($title);

  /**
   * Gets the recitation creation timestamp.
   *
   * @return int
   *   Creation timestamp of the recitation.
   */
  public function getCreatedTime();

  /**
   * Sets the recitation creation timestamp.
   *
   * @param int $timestamp
   *   The recitation creation timestamp.
   *
   * @return \Drupal\piv_contest_recitation\RecitationInterface
   *   The called recitation entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the recitation status.
   *
   * @return bool
   *   TRUE if the recitation is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the recitation status.
   *
   * @param bool $status
   *   TRUE to enable this recitation, FALSE to disable.
   *
   * @return \Drupal\piv_contest_recitation\RecitationInterface
   *   The called recitation entity.
   */
  public function setStatus($status);

}
