<?php

namespace Drupal\piv_contest\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a CompetitionConstraint constraint.
 *
 * @Constraint(
 *   id = "competition_constraint",
 *   label = @Translation("Competition Constraint", context = "Validation"),
 * )
 */
class CompetitionConstraint extends Constraint {

  /**
   * Field location error message.
   *
   * @var string
   */
  public $locationErrorMessage = 'Field Location is required.';

  /**
   * Field invited school error message.
   *
   * @var string
   */
  public $invitedSchoolsErrorMessage = 'Field Invited Schools is required.';

  /**
   * Prevent changing the score template.
   *
   * @var string
   */
  public $cantChangeScoreTemplate = 'There are scores for recitations on this competition using this score template already.';

}
