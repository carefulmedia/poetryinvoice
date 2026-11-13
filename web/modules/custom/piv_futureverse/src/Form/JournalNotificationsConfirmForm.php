<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\piv_futureverse\JournalMonthInterface;
use Drupal\piv_futureverse\JournalHelper;

/**
 * Confirm form to send notifications.
 */
final class JournalNotificationsConfirmForm extends ConfirmFormBase {

  /**
   * The operation.
   *
   * @param string
   */
  protected string $op;

  /**
   * Related entity.
   *
   * @param \Drupal\piv_futureverse\JournalMonthInterface
   */
  protected JournalMonthInterface $journalMonth;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $op = NULL, ?JournalMonthInterface $journal_month = NULL) {
    $this->op = $op;
    $this->journalMonth = $journal_month;
    $form = parent::buildForm($form, $form_state);
    if (str_starts_with((string) $this->op, 'futureverse_invitations_month:')) {
      $form['recipients'] = $this->buildRecipientList($this->futureverseInvitationMonthPoems());
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'piv_futureverse_journal_notifications_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    $op = $this->op;
    if (str_starts_with($op, 'monthly_prize_winner:')) {
      return $this->t('Are you sure you want to send the monthly prize <em>winner</em> notifications for <em>@title</em>?', [
        '@title' => $this->journalMonth->label(),
      ]);
    }
    elseif (str_starts_with($op, 'monthly_prize_losers:')) {
      return $this->t('Are you sure you want to send the monthly prize <em>losers</em> notifications for <em>@title</em>?', [
        '@title' => $this->journalMonth->label(),
      ]);
    }
    elseif (str_starts_with($op, 'accepted_to_voices:')) {
      return $this->t('Are you sure you want to send the <em>accepted</em> to the Voices/Voix anthology notifications?');
    }
    elseif (str_starts_with($op, 'not_accepted_to_voices:')) {
      return $this->t('Are you sure you want to send the <em>not accepted</em> to the Voices/Voix anthology notifications?');
    }
    elseif (str_starts_with($op, 'futureverse_invitations_month:')) {
      return $this->t('Are you sure you want to send the <em>futureverse invitations</em> notifications for <em>@title</em>?', [
        '@title' => $this->journalMonth->label(),
      ]);
    }
    elseif (str_starts_with($op, 'futureverse_invitations:')) {
      return $this->t('Are you sure you want to send the <em>futureverse invitations</em> notifications?');
    }
    elseif (str_starts_with($op, 'futureverse_shortlisted:')) {
      return $this->t('Are you sure you want to send the <em>futureverse shortlisted</em> notifications?');
    }

    return $this->t('Invalid operation.');
  }

  /**
   * Poems that this monthly FutureVerse invitation will email.
   *
   * Send includes everyone currently eligible. Update skips poems already
   * recorded as sent for the month.
   *
   * @return \Drupal\node\NodeInterface[]
   *   One poem per recipient email.
   */
  protected function futureverseInvitationMonthPoems(): array {
    $journal_year = JournalHelper::getJournalYearFromJournalMonth($this->journalMonth);
    if (!$journal_year) {
      return [];
    }

    $poems = JournalHelper::getFutureverseInvitationPoemsForMonth($this->journalMonth, $journal_year);
    if (!str_ends_with($this->op, ':update')) {
      return $poems;
    }

    $sent = \Drupal::keyValue('journal_notifications')
      ->get('futureverse_invitations_month_' . $this->journalMonth->id(), ['poems' => []]);
    $already_sent = array_map('strval', $sent['poems'] ?? []);
    return array_values(array_filter(
      $poems,
      static fn (NodeInterface $poem): bool => !in_array((string) $poem->id(), $already_sent, TRUE),
    ));
  }

  /**
   * Name and email table for the poems about to be emailed.
   *
   * @param \Drupal\node\NodeInterface[] $poems
   *   Recipient poems.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  protected function buildRecipientList(array $poems): array {
    $rows = [];
    foreach ($poems as $poem) {
      $rows[] = [
        $this->poemRecipientName($poem),
        (string) ($poem->get('field_email1')->value ?? ''),
      ];
    }
    usort($rows, static fn (array $a, array $b): int => strcasecmp($a[0], $b[0]));

    return [
      '#type' => 'table',
      '#caption' => $this->formatPlural(count($rows), '1 recipient', '@count recipients'),
      '#header' => [$this->t('Name'), $this->t('Email')],
      '#rows' => $rows,
      '#empty' => $this->t('No emails will be sent.'),
      '#weight' => -10,
    ];
  }

  /**
   * Student name stored on a journal poem.
   */
  protected function poemRecipientName(NodeInterface $poem): string {
    $first = $poem->hasField('piv_teacher_first_name') ? (string) $poem->get('piv_teacher_first_name')->value : '';
    $last = $poem->hasField('piv_teacher_last_name') ? (string) $poem->get('piv_teacher_last_name')->value : '';
    $name = trim($first . ' ' . $last);
    return $name !== '' ? $name : (string) $poem->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    $journal_month = $this->journalMonth;
    $journal_year = JournalHelper::getJournalYearFromJournalMonth($journal_month);
    if ($journal_year) {
      return $journal_year->toUrl();
    }
    return $journal_month->toUrl();
  }

  /**
   * Generate operations for the reminders.
   *
   * This is in a separate task so it can be used by cron operations.
   */
  public static function getReminderOperations($op, $journal_year) {
    $operations = [];

    // Helper function to set the operations.
    $set_operations = function ($poem, $journal_year) use ($op, &$operations) {
      $data = [
        'poem_id' => $poem->id(),
        'uid' => $poem->uid->target_id,
        'journal_year_id' => $journal_year,
      ];
      $operations[] = [
        [static::class, 'sendNotification'],
        [$op, $data],
      ];
    };

    if ($op == 'monthly_prize_winner:reminder' || $op == 'accepted_to_voices:reminder') {
      $criteria = $op == 'monthly_prize_winner:reminder' ? 'Monthly prize winner' : 'Accepted';
      $poems_by_email = JournalHelper::getPoemsByAcceptanceGroupedByEmailWithoutPoetBio($journal_year, $criteria);
      foreach ($poems_by_email as $poems_by_language) {
        foreach ($poems_by_language as $poems) {
          $poem = reset($poems);
          $set_operations($poem, $journal_year->id());
        }
      }
    }
    elseif ($op == 'futureverse_invitations:reminder') {
      $poems = JournalHelper::getFutureverseInvitationPoemsGroupedByEmail($journal_year);
      // Create a batch operation to send the emails.
      foreach ($poems as $poem) {
        $poem = reset($poem);
        $set_operations($poem, $journal_year->id());
      }
    }
    elseif ($op == 'futureverse_shortlisted:reminder') {
      // TRUE as the last parameter is to get shortlisted only.
      $poems = JournalHelper::getFutureverseShortlistedPoemsGroupedByEmailWithoutPoetBio($journal_year);
      // Create a batch operation to send the emails.
      foreach ($poems as $poem) {
        $poem = reset($poem);
        $set_operations($poem, $journal_year->id());
      }
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirectUrl($this->getCancelUrl());

    $key_value = \Drupal::keyValue('journal_notifications');
    $journal_month = $this->journalMonth;

    $op = $this->op;
    $operations = [];

    if (str_ends_with($op, ':reminder')) {
      $journal_year = JournalHelper::getJournalYearFromJournalMonth($journal_month);
      if (!$journal_year) {
        return;
      }
      $operations = self::getReminderOperations($op, $journal_year);
    }
    elseif (str_starts_with($op, 'monthly_prize_winner:') || str_starts_with($op, 'monthly_prize_losers:')) {
      // Data holds an array of poem ids.
      $key = NULL;
      if (str_starts_with($op, 'monthly_prize_winner:')) {
        $key = 'monthly_prize_winner_' . $journal_month->id();
      }
      elseif (str_starts_with($op, 'monthly_prize_losers:')) {
        $key = 'monthly_prize_losers_' . $journal_month->id();
      }
      $value = $key_value->get($key, ['poems' => []]);

      // If operation is send, just send to everyone. If operation is
      // update, then only send to whoever didn't receive it yet.
      $poems_to_send = [];
      $poems = [];
      if (str_starts_with($op, 'monthly_prize_winner:')) {
        $poems = JournalHelper::getMonthlyPrizeWinner($journal_month);
      }
      elseif (str_starts_with($op, 'monthly_prize_losers:')) {
        $poems = JournalHelper::getMonthlyPrizeLosersUniqueEmail($journal_month);
      }
      $poems_to_send = array_map(fn($w) => $w->id(), $poems);
      if (str_ends_with($op, ':update')) {
        $poems_already_sent = $value['poems'] ?? [];
        $poems_to_send = array_diff($poems_to_send, $poems_already_sent);
      }

      // Create a batch operation to send the emails.
      foreach ($poems_to_send as $poem_to_send) {
        $data = [
          'poem_id' => $poem_to_send,
          'journal_month_id' => $journal_month->id(),
        ];
        $operations[] = [
          [static::class, 'sendNotification'],
          [$op, $data],
        ];
      }
    }
    elseif (str_starts_with($op, 'accepted_to_voices:') || str_starts_with($op, 'not_accepted_to_voices:')) {
      $journal_year = JournalHelper::getJournalYearFromJournalMonth($journal_month);
      if (!$journal_year) {
        return;
      }

      $key = NULL;
      if (str_starts_with($op, 'accepted_to_voices:')) {
        $key = 'accepted_to_voices_' . $journal_year->id();
      }
      elseif (str_starts_with($op, 'not_accepted_to_voices:')) {
        $key = 'not_accepted_to_voices_' . $journal_year->id();
      }

      $value = $key_value->get($key, ['emails' => []]);

      // If operation is send, just send to everyone. If operation is
      // update, then only send to whoever didn't receive it yet.
      $poems = [];
      if (str_starts_with($op, 'accepted_to_voices:')) {
        $poems = JournalHelper::getPoemsByAcceptanceGroupedByEmail($journal_year, 'Accepted');
      }
      elseif (str_starts_with($op, 'not_accepted_to_voices:')) {
        // Not accepted also excludes the monthly prize winners.
        $poems = JournalHelper::getNotAcceptedPoemsGroupedByEmail($journal_year);
      }

      $to_send = array_keys($poems);
      if (str_ends_with($op, ':update')) {
        // List of emails.
        $already_sent = $value['emails'] ?? [];
        $to_send = array_diff($to_send, $already_sent);
      }

      // Create a batch operation to send the emails.
      foreach ($to_send as $email) {
        $poem = reset($poems[$email]);
        $data = [
          'poem_id' => $poem->id(),
          'journal_year_id' => $journal_year->id(),
        ];
        $operations[] = [
          [static::class, 'sendNotification'],
          [$op, $data],
        ];
      }
    }
    elseif (str_starts_with($op, 'futureverse_invitations_month:')) {
      $journal_year = JournalHelper::getJournalYearFromJournalMonth($journal_month);
      if (!$journal_year) {
        return;
      }

      foreach ($this->futureverseInvitationMonthPoems() as $poem) {
        $data = [
          'poem_id' => $poem->id(),
          'journal_month_id' => $journal_month->id(),
          'journal_year_id' => $journal_year->id(),
        ];
        $operations[] = [
          [static::class, 'sendNotification'],
          [$op, $data],
        ];
      }
    }
    elseif (str_starts_with($op, 'futureverse_invitations:')) {
      $journal_year = JournalHelper::getJournalYearFromJournalMonth($journal_month);
      if (!$journal_year) {
        return;
      }

      $key = 'futureverse_invitations_' . $journal_year->id();
      $value = $key_value->get($key, ['emails' => []]);

      // If operation is send, just send to everyone. If operation is
      // update, then only send to whoever didn't receive it yet.
      $poems = JournalHelper::getFutureverseInvitationPoemsGroupedByEmail($journal_year);

      $to_send = array_keys($poems);
      if (str_ends_with($op, ':update')) {
        // List of emails.
        $already_sent = $value['emails'] ?? [];
        $to_send = array_diff($to_send, $already_sent);
      }

      // Create a batch operation to send the emails.
      foreach ($to_send as $email) {
        $poem = reset($poems[$email]);
        $data = [
          'poem_id' => $poem->id(),
          'journal_year_id' => $journal_year->id(),
        ];
        $operations[] = [
          [static::class, 'sendNotification'],
          [$op, $data],
        ];
      }
    }
    // Futureverse shortlisted.
    elseif (str_starts_with($op, 'futureverse_shortlisted:')) {
      $journal_year = JournalHelper::getJournalYearFromJournalMonth($journal_month);
      if (!$journal_year) {
        return;
      }

      $key = 'futureverse_shortlisted_' . $journal_year->id();
      $value = $key_value->get($key, ['emails' => []]);

      // If operation is send, just send to everyone. If operation is
      // update, then only send to whoever didn't receive it yet.
      $poems = JournalHelper::getFutureverseShortlistedPoemsGroupedByEmail($journal_year);

      $to_send = array_keys($poems);
      if (str_ends_with($op, ':update')) {
        // List of emails.
        $already_sent = $value['emails'] ?? [];
        $to_send = array_diff($to_send, $already_sent);
      }

      // Create a batch operation to send the emails.
      foreach ($to_send as $email) {
        $poem = reset($poems[$email]);
        $data = [
          'poem_id' => $poem->id(),
          'journal_year_id' => $journal_year->id(),
        ];
        $operations[] = [
          [static::class, 'sendNotification'],
          [$op, $data],
        ];
      }
    }

    if (!$operations) {
      $this->messenger()->addWarning($this->t('No emails were queued. Eligible poems must have acceptance level <em>Yes</em> or <em>Monthly prize winner</em>, grade 10–12, no FutureVerse application yet, and belong to the selected journal month.'));
      return;
    }

    if (str_starts_with($op, 'futureverse_invitations')) {
      $mail_plugin = \Drupal::service('plugin.manager.piv_mail')
        ->createInstance('journal_poem_futureverse_invitation');
      $mail_config = $mail_plugin->getConfiguration();
      foreach (['en', 'fr'] as $langcode) {
        if (empty($mail_config[$langcode]['active'])) {
          $this->messenger()->addError($this->t('The FutureVerse invitation email template is inactive for @langcode. Enable it under Poetry in Voice → E-mails → Journal Poem: Futureverse invitation.', [
            '@langcode' => $langcode,
          ]));
          return;
        }
        if (empty($mail_config[$langcode]['to']['journal_poem_email'])) {
          $this->messenger()->addError($this->t('The FutureVerse invitation email template has no student recipient configured for @langcode. Check the “Email from the journal poem” TO option.', [
            '@langcode' => $langcode,
          ]));
          return;
        }
      }
    }

    $this->messenger()->addStatus($this->t('Done!'));

    $batch = [
      'operations' => $operations,
      'finished' => [static::class, 'finishBatch'],
      'title' => 'Sending emails...',
    ];
    batch_set($batch);
  }

  /**
   * Send the notification.
   */
  public static function sendNotification($op, $data, &$context): void {
    $request_time = \Drupal::time()->getCurrentTime();
    $journal_year_id = $data['journal_year_id'] ?? 0;
    $journal_month_id = $data['journal_month_id'] ?? 0;
    $poem_id = $data['poem_id'] ?? 0;

    $poem = \Drupal::entityTypeManager()->getStorage('node')->load($poem_id);
    if (!$poem) {
      return;
    }

    $replacements_service = \Drupal::service('piv_mail.replacements_service');
    $replacements_service
      ->addSource('journal_poem', $poem)
      ->addSource('user', $poem->getOwner());

    // Reminders are different in the way the states are saved, we
    // save the last time a reminder was sent for the individual users
    // per journal year by using a different collection per journal
    // year.
    $reminder_key_value = \Drupal::keyValue("journal_reminders_{$journal_year_id}");
    if (str_ends_with($op, ':reminder')) {
      $key = "{$op}:{$poem->uid->target_id}";
      $map = [
        'monthly_prize_winner:reminder' => 'journal_poem_monthly_prize_winner',
        'accepted_to_voices:reminder' => 'journal_poem_accepted_voices_anthology',
        'futureverse_invitations:reminder' => 'journal_poem_futureverse_invitation',
        'futureverse_shortlisted:reminder' => 'journal_poem_futureverse_shortlisted',
      ];
      if (array_key_exists($op, $map)) {
        $results = piv_mail_send_mail($map[$op], $poem->langcode->value, $replacements_service, NULL, FALSE, FALSE, TRUE);
        if ($results !== FALSE) {
          $context['results'][$poem_id] = $poem_id;
          $reminder_key_value->set($key, $request_time);
        }
      }
      return;
    }

    $key_value = \Drupal::keyValue('journal_notifications');
    if (str_starts_with($op, 'monthly_prize_winner:') || str_starts_with($op, 'monthly_prize_losers:')) {
      $key = NULL;
      if (str_starts_with($op, 'monthly_prize_winner:')) {
        $key = "monthly_prize_winner_{$journal_month_id}";
      }
      elseif (str_starts_with($op, 'monthly_prize_losers:')) {
        $key = "monthly_prize_losers_{$journal_month_id}";
      }
      $value = $key_value->get($key, ['poems' => []]);
      $results = FALSE;
      if (str_starts_with($op, 'monthly_prize_winner:')) {
        $results = piv_mail_send_mail('journal_poem_monthly_prize_winner', $poem->langcode->value, $replacements_service);
      }
      elseif (str_starts_with($op, 'monthly_prize_losers:')) {
        $results = piv_mail_send_mail('journal_poem_monthly_prize_losers', $poem->langcode->value, $replacements_service);
      }
      if ($results !== FALSE) {
        $value['poems'][] = $poem_id;
        $value['poems'] = array_unique($value['poems']);
        $context['results'][$poem_id] = $poem_id;
        $key_value->set($key, $value);
        // Set time for reminders.
        if (str_starts_with($op, 'monthly_prize_winner:')) {
          $reminder_key_value->set("monthly_prize_winner:reminder:{$poem->uid->target_id}", $request_time);
        }
      }
    }
    elseif (str_starts_with($op, 'accepted_to_voices:') || str_starts_with($op, 'not_accepted_to_voices:')) {
      $key = NULL;
      if (str_starts_with($op, 'accepted_to_voices:')) {
        $key = 'accepted_to_voices_' . $data['journal_year_id'];
      }
      elseif (str_starts_with($op, 'not_accepted_to_voices:')) {
        $key = 'not_accepted_to_voices_' . $data['journal_year_id'];
      }
      $value = $key_value->get($key, ['emails' => []]);
      if (str_starts_with($op, 'accepted_to_voices:')) {
        $results = piv_mail_send_mail('journal_poem_accepted_voices_anthology', $poem->langcode->value, $replacements_service);
      }
      elseif (str_starts_with($op, 'not_accepted_to_voices:')) {
        $results = piv_mail_send_mail('journal_poem_not_accepted_voices_anthology', $poem->langcode->value, $replacements_service);
      }
      if ($results !== FALSE) {
        $value['emails'][] = $poem->field_email1->value;
        $value['emails'] = array_unique($value['emails']);
        $context['results'][$poem_id] = $poem_id;
        $key_value->set($key, $value);
        if (str_starts_with($op, 'accepted_to_voices:')) {
          $reminder_key_value->set("accepted_to_voices:reminder:{$poem->uid->target_id}", $request_time);
        }
      }
    }
    elseif (str_starts_with($op, 'futureverse_invitations_month:') || str_starts_with($op, 'futureverse_invitations:')) {
      if ($journal_year_id) {
        $journal_year = \Drupal::entityTypeManager()->getStorage('journal_year')->load($journal_year_id);
        if ($journal_year && JournalHelper::hasFutureverseApplicationByEmail($journal_year, $poem->field_email1->value ?? '')) {
          return;
        }
      }

      $results = piv_mail_send_mail('journal_poem_futureverse_invitation', $poem->langcode->value, $replacements_service);
      if ($results === FALSE) {
        return;
      }

      $context['results'][$poem_id] = $poem_id;
      $email = $poem->field_email1->value;
      $year_id = $data['journal_year_id'] ?? 0;
      if ($year_id) {
        $year_key = 'futureverse_invitations_' . $year_id;
        $year_value = $key_value->get($year_key, ['emails' => []]);
        $year_value['emails'][] = $email;
        $year_value['emails'] = array_unique($year_value['emails']);
        $key_value->set($year_key, $year_value);
        $reminder_key_value = \Drupal::keyValue("journal_reminders_{$year_id}");
        $reminder_key_value->set("futureverse_invitations:reminder:{$poem->uid->target_id}", $request_time);
      }

      if (str_starts_with($op, 'futureverse_invitations_month:') && $journal_month_id) {
        $month_key = 'futureverse_invitations_month_' . $journal_month_id;
        $month_value = $key_value->get($month_key, ['poems' => []]);
        $month_value['poems'][] = $poem_id;
        $month_value['poems'] = array_unique($month_value['poems']);
        $key_value->set($month_key, $month_value);
      }
    }
    elseif (str_starts_with($op, 'futureverse_shortlisted:')) {
      $key = 'futureverse_shortlisted_' . $data['journal_year_id'];
      $value = $key_value->get($key, ['emails' => []]);
      $results = piv_mail_send_mail('journal_poem_futureverse_shortlisted', $poem->langcode->value, $replacements_service);
      if ($results !== FALSE) {
        $value['emails'][] = $poem->field_email1->value;
        $value['emails'] = array_unique($value['emails']);
        $context['results'][$poem_id] = $poem_id;
        $key_value->set($key, $value);
        $reminder_key_value->set("futureverse_shortlisted:reminder:{$poem->uid->target_id}", $request_time);
      }
    }
  }

  /**
   * Batch finished.
   */
  public static function finishBatch($success, $results, $operations): void {
    // Do nothing, the confirm form already handles messaging.
  }

}
