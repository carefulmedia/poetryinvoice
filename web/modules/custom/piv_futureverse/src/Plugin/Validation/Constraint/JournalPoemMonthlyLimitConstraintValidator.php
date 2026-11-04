<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Plugin\Validation\Constraint;

use Drupal\node\NodeInterface;
use Drupal\piv_futureverse\JournalHelper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the journal poem monthly limit constraint.
 */
class JournalPoemMonthlyLimitConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $entity, Constraint $constraint): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'journal_poem') {
      return;
    }
    if (!$entity->isNew() || !piv_futureverse_journal_submission_is_public()) {
      return;
    }

    $email = (string) ($entity->field_email1->value ?? '');
    if (!JournalHelper::isAtMonthlyPoemLimit($email, $entity->language()->getId())) {
      return;
    }

    $this->context->buildViolation(piv_futureverse_submission_message('journal_poem_limit'))
      ->atPath('field_email1')
      ->addViolation();
  }

}
