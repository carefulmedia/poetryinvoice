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

  /**
   * Criteria and labels count.
   *
   * @var string
   */
  public $countErrorMessage = "The number of <b>Score Options</b> in a Criterion must match the number of <b>Criterion Labels</b> (which is %quantity).";

  /**
   * Criteria editing on updating score_template.
   *
   * @var string
   */
  public $criteriaCountErrorMessage = "The quantity of <b>Criteria</b> can't be changed since there are score entities using this score_template.";

  /**
   * Label editing on updating score_template.
   *
   * @var string
   */
  public $labelCountErrorMessage = "The quantity of <b>Score Option Labels</b> can't be changed since there are score entities using this score_template.";

}
