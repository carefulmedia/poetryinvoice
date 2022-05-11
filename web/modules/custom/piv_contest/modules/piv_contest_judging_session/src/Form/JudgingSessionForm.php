<?php

namespace Drupal\piv_contest_judging_session\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the judging session entity edit forms.
 */
class JudgingSessionForm extends ContentEntityForm {

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
      $this->messenger()->addStatus($this->t('New judging session %label has been created.', $message_arguments));
      $this->logger('piv_contest_judging_session')->notice('Created new judging session %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The judging session %label has been updated.', $message_arguments));
      $this->logger('piv_contest_judging_session')->notice('Updated new judging session %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.judging_session.canonical', ['judging_session' => $entity->id()]);
  }

}
