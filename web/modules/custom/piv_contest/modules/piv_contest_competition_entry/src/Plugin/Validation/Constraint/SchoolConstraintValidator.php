<?php

namespace Drupal\piv_contest_competition_entry\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Validates the School constraint.
 */
class SchoolConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Creates a new TaxonomyTermHierarchyConstraintValidator instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
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
  public function validate($items, Constraint $constraint): void {
    $entity = $items->getEntity();
    $entity_id = $entity->id();
    $field_stream = $entity->field_stream->target_id;
    foreach ($items as $delta => $item) {
      if (empty($item->target_id)) {
        continue;
      }

      $query = $this->entityTypeManager
        ->getStorage('competition_entry')
        ->getQuery()
        ->accessCheck(FALSE);
      $results = $query->condition('field_school', $item->target_id)
        ->condition('field_stream', $field_stream)
        ->accessCheck(FALSE)
        ->execute();
      // Remove self from results.
      if ($entity_id) {
        unset($results[$entity_id]);
      }
      if ($results) {
        $this->context->buildViolation($constraint->errorMessage)
          ->atPath($delta)
          ->setParameter('@ids', implode(', ', $results))
          ->addViolation();
      }
    }
  }

}
