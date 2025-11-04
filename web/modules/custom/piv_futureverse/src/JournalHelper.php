<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;

/**
 * Helper functions for journal functionalities.
 */
class JournalHelper {

  /**
   * Get journal months for current date.
   */
  public static function getJournalMonths() : array {
    $timezone = new \DateTimeZone(date_default_timezone_get());
    $current_date = (new DrupalDateTime('now', $timezone))->setTime(0, 0, 0);
    $current_date->setTime(0, 0, 0);
    $today = $current_date->format('Y-m-d');
    $storage = \Drupal::entityTypeManager()->getStorage('journal_month');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_start_date', $today, '<=')
      ->condition('field_end_date', $today, '>=');

    $ids = $query->execute();
    return $ids ? $storage->loadMultiple($ids) : [];
  }

  /**
   * Get all accepted poems in a year.
   *
   * A user can have multiple accepted poems, we group them by the
   * email.
   */
  public static function getPoemsAcceptedGroupedByEmail(JournalYearInterface $journal_year) : array {
    $accepted = [];
    foreach ($journal_year->field_journal_months->referencedEntities() as $journal_month) {
      foreach ($journal_month->field_journal_poems->referencedEntities() as $poem) {
        if ($poem->field_acceptance_level->value == 'Accepted') {
          $email = $poem->field_email1->value;
          $accepted[$email][] = $poem;
        }
      }
    }
    return $accepted;
  }

  /**
   * Get the monthly prize winner.
   */
  public static function getMonthlyPrizeWinner(JournalMonthInterface $journal_month) : array {
    // There should be only one winner, but in theory multiple can be
    // made winners.
    $poems = $journal_month->field_journal_poems->referencedEntities();
    return array_filter($poems, function ($poem) {
      return $poem->field_acceptance_level->value == 'Monthly prize winner';
    });
  }

  /**
   * Get the monthly prize losers.
   */
  public static function getMonthlyPrizeLosers(JournalMonthInterface $journal_month) : array {
    // There should be only one winner, but in theory multiple can be
    // made winners.
    $poems = $journal_month->field_journal_poems->referencedEntities();
    return array_filter($poems, function ($poem) {
      return $poem->field_acceptance_level->value != 'Monthly prize winner';
    });
  }

  /**
   * Get the monthly prize losers non duplicated.
   *
   * This returns a single poem per email, preventing it to send
   * multiple e-mails to the same recipient.
   */
  public static function getMonthlyPrizeLosersUniqueEmail(JournalMonthInterface $journal_month) : array {
    $losers = self::getMonthlyPrizeLosers($journal_month);
    // To guarantee that it's always the same poems returned, sort them
    // by id.
    usort($losers, function ($a, $b) {
      return $a->id() <=> $b->id();
    });

    // Get all winners emails to prevent sending a loser email to them.
    $winners = self::getMonthlyPrizeWinner($journal_month);
    $winner_emails = array_map(fn($w) => $w->field_email1->value, $winners);

    $poems = [];
    foreach ($losers as $loser) {
      $email = $loser->field_email1->value ?? NULL;
      if (!$email || in_array($email, $winner_emails) || array_key_exists($email, $poems)) {
        continue;
      }
      $poems[$email] = $loser;
    }
    return array_values($poems);
  }

  /**
   * Given a poem, return the journal month.
   *
   * Journal Poems are nodes.
   */
  public static function getJournalMonthFromPoem(NodeInterface $node) {
    $journal_months = \Drupal::entityTypeManager()->getStorage('journal_month')
      ->loadByProperties(['field_journal_poems' => $node->id()]);
    return $journal_months ? end($journal_months) : NULL;
  }

  /**
   * Given a journal month, return the journal year.
   */
  public static function getJournalYearFromJournalMonth(JournalMonthInterface $journal_month) {
    $journal_years = \Drupal::entityTypeManager()->getStorage('journal_year')
      ->loadByProperties(['field_journal_months' => $journal_month->id()]);
    return $journal_years ? end($journal_years) : NULL;
  }

}
