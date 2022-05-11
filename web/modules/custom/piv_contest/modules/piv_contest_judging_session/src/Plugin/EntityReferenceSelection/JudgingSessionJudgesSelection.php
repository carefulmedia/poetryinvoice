<?php

namespace Drupal\piv_contest_judging_session\Plugin\EntityReferenceSelection;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\user\Plugin\EntityReferenceSelection\UserSelection;

/**
 * Entity reference selection to select Judges.
 *
 * @EntityReferenceSelection(
 *   id = "judges_selection:user",
 *   label = @Translation("Judges Selection entity reference"),
 *   entity_types = {"user"},
 *   group = "piv_contest_judging_session",
 *   weight = 1
 * )
 */
class JudgingSessionJudgesSelection extends UserSelection {

  /**
   * {@inheritdoc}
   */
  public function entityQueryAlter(SelectInterface $query) {
    parent::entityQueryAlter($query);

    $competition_id = $this->getConfiguration()['competition_id'];
    /** @var \Drupal\piv_contest_judging_session\Entity\JudgingSession $competition */
    $competition = \Drupal::entityTypeManager()->getStorage('competition')->load($competition_id);

    $ids = [];
    foreach ($competition->field_judges as $judge_field) {
      if ($judge_field->target_id) {
        $ids[] = $judge_field->target_id;
      }
    }
    $query->condition('base_table.uid', $ids, 'IN');
  }

}
