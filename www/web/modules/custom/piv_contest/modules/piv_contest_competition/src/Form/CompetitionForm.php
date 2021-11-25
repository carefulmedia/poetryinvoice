<?php

namespace Drupal\piv_contest_competition\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the competition entity edit forms.
 */
class CompetitionForm extends ContentEntityForm {

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
      $this->messenger()->addStatus($this->t('New competition %label has been created.', $message_arguments));
      $this->logger('piv_contest_competition')->notice('Created new competition %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The competition %label has been updated.', $message_arguments));
      $this->logger('piv_contest_competition')->notice('Updated new competition %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.competition.canonical', ['competition' => $entity->id()]);
  }

}
