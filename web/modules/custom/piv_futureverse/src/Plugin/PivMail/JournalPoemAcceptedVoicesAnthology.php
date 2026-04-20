<?php

namespace Drupal\piv_futureverse\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "journal_poem_accepted_voices_anthology",
 *   type = "default",
 *   label = @Translation("Journal Poem: Accepted Voices Anthology"),
 *   description = @Translation("Send an email to users accepted for Voices Anthology."),
 *   sources = {"user", "journal_poem"}
 * )
 */
class JournalPoemAcceptedVoicesAnthology extends PivMailPluginBase {

  /**
   * {@inheritdoc}
   *
   * The other plugins with reminders in this module extends this
   * plugin.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, ?string $langcode = NULL) {
    $form = parent::buildConfigurationForm($form, $form_state, $langcode);
    $configurations = $this->getConfiguration()['en'] ?? [];
    $form['reminder_interval_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Reminder interval in days.'),
      '#weight' => -1,
      '#description' => $this->t('Same value for all languages. Set to 0 to disable reminders, these are sent only after the notifications are sent first. A "REMINDER:" will be added to the subject.'),
      '#default_value' => $configurations['reminder_interval_days'] ?? 0,
      '#step' => 1,
      '#disabled' => $langcode != 'en',
    ];
    return $form;
  }

}
