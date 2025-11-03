<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\piv_futureverse\BioEnrichment;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for poet bio enrichment form.
 */
final class PoetBioEnrichmentController extends ControllerBase {

  /**
   * Constructs a PoetBioEnrichmentController object.
   */
  public function __construct(
    protected BioEnrichment $bioEnrichment,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('piv_futureverse.bio_enrichment')
    );
  }

  /**
   * Custom access verification.
   */
  public function access(string $hash) {
    $journal_poem_id = $this->bioEnrichment->dataFromHash($hash);
    [$journal_poem, , $journal_year] = $this->bioEnrichment->entitiesFromJournalPoemId($journal_poem_id);
    if (!$journal_poem || !$journal_year) {
      return AccessResult::forbidden();
    }

    // Prevent creating one if there is already a poet bio for the same
    // journal year, language and user.
    $entries = $this->entityTypeManager()->getStorage('poet_bio')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_journal_year', $journal_year->id())
      ->condition('field_language', $journal_poem->langcode->value)
      ->condition('uid', $journal_poem->uid->target_id)
      ->execute();

    return AccessResult::allowedIf(empty($entries));
  }

  /**
   * Displays the poet bio enrichment form.
   */
  public function __invoke(string $hash): array {
    $journal_poem_id = $this->bioEnrichment->dataFromHash($hash);
    [$journal_poem, , $journal_year] = $this->bioEnrichment->entitiesFromJournalPoemId($journal_poem_id);
    $poet_bio_storage = $this->entityTypeManager()->getStorage('poet_bio');

    // Get an address to reuse from the most recent poet bio.
    $address = [];
    $uid = $journal_poem->uid->target_id;
    $poet_bios = $poet_bio_storage->loadByProperties(['uid' => $uid]);
    if ($poet_bios) {
      $most_recent_bio = end($poet_bios);
      $address = $most_recent_bio->field_address->getValue();
    }

    $poet_bio = $poet_bio_storage->create([
      'label' => "{$journal_poem->piv_teacher_first_name->value} {$journal_poem->piv_teacher_last_name->value}",
      'field_language' => $journal_poem->langcode->value,
      'field_journal_year' => $journal_year->id(),
      'field_grade' => $journal_poem->field_grade->value,
      'field_profile_picture' => $journal_poem->field_student_photo->target_id,
      'field_school' => $journal_poem->field_school_journal->target_id,
      'field_interac_e_transfer_info' => $journal_poem->field_interac_email_or_phone_num->value,
      'field_address' => $address,
      'uid' => $uid,
    ]);

    return $this->entityFormBuilder()->getForm($poet_bio, 'enrichment');
  }

}
