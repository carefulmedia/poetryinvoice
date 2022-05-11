<?php

namespace Drupal\piv_contest_competition_entry\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Constraint constraint.
 */
class CompetitionEntryConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    // Make sure poems are unique per competition entry.
    $poem_ids = [];
    foreach ($entity->field_recitations as $field) {
      $poem_id = $field->entity->field_poem->target_id;
      if (!$poem_id) {
        continue;
      }
      if (in_array($poem_id, $poem_ids)) {
        $this->context->buildViolation($constraint->errorMessage)
          ->atPath("field_recitations")
          ->addViolation();
      }
      $poem_ids[] = $poem_id;
    }
  }

}
