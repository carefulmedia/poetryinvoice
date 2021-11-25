<?php

namespace Drupal\piv_contest\Plugin\ScoreForm;

use Drupal\piv_contest\ScoreFormPluginBase;
use Drupal\piv_contest_score_template\ScoreTemplateInterface;
use Drupal\piv_contest_score\ScoreInterface;

/**
 * Plugin implementation of the score_form.
 *
 * @ScoreForm(
 *   id = "default",
 *   score_type = "default",
 * )
 */
class DefaultScoreForm extends ScoreFormPluginBase {

  /**
   * {@inheritdoc}
   */
  public function form(ScoreTemplateInterface $score_template) {
    $form = [];
    $form['criteria'] = [
      '#type' => 'container',
    ];
    foreach ($score_template->field_criteria->referencedEntities() as $delta => $criteria) {
      $options = array_column($criteria->field_score_options->getValue(), 'value');
      $options = array_combine($options, $options);
      $form['criteria'][$delta] = [
        '#type' => 'radios',
        '#title' => $criteria->field_criterion->value,
        '#options' => $options,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(ScoreInterface $score, array $values) {
    $scores = $values['criteria'] ?? [];
    $score->field_scores = $scores;
  }

}
