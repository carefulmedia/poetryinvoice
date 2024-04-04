<?php

namespace Drupal\piv_contest\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the StreamConstraint constraint.
 */
class StreamConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint): void {
    if ($entity->bundle() != 'competition_stream') {
      return;
    }

    $min = $entity->field_entries_required_school->value;
    $max = $entity->field_max_entries_school->value;
    if ($min > $max) {
      $this->context->buildViolation($constraint->minMaxError)
        ->atPath('field_max_entries_school')
        ->addViolation();
    }
  }

}
