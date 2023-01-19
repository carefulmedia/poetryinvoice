<?php

namespace Drupal\piv_contest_competition_entry\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a School constraint.
 *
 * @Constraint(
 *   id = "PivContestCompetitionEntrySchool",
 *   label = @Translation("School", context = "Validation"),
 * )
 */
class SchoolConstraint extends Constraint {

  /**
   * The error message.
   *
   * @var string
   */
  public $errorMessage = 'There already exists a competition entry (@ids) for that school for that stream.';

}
