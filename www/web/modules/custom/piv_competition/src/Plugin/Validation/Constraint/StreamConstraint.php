<?php

namespace Drupal\piv_competition\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a StreamConstraint constraint.
 *
 * @Constraint(
 *   id = "stream_constraint",
 *   label = @Translation("Stream Constraint", context = "Validation"),
 * )
 */
class StreamConstraint extends Constraint {

  public $minMaxError = 'The <b>Maximum number of entries</b> must be greater than or equal to <b>Number of entries required</b>.';

}
