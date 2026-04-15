<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse;

/**
 * Once Url Generator abstract class.
 */
abstract class OnceUrlGenerator {

  /**
   * Key Value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected $keyValueStore;

  /**
   * Load related entities given the journal poem id.
   *
   * This return an array with the Journal Poem, Journal Month and the
   * Journal Year.
   */
  public function entitiesFromJournalPoemId($journal_poem_id): array {
    $journal_poem = $this->entityTypeManager
      ->getStorage('node')
      ->load($journal_poem_id);

    if (!$journal_poem) {
      return [NULL, NULL, NULL];
    }

    $journal_month = JournalHelper::getJournalMonthFromPoem($journal_poem);
    if (!$journal_month) {
      return [$journal_poem, NULL, NULL];
    }

    $journal_year = JournalHelper::getJournalYearFromJournalMonth($journal_month);
    if (!$journal_year) {
      return [$journal_poem, $journal_month, NULL];
    }

    return [$journal_poem, $journal_month, $journal_year];
  }

  /**
   * Return data given hash.
   */
  public function dataFromHash($hash) {
    return $this->keyValueStore->get($hash);
  }

}
