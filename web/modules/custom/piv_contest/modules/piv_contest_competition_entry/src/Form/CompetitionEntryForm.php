<?php

namespace Drupal\piv_contest_competition_entry\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the competition entry entity edit forms.
 */
class CompetitionEntryForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New competition entry %label has been created.', $message_arguments));
      $this->logger('piv_contest_competition_entry')->notice('Created new competition entry %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The competition entry %label has been updated.', $message_arguments));
      $this->logger('piv_contest_competition_entry')->notice('Updated new competition entry %label.', $logger_arguments);
    }
    // Do not override if there is a redirect set in the form already.
    if (!$form_state->getRedirect()) {
      $form_state->setRedirect('entity.competition_entry.canonical', ['competition_entry' => $entity->id()]);
    }
  }

}
