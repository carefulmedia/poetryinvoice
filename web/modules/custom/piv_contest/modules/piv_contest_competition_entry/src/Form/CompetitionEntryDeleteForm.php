<?php

namespace Drupal\piv_contest_competition_entry\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Custom delete form extending the core one.
 */
class CompetitionEntryDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entity = $this->getEntity();
    $uid = $entity->uid->target_id;
    $competition_id = $entity->field_competition->target_id;
    parent::submitForm($form, $form_state);
    if ($uid && $competition_id) {
      $form_state->setRedirect('piv_contest.competition', [
        'user' => $uid,
        'competition' => $competition_id,
      ]);
    }
  }

}
