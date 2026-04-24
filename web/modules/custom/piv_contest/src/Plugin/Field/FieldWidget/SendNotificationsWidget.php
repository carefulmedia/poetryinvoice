<?php

namespace Drupal\piv_contest\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Widget for viewing/editing raw notification JSON data.
 *
 * @FieldWidget(
 *   id = "send_notifications_widget",
 *   label = @Translation("Send Notifications"),
 *   field_types = {
 *     "string_long"
 *   }
 * )
 */
class SendNotificationsWidget extends StringTextareaWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['value']['#title'] = $this->t('Notification log (JSON)');
    $element['value']['#rows'] = 5;
    return $element;
  }

}
