<?php

namespace Drupal\piv_contest_score_template\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a Score Template constraint.
 *
 * @Constraint(
 *   id = "score_template_constraint",
 *   label = @Translation("Score Template", context = "Validation"),
 * )
 */
class ScoreTemplateConstraint extends Constraint {

  public $countErrorMessage = 'The number of <b>Score Options</b> in a Criterion must match the number of <b>Criterion Labels</b> (which is %quantity).';

}
