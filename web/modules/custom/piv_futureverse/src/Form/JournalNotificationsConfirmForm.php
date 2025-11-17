<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
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
    return parent::buildForm($form, $form_state);
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
    elseif (str_starts_with($op, 'futureverse_invitations:')) {
      return $this->t('Are you sure you want to send the <em>futureverse invitations</em> notifications?');
    }
    elseif (str_starts_with($op, 'futureverse_shortlisted:')) {
      return $this->t('Are you sure you want to send the <em>futureverse shortlisted</em> notifications?');
    }

    return $this->t('Invalid operation.');
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
    $set_operations = function ($poem_id, $journal_year) use ($op, &$operations) {
      $data = [
        'poem_id' => $poem_id,
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
          $set_operations($poem->id(), $journal_year->id());
        }
      }
    }
    elseif ($op == 'futureverse_invitations:reminder') {
      $poems = JournalHelper::getPoemsByAcceptanceGroupedByEmailWithoutFutureverseApplication($journal_year, [
        'Yes',
        'Monthly prize winner',
      ]);
      // Create a batch operation to send the emails.
      foreach ($poems as $poem) {
        $poem = reset($poem);
        $set_operations($poem->id(), $journal_year->id());
      }
    }
    elseif ($op == 'futureverse_shortlisted:reminder') {
      // TRUE as the last parameter is to get shortlisted only.
      $poems = JournalHelper::getFutureverseShortlistedPoemsGroupedByEmailWithoutPoetBio($journal_year);
      // Create a batch operation to send the emails.
      foreach ($poems as $poem) {
        $poem = reset($poem);
        $set_operations($poem->id(), $journal_year->id());
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

    $this->messenger()->addStatus($this->t('Done!'));

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
    elseif (str_starts_with($op, 'futureverse_invitations:')) {
      $journal_year = JournalHelper::getJournalYearFromJournalMonth($journal_month);
      if (!$journal_year) {
        return;
      }

      $key = 'futureverse_invitations_' . $journal_year->id();
      $value = $key_value->get($key, ['emails' => []]);

      // If operation is send, just send to everyone. If operation is
      // update, then only send to whoever didn't receive it yet.
      $poems = JournalHelper::getPoemsByAcceptanceGroupedByEmail($journal_year, [
        'Yes',
        'Monthly prize winner',
      ]);

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
    $key_value = \Drupal::keyValue('journal_notifications');
    if (str_ends_with($op, ':reminder')) {
      $key = implode('_', [$op, $data['journal_year_id']]);
      $poem = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load($data['poem_id']);
      if (!$poem) {
        return;
      }

      $replacements_service = \Drupal::service('piv_mail.replacements_service');
      $replacements_service
        ->addSource('journal_poem', $poem)
        ->addSource('user', $poem->getOwner());

      $map = [
        'monthly_prize_winner:reminder' => 'journal_poem_monthly_prize_winner',
        'accepted_to_voices:reminder' => 'journal_poem_accepted_voices_anthology',
        'futureverse_invitations:reminder' => 'journal_poem_futureverse_invitation',
        'futureverse_shortlisted:reminder' => 'journal_poem_futureverse_shortlisted',
      ];
      if (array_key_exists($op, $map)) {
        $results = piv_mail_send_mail($map[$op], $poem->langcode->value, $replacements_service, NULL, FALSE, FALSE, TRUE);
        if ($results !== FALSE) {
          $context['results'][$data['poem_id']] = $data['poem_id'];
          $request_time = \Drupal::time()->getCurrentTime();
          // For reminders we only care about saving the last time
          // emails were sent.
          $key_value->set($key, $request_time);
        }
      }
    }
    elseif (str_starts_with($op, 'monthly_prize_winner:') || str_starts_with($op, 'monthly_prize_losers:')) {
      $key = NULL;
      if (str_starts_with($op, 'monthly_prize_winner:')) {
        $key = 'monthly_prize_winner_' . $data['journal_month_id'];
      }
      elseif (str_starts_with($op, 'monthly_prize_losers:')) {
        $key = 'monthly_prize_losers_' . $data['journal_month_id'];
      }

      $value = $key_value->get($key, ['poems' => []]);
      $poem = \Drupal::entityTypeManager()->getStorage('node')->load($data['poem_id']);
      if (!$poem) {
        return;
      }

      $replacements_service = \Drupal::service('piv_mail.replacements_service');
      $replacements_service
        ->addSource('journal_poem', $poem)
        ->addSource('user', $poem->getOwner());

      $results = FALSE;
      if (str_starts_with($op, 'monthly_prize_winner:')) {
        $results = piv_mail_send_mail('journal_poem_monthly_prize_winner', $poem->langcode->value, $replacements_service);
      }
      elseif (str_starts_with($op, 'monthly_prize_losers:')) {
        $results = piv_mail_send_mail('journal_poem_monthly_prize_losers', $poem->langcode->value, $replacements_service);
      }
      if ($results !== FALSE) {
        $value['poems'][] = $data['poem_id'];
        $value['poems'] = array_unique($value['poems']);
        $context['results'][$data['poem_id']] = $data['poem_id'];
        $key_value->set($key, $value);

        // Set time for reminders.
        if (str_starts_with($op, 'monthly_prize_winner:')) {
          $request_time = \Drupal::time()->getCurrentTime();
          $key_value->set('monthly_prize_winner:reminder_' . $data['journal_month_id'], $request_time);
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
      $poem = \Drupal::entityTypeManager()->getStorage('node')->load($data['poem_id']);
      if (!$poem) {
        return;
      }

      $replacements_service = \Drupal::service('piv_mail.replacements_service');
      $replacements_service
        ->addSource('journal_poem', $poem)
        ->addSource('user', $poem->getOwner());

      if (str_starts_with($op, 'accepted_to_voices:')) {
        $results = piv_mail_send_mail('journal_poem_accepted_voices_anthology', $poem->langcode->value, $replacements_service);
      }
      elseif (str_starts_with($op, 'not_accepted_to_voices:')) {
        $results = piv_mail_send_mail('journal_poem_not_accepted_voices_anthology', $poem->langcode->value, $replacements_service);
      }

      if ($results !== FALSE) {
        $value['emails'][] = $poem->field_email1->value;
        $value['emails'] = array_unique($value['emails']);
        $context['results'][$data['poem_id']] = $data['poem_id'];
        $key_value->set($key, $value);

        if (str_starts_with($op, 'accepted_to_voices:')) {
          // Set time for reminders.
          $request_time = \Drupal::time()->getCurrentTime();
          $key_value->set('accepted_to_voices:reminder_' . $data['journal_year_id'], $request_time);
        }
      }
    }
    elseif (str_starts_with($op, 'futureverse_invitations:')) {
      $key = 'futureverse_invitations_' . $data['journal_year_id'];
      $value = $key_value->get($key, ['emails' => []]);

      $poem = \Drupal::entityTypeManager()->getStorage('node')->load($data['poem_id']);
      if (!$poem) {
        return;
      }

      $replacements_service = \Drupal::service('piv_mail.replacements_service');
      $replacements_service
        ->addSource('journal_poem', $poem)
        ->addSource('user', $poem->getOwner());

      $results = piv_mail_send_mail('journal_poem_futureverse_invitation', $poem->langcode->value, $replacements_service);

      if ($results !== FALSE) {
        $value['emails'][] = $poem->field_email1->value;
        $value['emails'] = array_unique($value['emails']);
        $context['results'][$data['poem_id']] = $data['poem_id'];
        $key_value->set($key, $value);

        // Set time for reminders.
        $request_time = \Drupal::time()->getCurrentTime();
        $key_value->set('futureverse_invitations:reminder_' . $data['journal_year_id'], $request_time);
      }
    }
    elseif (str_starts_with($op, 'futureverse_shortlisted:')) {
      $key = 'futureverse_shortlisted_' . $data['journal_year_id'];
      $value = $key_value->get($key, ['emails' => []]);

      $poem = \Drupal::entityTypeManager()->getStorage('node')->load($data['poem_id']);
      if (!$poem) {
        return;
      }

      $replacements_service = \Drupal::service('piv_mail.replacements_service');
      $replacements_service
        ->addSource('journal_poem', $poem)
        ->addSource('user', $poem->getOwner());

      $results = piv_mail_send_mail('journal_poem_futureverse_shortlisted', $poem->langcode->value, $replacements_service);

      if ($results !== FALSE) {
        $value['emails'][] = $poem->field_email1->value;
        $value['emails'] = array_unique($value['emails']);
        $context['results'][$data['poem_id']] = $data['poem_id'];
        $key_value->set($key, $value);
        // Set time for reminders.
        $request_time = \Drupal::time()->getCurrentTime();
        $key_value->set('futureverse_shortlisted:reminder_' . $data['journal_year_id'], $request_time);
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
