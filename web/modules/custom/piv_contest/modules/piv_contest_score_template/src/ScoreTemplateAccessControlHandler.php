<?php

namespace Drupal\piv_contest_score_template;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityHandlerInterface;

/**
 * Defines the access control handler for the score template entity type.
 */
class ScoreTemplateAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The entity type manager service.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static($entity_type, $container
      ->get('entity_type.manager'));
  }

  /**
   * Constructs the block access control handler instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view score template');

      case 'update':
        return AccessResult::allowedIfHasPermissions($account, [
          'edit score template',
          'administer score template',
        ], 'OR');

      case 'delete':
        // Prevent deleting templates if there are scores using it.
        $query = $this->entityTypeManager->getStorage('score')->getQuery();
        $results = $query->condition('score_template', $entity->id())->execute();
        if ($results) {
          return AccessResult::forbidden('There is a score using this score template');
        }
        return AccessResult::allowedIfHasPermissions($account, [
          'delete score template',
          'administer score template',
        ], 'OR');

      default:
        // No opinion.
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'create score template',
      'administer score template',
    ], 'OR');
  }

}
