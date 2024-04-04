<?php

namespace Drupal\piv_contest_score_template\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the score template entity edit forms.
 */
class ScoreTemplateForm extends ContentEntityForm {

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
      $this->messenger()->addStatus($this->t('New score template %label has been created.', $message_arguments));
      $this->logger('piv_contest_score_template')->notice('Created new score template %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The score template %label has been updated.', $message_arguments));
      $this->logger('piv_contest_score_template')->notice('Updated new score template %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.score_template.canonical', ['score_template' => $entity->id()]);
  }

}
