<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Url;
use Drupal\Core\Site\Settings;

/**
 * Bio Enrichment service.
 */
final class BioEnrichment {

  /**
   * Key Value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected $keyValueStore;

  /**
   * Constructs a BioEnrichment object.
   */
  public function __construct(
    private readonly KeyValueExpirableFactoryInterface $keyValue,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->keyValueStore = $keyValue->get('piv_futureverse_bio_urls');
  }

  /**
   * Check if a bio was already created.
   */
  public function exists(int $journal_poem_id) {
    $journal_poem = $this->entityTypeManager
      ->getStorage('node')
      ->load($journal_poem_id);

    if (!$journal_poem) {
      return FALSE;
    }

    $journal_month = JournalHelper::getJournalMonthFromPoem($journal_poem);
    if (!$journal_month) {
      return FALSE;
    }

    $journal_year = JournalHelper::getJournalYearFromJournalMonth($journal_month);
    if (!$journal_year) {
      return FALSE;
    }

    $entries = $this->entityTypeManager
      ->getStorage('poet_bio')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_journal_year', $journal_year->id())
      ->condition('field_language', $journal_poem->langcode->value)
      ->condition('uid', $journal_poem->uid->target_id)
      ->execute();
    return count($entries) > 0;
  }

  /**
   * Generate the enrichment url.
   *
   * A Poet Bio is created for a user based in a Journal Poem, the same
   * user can have multiple poems in the same year, the Poet Bio
   * language is based on the Journal Poem language, the user is the
   * owner of the Journal Poem, there can be only one Poet Bio per user
   * per language in the same Journal Year.
   */
  public function generateUrl(int $journal_poem_id): ?Url {
    // Generate a hash based on the user uuid, language machine name and
    // journal year uuid. That way we override existing ones if they
    // already exists preventing multiple different urls for the same
    // bio.
    $journal_poem = $this->entityTypeManager
      ->getStorage('node')
      ->load($journal_poem_id);

    if (!$journal_poem) {
      return NULL;
    }

    $user = $journal_poem->getOwner();
    if (!$user) {
      return NULL;
    }

    $journal_month = JournalHelper::getJournalMonthFromPoem($journal_poem);
    if (!$journal_month) {
      return NULL;
    }

    $journal_year = JournalHelper::getJournalYearFromJournalMonth($journal_month);
    if (!$journal_year) {
      return NULL;
    }

    $data = $user->uuid();
    $data .= ':' . $journal_poem->langcode->value;
    $data .= ':' . $journal_year->uuid();
    $hash = Crypt::hmacBase64($data, Settings::getHashSalt());
    $this->keyValueStore->setWithExpire($hash, $journal_poem_id, 31536000);
    return Url::fromRoute('piv_futureverse.poet_bio_enrichment', [
      'hash' => $hash,
    ],
    [
      'language' => $journal_poem->language(),
      'absolute' => TRUE,
    ]);
  }

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
