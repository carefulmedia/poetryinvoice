<?php

namespace Drupal\piv_contest_competition_entry\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a Constraint constraint.
 *
 * @Constraint(
 *   id = "competition_entry_constraint",
 *   label = @Translation("Constraint", context = "Validation"),
 * )
 *
 * @DCG
 * To apply this constraint on a particular field implement
 * hook_entity_type_build().
 */
class CompetitionEntryConstraint extends Constraint {

  public $errorMessage = "Each recitation should be for a different poem.";

}
