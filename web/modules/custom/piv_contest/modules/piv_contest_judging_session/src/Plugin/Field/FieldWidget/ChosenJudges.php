<?php

namespace Drupal\piv_contest_judging_session\Plugin\Field\FieldWidget;

use Drupal\chosen_field\Plugin\Field\FieldWidget\ChosenFieldWidget;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Plugin implementation of the 'chosen_select' widget.
 *
 * @FieldWidget(
 *   id = "chosen_judges_select",
 *   label = @Translation("Chosen Judges"),
 *   field_types = {
 *     "list_integer",
 *     "list_float",
 *     "list_string",
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class ChosenJudges extends ChosenFieldWidget {

  /**
   * All options for the field.
   */
  protected function getOptions(FieldableEntityInterface $entity) {
    $competition_id = $entity->field_competition->target_id;
    if (!$competition_id) {
      return [];
    }

    /** @var \Drupal\piv_contest_judging_session\Entity\JudgingSession $competition */
    $competition = \Drupal::entityTypeManager()->getStorage('competition')->load($competition_id);

    $options = [];
    // Add an empty option if the widget needs one.
    if ($empty_label = $this->getEmptyLabel()) {
      $options = ['_none' => $empty_label] + $options;
    }

    foreach ($competition->field_judges as $judge_field) {
      $entity = $judge_field->entity;
      $options[$entity->id()] = $entity->label();
    }

    $this->options = $options;

    return $options;
  }

}
