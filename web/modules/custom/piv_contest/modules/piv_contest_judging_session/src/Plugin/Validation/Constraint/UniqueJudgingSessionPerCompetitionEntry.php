<?php

namespace Drupal\piv_contest_judging_session\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a Score Template constraint.
 *
 * @Constraint(
 *   id = "unique_judging_session_per_competition_entry",
 *   label = @Translation("Unique Judging Session Per Competition Entry", context = "Validation"),
 * )
 */
class UniqueJudgingSessionPerCompetitionEntry extends Constraint {

  /**
   * The error message.
   *
   * @var string
   */
  public $message = 'This competition entry is already being used by <a href="@judging_session_link">@judging_session_text</a>';

}
