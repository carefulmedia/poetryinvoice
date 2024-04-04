<?php

namespace Drupal\piv_contest_recitation\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the recitation entity edit forms.
 */
class RecitationForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): void {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toString();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => $link];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New recitation %label has been created.', $message_arguments));
      $this->logger('piv_contest_recitation')->notice('Created new recitation %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The recitation %label has been updated.', $message_arguments));
      $this->logger('piv_contest_recitation')->notice('Updated new recitation %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.recitation.canonical', ['recitation' => $entity->id()]);
  }

}
