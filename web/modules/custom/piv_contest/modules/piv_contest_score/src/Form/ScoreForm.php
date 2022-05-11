<?php

namespace Drupal\piv_contest_score\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the score entity edit forms.
 */
class ScoreForm extends ContentEntityForm {

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
      $this->messenger()->addStatus($this->t('New score %label has been created.', $message_arguments));
      $this->logger('piv_contest_score')->notice('Created new score %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The score %label has been updated.', $message_arguments));
      $this->logger('piv_contest_score')->notice('Updated new score %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.score.canonical', ['score' => $entity->id()]);
  }

}
