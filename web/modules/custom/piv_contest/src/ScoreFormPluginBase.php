<?php

namespace Drupal\piv_contest;

use Drupal\Component\Plugin\PluginBase;
use Drupal\piv_contest_score_template\ScoreTemplateInterface;
use Drupal\piv_contest_score\ScoreInterface;

/**
 * Base class for score_form plugins.
 */
abstract class ScoreFormPluginBase extends PluginBase implements ScoreFormInterface {

  /**
   * {@inheritdoc}
   */
  public function form(ScoreTemplateInterface $entity) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function save(ScoreInterface $score, array $values) {
    return TRUE;
  }

}
