<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the poet bio entity edit forms.
 */
final class PoetBioForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    $message_args = ['%label' => $this->entity->toLink()->toString()];
    $logger_args = [
      '%label' => $this->entity->label(),
      'link' => $this->entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->addSaveStatus($this->t('New poet bio %label has been created.', $message_args));
        $this->logger('piv_futureverse')->notice('New poet bio %label has been created.', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->addSaveStatus($this->t('The poet bio %label has been updated.', $message_args));
        $this->logger('piv_futureverse')->notice('The poet bio %label has been updated.', $logger_args);
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    if (!$form_state->getRedirect()) {
      $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    }

    return $result;
  }

  /**
   * Adds the status message for this save.
   *
   * Public submissions use the editable thank-you. Admin saves keep the
   * entity message.
   */
  private function addSaveStatus(\Stringable|string $admin_message): void {
    if ($this->getOperation() === 'enrichment') {
      piv_futureverse_queue_submission_modal(piv_futureverse_submission_message('poet_bio'));
      return;
    }
    $this->messenger()->addStatus($admin_message);
  }

}
