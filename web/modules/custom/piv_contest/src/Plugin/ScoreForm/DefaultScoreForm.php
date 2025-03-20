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
  public function form(ScoreTemplateInterface $score_template, $language = NULL, $default_values = []) {
    if ($language && $score_template->hasTranslation($language)) {
      $score_template = $score_template->getTranslation($language);
    }
    $form = [
      '#theme' => 'recitation_score_form_default',
    ];

    $form['criteria'] = [];
    $labels = array_column($score_template->field_score_option_labels->getValue(), 'value');
    $form['labels'] = array_map(function ($label) {
      return ['#markup' => $label];
    }, $labels);
    foreach ($score_template->field_criteria->referencedEntities() as $delta => $criteria) {
      if ($language && $criteria->hasTranslation($language)) {
        $criteria = $criteria->getTranslation($language);
      }
      $values = array_column($criteria->field_score_options->getValue(), 'value');
      $options = [];
      $default_value = NULL;
      foreach ($values as $option => $value) {
        $options["{$option}_{$value}"] = $value;
        if (isset($default_values[$delta]) && $default_values[$delta] == $value) {
          $default_value = "{$option}_{$value}";
        }
      }
      $form['criteria'][$delta] = [
        '#type' => 'radios',
        '#title' => $criteria->field_criterion->value,
        '#options' => $options,
        '#default_value' => $default_value,
        '#required' => TRUE,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(ScoreInterface $score, array $values): void {
    $scores = $values['criteria'] ?? [];
    $scores = array_map(fn ($score) => explode('_', $score)[1] ?? 0, $scores);
    $score->field_scores = $scores;
  }

}
