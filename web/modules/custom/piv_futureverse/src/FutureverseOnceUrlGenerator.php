<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Url;
use Drupal\Core\Site\Settings;

/**
 * Futureverse url generator service.
 */
final class FutureverseOnceUrlGenerator extends OnceUrlGenerator {

  /**
   * Constructs a BioEnrichment object.
   */
  public function __construct(
    protected readonly KeyValueExpirableFactoryInterface $keyValue,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->keyValueStore = $keyValue->get('piv_futureverse_futureverse_urls');
  }

  /**
   * Check if a bio was already created.
   */
  /*public function exists(int $journal_poem_id) {
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
  }*/

  /**
   * Generate the once form url.
   */
  public function generateUrl(string $bundle, int $entity_id): ?Url {
    $data = '';
    if ($bundle == 'native_student') {
      // For native students the url is just based on the futureverse
      // since it will be created by a teacher or admin before, there
      // will be no poem entry or poet bio prior to that. The entity_id
      // is then the futureverse application entity id.
      $futureverse = $this->entityTypeManager
        ->getStorage('futureverse_application')
        ->load($entity_id);
      if (!$futureverse) {
        return NULL;
      }
      $data = $futureverse->uuid();
    }
    else {
      // Otherwise this is a normal futureverse invitation based on a
      // journal poem, the futureverse application entity doesn't exist
      // yet.
      $journal_poem = $this->entityTypeManager
        ->getStorage('node')
        ->load($entity_id);

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
      $data .= ':' . $journal_year->uuid();
    }

    $hash = Crypt::hmacBase64($data, Settings::getHashSalt());
    $this->keyValueStore->setWithExpire($hash, [$bundle, $entity_id], 31536000);
    return Url::fromRoute('piv_futureverse.futureverse_application', [
      'hash' => $hash,
    ],
    [
      'absolute' => TRUE,
    ]);
  }

}
