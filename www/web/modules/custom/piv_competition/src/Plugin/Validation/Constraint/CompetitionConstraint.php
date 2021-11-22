<?php

namespace Drupal\piv_competition\Plugin\Validation\Constraint;

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

  public $locationErrorMessage = 'Field Location is required.';

  public $invitedSchoolsErrorMessage = 'Field Invited Schools is required.';

}
