<?php

namespace Drupal\piv_contest_judging_session\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\piv_contest_competition_entry\Entity\CompetitionEntry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Score Template constraint.
 */
class UniqueJudgingSessionPerCompetitionEntryValidator extends ConstraintValidator implements ContainerInjectionInterface {

  private $judgingSessionStorage;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->judgingSessionStorage = $entity_type_manager->getStorage('judging_session');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    $bundle = $entity->bundle();

    if ($bundle !== 'default') {
      return;
    }

    $competition_entries = [];
    foreach ($entity->field_competition_entries->referencedEntities() as $delta => $entry) {
      $competition_entries[$delta] = $entry->id();
    }

    $entries_in_use = $this->getEntriesAlreadyInUseFromIDs($competition_entries);

    foreach ($competition_entries as $delta => $entry) {
      if (isset($entries_in_use[$entry])) {
        $session = $entries_in_use[$entry];

        // this means it's added to the current session, we do not fail on that case.
        if ($session->id() === $entity->id()) {
          continue;
        }

        $this->context->buildViolation($constraint->message)
          ->setParameter('@judging_session_link', $session->toLink()->getUrl()->toString())
          ->setParameter('@judging_session_text', $session->toLink()->getText())
          ->atPath("field_competition_entries.$delta")
          ->addViolation();
      }

    }
  }

  /**
   * @param array $ids
   *
   * @return CompetitionEntry[]
   */
  public function getEntriesAlreadyInUseFromIDs(array $ids): array {
    if (empty($ids)) {
      return [];
    }

    $ids = $this->judgingSessionStorage->getQuery()
      ->condition('field_competition_entries', $ids, 'IN')
      ->execute();

    if (!$ids) {
      return [];
    }

    $result = [];

    $sessions = $this->judgingSessionStorage->loadMultiple($ids);
    foreach ($sessions as $session) {
      foreach ($session->field_competition_entries as $entry) {
        if (!isset($result[$entry->target_id])) {
          $result[$entry->target_id] = $session;
        }
      }
    }

    return $result;
  }

}
