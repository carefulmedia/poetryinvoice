<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Limits public journal poem creates to five per email per language per journal month.
 *
 * @Constraint(
 *   id = "journal_poem_monthly_limit",
 *   label = @Translation("Journal poem monthly limit", context = "Validation"),
 *   type = "entity"
 * )
 */
class JournalPoemMonthlyLimitConstraint extends Constraint {

}
