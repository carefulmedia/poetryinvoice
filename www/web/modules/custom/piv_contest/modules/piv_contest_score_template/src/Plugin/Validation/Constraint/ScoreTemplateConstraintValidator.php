<?php

namespace Drupal\piv_contest_score_template\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Score Template constraint.
 */
class ScoreTemplateConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    $bundle = $entity->bundle();

    // For now only the default bundle is validated. Make sure all criterion
    // have the same number of score options as the criterion labels.
    if ($bundle == 'default') {
      $labels_count = $entity->field_score_option_labels->count();
      foreach ($entity->field_criteria->referencedEntities() as $delta => $criterion) {
        $options_count = $criterion->field_score_options->count();
        if ($options_count != $labels_count) {
          $this->context->buildViolation($constraint->countErrorMessage)
            ->setParameter('%quantity', $labels_count)
            ->atPath("field_criteria.$delta")
            ->addViolation();
        }
      }      
    }
  }

}
