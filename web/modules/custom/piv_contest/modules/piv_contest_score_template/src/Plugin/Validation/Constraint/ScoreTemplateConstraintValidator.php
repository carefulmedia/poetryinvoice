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
    $entity_type_manager = \Drupal::entityTypeManager();
    $original = $entity->isNew()
      ? NULL
      : $entity_type_manager->getStorage('score_template')->load($entity->id());

    $bundle = $entity->bundle();
    // For now only the default bundle is validated.
    if ($bundle == 'default') {
      // Make sure all criterion have the same number of score options as the
      // criterion labels.
      $labels_count = $entity->field_score_option_labels->filterEmptyItems()->count();
      // Can't use referencedEntities() directly since it will load the old
      // entity.
      foreach ($entity->field_criteria->filterEmptyItems()->getValue() as $delta => $data) {
        $criterion = $data['entity'];
        $options_count = $criterion->field_score_options->filterEmptyItems()->count();
        if ($options_count != $labels_count) {
          $this->context->buildViolation($constraint->countErrorMessage)
            ->setParameter('%quantity', $labels_count)
            ->atPath("field_criteria.$delta")
            ->addViolation();
        }
      }
      // When editing, make sure we didn't change the quantity of criterias and
      // score options.
      if ($original) {
        $results = $entity_type_manager->getStorage('score')->getQuery()
          ->condition('score_template', $entity->id())
          ->execute();
        if ($results) {
          // Compare criteria count.
          if ($entity->field_criteria->filterEmptyItems()->count() != $original->field_criteria->filterEmptyItems()->count()) {
            $this->context->buildViolation($constraint->criteriaCountErrorMessage)
              ->atPath("field_criteria")
              ->addViolation();
          }
          if ($entity->field_score_option_labels->filterEmptyItems()->count() != $original->field_score_option_labels->filterEmptyItems()->count()) {
            $this->context->buildViolation($constraint->labelCountErrorMessage)
              ->atPath("field_score_option_labels")
              ->addViolation();
          }
        }
      }
    }
  }

}
