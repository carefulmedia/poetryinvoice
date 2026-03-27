<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates team regionals entry constraints.
 *
 * @Constraint(
 *   id = "team_regionals_entry",
 *   label = @Translation("Team Regionals Entry", context = "Validation"),
 *   type = "entity"
 * )
 */
class TeamRegionalsEntryConstraint extends Constraint {

  /**
   * The message for when competition closing date has passed.
   */
  public string $closingDatePassedMessage = "This competition's closing date has passed. You cannot add new entries to it.";

  /**
   * The message for when competition has already started.
   */
  public string $alreadyStartedMessage = "This competition has already begun. You cannot add new entries to it.";

  /**
   * The message for when maximum entries per school is reached.
   */
  public string $maxEntriesMessage = "Your school has reached the maximum number of entries for this competition.";

  /**
   * The message for when school is not invited.
   */
  public string $notInvitedMessage = "You can only create entries for competitions your school is invited to.";

  /**
   * The message for when user has no school assigned.
   */
  public string $noSchoolMessage = "You must be assigned to a school to create entries for this competition.";
  
  /**
   * Messages for max students per entry.
   * 
   * Singular / Plural are separated by |.
   * @see https://symfony.com/doc/2.x/components/translation/usage.html#pluralization
   */
  public string $maxStudentsMessage = "The associated competition allows only one student per entry. If you have submitted more students than permitted, contact an administrator to remove the extra entries.|This associated competition strictly requires @count students per entry. If you have more entries than the allowed amount, contact an administrator to remove a student.";

}
