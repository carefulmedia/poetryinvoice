<?php

namespace Drupal\piv_contest_score_template;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a score template entity type.
 */
interface ScoreTemplateInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the score template title.
   *
   * @return string
   *   Title of the score template.
   */
  public function getTitle();

  /**
   * Sets the score template title.
   *
   * @param string $title
   *   The score template title.
   *
   * @return \Drupal\piv_contest_score_template\ScoreTemplateInterface
   *   The called score template entity.
   */
  public function setTitle($title);

  /**
   * Gets the score template creation timestamp.
   *
   * @return int
   *   Creation timestamp of the score template.
   */
  public function getCreatedTime();

  /**
   * Sets the score template creation timestamp.
   *
   * @param int $timestamp
   *   The score template creation timestamp.
   *
   * @return \Drupal\piv_contest_score_template\ScoreTemplateInterface
   *   The called score template entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the score template status.
   *
   * @return bool
   *   TRUE if the score template is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the score template status.
   *
   * @param bool $status
   *   TRUE to enable this score template, FALSE to disable.
   *
   * @return \Drupal\piv_contest_score_template\ScoreTemplateInterface
   *   The called score template entity.
   */
  public function setStatus($status);

}
