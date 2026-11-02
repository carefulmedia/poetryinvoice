<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the futureverse application entity edit forms.
 */
final class FutureverseApplicationForm extends ContentEntityForm {

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
        $this->addSaveStatus($this->t('New futureverse application %label has been created.', $message_args));
        $this->logger('piv_futureverse')->notice('New futureverse application %label has been created.', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->addSaveStatus($this->t('The futureverse application %label has been updated.', $message_args));
        $this->logger('piv_futureverse')->notice('The futureverse application %label has been updated.', $logger_args);
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    // If using invitation mode, redirect back to edit form.
    $form_display = $form['#process'][1][0] ?? NULL;
    if (is_object($form_display) && method_exists($form_display, 'getMode')) {
      $mode = $form_display->getMode();
      if ($mode == 'invitation') {
        $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
        return $result;
      }
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
    if ($this->getOperation() === 'student') {
      piv_futureverse_queue_submission_modal(piv_futureverse_submission_message('futureverse_application'));
      return;
    }
    $this->messenger()->addStatus($admin_message);
  }

}
