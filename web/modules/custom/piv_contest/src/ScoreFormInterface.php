<?php

namespace Drupal\piv_contest;

use Drupal\piv_contest_score_template\ScoreTemplateInterface;
use Drupal\piv_contest_score\ScoreInterface;

/**
 * Interface for score_form plugins.
 */
interface ScoreFormInterface {

  /**
   * Returns the form.
   */
  public function form(ScoreTemplateInterface $entity);

  /**
   * Save a score given values from submited form and a new score entity.
   */
  public function save(ScoreInterface $score, array $values);

}
