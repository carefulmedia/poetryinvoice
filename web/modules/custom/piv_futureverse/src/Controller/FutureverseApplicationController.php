<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\piv_futureverse\FutureverseOnceUrlGenerator;

/**
 * Controller for poet bio enrichment form.
 */
final class FutureverseApplicationController extends ControllerBase {

  /**
   * Constructs a FutureverseApplicationController object.
   */
  public function __construct(
    protected FutureverseOnceUrlGenerator $futureverseOnceUrlGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('piv_futureverse.futureverse_once_url_generator')
    );
  }

  /**
   * Custom access verification.
   */
  public function access(string $hash) {
    $data = $this->futureverseOnceUrlGenerator->dataFromHash($hash);
    if (!$data) {
      return AccessResult::forbidden();
    }
    [$bundle, $entity_id] = $data;
    if ($bundle == 'native_student') {
      $entity = $this->entityTypeManager()
        ->getStorage('futureverse_application')
        ->load($entity_id);
      if ($entity && !empty($entity->field_once_link_enabled->value)) {
        return AccessResult::allowed();
      }
      return AccessResult::forbidden();
    }

    // Non-native behavior, create a futureverse application based on
    // the journal poem.
    [$journal_poem, , $journal_year] = $this->futureverseOnceUrlGenerator
      ->entitiesFromJournalPoemId($entity_id);
    if (!$journal_poem || !$journal_year) {
      return AccessResult::forbidden();
    }

    // Prevent creating one if there is already an entity for the same
    // journal year and user.
    $entries = $this->entityTypeManager()->getStorage('futureverse_application')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_journal_year', $journal_year->id())
      ->condition('uid', $journal_poem->uid->target_id)
      ->execute();

    return AccessResult::allowedIf(empty($entries));
  }

  /**
   * Displays the poet bio enrichment form.
   */
  public function __invoke(string $hash): array {
    [$bundle, $entity_id] = $this->futureverseOnceUrlGenerator->dataFromHash($hash);
    $futureverse_application_storage = $this->entityTypeManager()->getStorage('futureverse_application');

    if ($bundle == 'native_student') {
      $futureverse_application = $futureverse_application_storage->load($entity_id);
      // Set it to 0 so when saving this on this controller disables
      // editing it again.
      $futureverse_application->field_once_link_enabled = 0;
    }
    else {
      // Create new futureverse based on journal poem.
      [$journal_poem, , $journal_year] = $this->futureverseOnceUrlGenerator
        ->entitiesFromJournalPoemId($entity_id);
      // Get an address to reuse from the most recent poet bio.
      $poet_bio_storage = $this->entityTypeManager()->getStorage('poet_bio');
      $poet_bio = NULL;
      $uid = $journal_poem->uid->target_id;
      if ($poet_bios = $poet_bio_storage->loadByProperties(['uid' => $uid])) {
        $poet_bio = end($poet_bios);
      }

      $futureverse_application = $futureverse_application_storage->create([
        'bundle' => 'student',
        'label' => "{$journal_poem->piv_teacher_first_name->value} {$journal_poem->piv_teacher_last_name->value}",
        'field_grade' => $poet_bio ? $poet_bio->field_grade->value : $journal_poem->field_grade->value,
        'field_address' => $poet_bio ? $poet_bio->field_address->getValue() : [],
        'field_first_name' => $journal_poem->piv_teacher_first_name->value,
        'field_last_name' => $journal_poem->piv_teacher_last_name->value,
        'field_legal_name' => $journal_poem->field_legal_name->value,
        'field_legal_name_boolean' => $journal_poem->field_legal_name_boolean->value,
        'uid' => $uid,
      ]);
      $futureverse_application->field_journal_year = $journal_year->id();
    }

    $front = Url::fromRoute('<front>');
    return $this->entityFormBuilder()->getForm($futureverse_application, 'edit', [
      'redirect' => $front,
    ]);
  }

}
