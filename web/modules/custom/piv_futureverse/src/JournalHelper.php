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
   * Get poems grouped by email given acceptance.
   */
  public static function getPoemsByAcceptanceGroupedByEmail(JournalYearInterface $journal_year, $acceptance_level, $reverse = FALSE) : array {
    if (is_scalar($acceptance_level)) {
      $acceptance_level = [$acceptance_level];
    }
    $poems = [];
    foreach ($journal_year->field_journal_months->referencedEntities() as $journal_month) {
      foreach ($journal_month->field_journal_poems->referencedEntities() as $poem) {
        $valid = $reverse
          ? !in_array($poem->field_acceptance_level->value, $acceptance_level)
          : in_array($poem->field_acceptance_level->value, $acceptance_level);

        if ($valid) {
          $email = $poem->field_email1->value;
          $poems[$email][] = $poem;
        }
      }
    }
    return $poems;
  }

  /**
   * Get poems grouped by email given acceptance.
   */
  public static function getPoemsByAcceptanceGroupedByEmailWithoutPoetBio(JournalYearInterface $journal_year, $acceptance_level) : array {
    if (is_scalar($acceptance_level)) {
      $acceptance_level = [$acceptance_level];
    }

    $poems = [];
    foreach ($journal_year->field_journal_months->referencedEntities() as $journal_month) {
      foreach ($journal_month->field_journal_poems->referencedEntities() as $poem) {
        if (!in_array($poem->field_acceptance_level->value, $acceptance_level)) {
          continue;
        }
        // Check if there is a poet bio.
        $langcode = $poem->langcode->value;
        if (self::hasPoetBio($journal_year, $poem)) {
          continue;
        }
        $email = $poem->field_email1->value;
        $poems[$email][$langcode][] = $poem;
      }
    }
    return $poems;
  }

  /**
   * Check if there is a futureverse application for poem for year.
   */
  public static function hasFutureverseApplication($journal_year, $poem) {
    // Check if there is a futureverse_application.
    $futureverse_application_storage = \Drupal::entityTypeManager()->getStorage('futureverse_application');
    $futureverse_applications = $futureverse_application_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_journal_year', $journal_year->id())
      ->condition('uid', $poem->uid->target_id)
      ->condition('bundle', 'student')
      ->execute();
    return count($futureverse_applications) > 0;
  }

  /**
   * Check if there is a poet bio for poem for year.
   */
  public static function hasPoetBio($journal_year, $poem) {
    $poet_bio_storage = \Drupal::entityTypeManager()
      ->getStorage('poet_bio');
    // Check if there is a poet bio.
    $langcode = $poem->langcode->value;
    $poet_bios = $poet_bio_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_journal_year', $journal_year->id())
      ->condition('field_language', $langcode)
      ->condition('uid', $poem->uid->target_id)
      ->execute();
    return count($poet_bios) > 0;
  }

  /**
   * Get poems grouped by email given acceptance.
   */
  public static function getPoemsByAcceptanceGroupedByEmailWithoutFutureverseApplication(JournalYearInterface $journal_year, $acceptance_level) : array {
    if (is_scalar($acceptance_level)) {
      $acceptance_level = [$acceptance_level];
    }

    $poems = [];
    foreach ($journal_year->field_journal_months->referencedEntities() as $journal_month) {
      foreach ($journal_month->field_journal_poems->referencedEntities() as $poem) {
        if (!in_array($poem->field_acceptance_level->value, $acceptance_level)) {
          continue;
        }
        if (!self::hasFutureverseApplication($journal_year, $poem)) {
          $email = $poem->field_email1->value;
          $poems[$email][] = $poem;
        }
      }
    }
    return $poems;
  }

  /**
   * Return the shortlisted for futureverse poems.
   */
  public static function getFutureverseShortlistedPoemsGroupedByEmailWithoutPoetBio(JournalYearInterface $journal_year) {
    $poems = [];
    foreach ($journal_year->field_journal_months->referencedEntities() as $journal_month) {
      foreach ($journal_month->field_journal_poems->referencedEntities() as $poem) {
        if (!empty($poem->field_shortlisted_for_futurevers->value)) {

          $email = $poem->field_email1->value;
          $poems[$email][] = $poem;
        }
      }
    }
    return $poems;
  }

  /**
   * Return the shortlisted for futureverse poems.
   */
  public static function getFutureverseShortlistedPoemsGroupedByEmail(JournalYearInterface $journal_year) {
    $poems = [];
    foreach ($journal_year->field_journal_months->referencedEntities() as $journal_month) {
      foreach ($journal_month->field_journal_poems->referencedEntities() as $poem) {
        if (!empty($poem->field_shortlisted_for_futurevers->value)) {
          $email = $poem->field_email1->value;
          $poems[$email][] = $poem;
        }
      }
    }
    return $poems;
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
   * Get non accepted poems.
   *
   * This excludes poems that were not accepted but where another poem
   * by the same owner was accepted.
   */
  public static function getNotAcceptedPoemsGroupedByEmail(JournalYearInterface $journal_year) : array {
    $accepted_poems = self::getPoemsByAcceptanceGroupedByEmail($journal_year, ['Accepted', 'Monthly prize winner']);
    // Reversed filter.
    $not_accepted_poems = self::getPoemsByAcceptanceGroupedByEmail($journal_year, ['Accepted', 'Monthly prize winner'], TRUE);
    return array_diff_key($not_accepted_poems, $accepted_poems);
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

  /**
   * Return a poet bio given a journal poem node.
   */
  public static function getPoetBioByPoem(NodeInterface $node) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $langcode = $node->langcode->value;
    $journal_year = $entity_type_manager->getStorage('journal_year')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_journal_months.entity.field_journal_poems.target_id', $node->id())
      ->execute();
    if (!$journal_year) {
      return NULL;
    }
    $poet_bio = $entity_type_manager->getStorage('poet_bio')->loadByProperties([
      'field_journal_year' => $journal_year,
      'field_language' => $langcode,
    ]);
    return $poet_bio ? reset($poet_bio) : NULL;
  }

}
