<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\piv_mail\PivMailPluginManager;
use Drupal\Core\KeyValueStore\KeyValueFactory;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\piv_futureverse\Form\JournalNotificationsConfirmForm;
use Drupal\Component\Utility\Environment;

/**
 * Reminders service.
 */
final class NotificationReminders {

  /**
   * Key value collection.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  private KeyValueStoreInterface $journalNotificationsCollection;

  /**
   * Constructs a NotificationReminders object.
   */
  public function __construct(
    private readonly KeyValueFactory $keyValueFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PivMailPluginManager $pivMailPluginManager,
    private readonly TimeInterface $time,
  ) {
    $this->journalNotificationsCollection = $this->keyValueFactory
      ->get('journal_notifications');
  }

  /**
   * Process email reminders through cron.
   */
  public function process(): void {
    Environment::setTimeLimit(0);
    // Get current journal year.
    $journal_months = JournalHelper::getJournalMonths();
    if (!$journal_months) {
      return;
    }
    $journal_month = reset($journal_months);
    $journal_year = JournalHelper::getJournalYearFromJournalMonth($journal_month);
    if (!$journal_year) {
      return;
    }

    // Map the operation to the email plugin id.
    $map = [
      'monthly_prize_winner:reminder' => 'journal_poem_monthly_prize_winner',
      'accepted_to_voices:reminder' => 'journal_poem_accepted_voices_anthology',
      'futureverse_invitations:reminder' => 'journal_poem_futureverse_invitation',
      'futureverse_shortlisted:reminder' => 'journal_poem_futureverse_shortlisted',
    ];

    // Futureverse invitations are sent before the shortlist cutoff
    // date, shortlist only after cutoff.
    $cutoff = INF;
    if (!$journal_year->field_futureverse_shortlist_date->isEmpty()) {
      $cutoff = $journal_year->field_futureverse_shortlist_date->date?->getTimestamp();
    }

    if ($this->time->getCurrentTime() > $cutoff) {
      unset($map['futureverse_invitations:reminder']);
    }
    else {
      unset($map['futureverse_shortlisted:reminder']);
    }

    $journal_notifications = $this->keyValueFactory
      ->get('journal_notifications');
    $journal_reminders = $this->keyValueFactory
      ->get('journal_reminders_' . $journal_year->id());

    $context = [];
    foreach ($map as $op => $id) {
      $instance = $this->pivMailPluginManager->createInstance($id);

      // Reminders should only be sent if at least one email was sent
      // already manually.
      $sent_key = str_replace(':reminder', '', "{$op}_{$journal_year->id()}");
      $values = $journal_notifications->get($sent_key, []);
      $count = count($values['emails'] ?? $values['poems'] ?? []);
      if (!$count) {
        continue;
      }

      $operations = JournalNotificationsConfirmForm::getReminderOperations($op, $journal_year);
      foreach ($operations as $operation) {
        [$o, $d] = $operation[1];
        $key = "{$op}:{$d['uid']}";
        if ($this->shouldSendReminders($instance, $key, $journal_reminders)) {
          JournalNotificationsConfirmForm::sendNotification($o, $d, $context);
        }
      }
    }
  }

  /**
   * Check if its in time to send reminder.
   */
  private function shouldSendReminders($instance, $key, $journal_reminders) {
    if (!$instance) {
      return FALSE;
    }

    $interval = $instance->getConfiguration()['en']['reminder_interval_days'] ?? 0;
    if ($interval == 0) {
      return FALSE;
    }

    // Convert interval to seconds.
    $interval = $interval * 86400;
    $last_sent = $journal_reminders->get($key, 0);
    $now = $this->time->getCurrentTime();
    return $last_sent + $interval < $now;
  }

}
