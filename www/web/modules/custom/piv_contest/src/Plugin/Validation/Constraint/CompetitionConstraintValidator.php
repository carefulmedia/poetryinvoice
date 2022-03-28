<?php

namespace Drupal\piv_contest\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the CompetitionConstraint constraint.
 */
class CompetitionConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    if ($entity->bundle() != 'default') {
      return;
    }

    $is_online = (BOOL) $entity->field_online_competition->value;
    if (!$is_online && $entity->field_location->isEmpty()) {
      $this->context->buildViolation($constraint->locationErrorMessage)
        ->atPath('field_location')
        ->addViolation();
    }
    $is_invitation_only = (BOOL) $entity->field_by_invitation_only->value;
    if ($is_invitation_only && $entity->field_invited_schools->isEmpty()) {
      $this->context->buildViolation($constraint->invitedSchoolsErrorMessage)
        ->atPath('field_invited_schools')
        ->addViolation();
    }
  }

}
