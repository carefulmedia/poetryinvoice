<?php

namespace Drupal\piv_contest_competition_entry;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\piv_contest_competition_entry\Service\CompetitionLockService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access control handler for the competition entry entity type.
 */
class CompetitionEntryAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * @var CompetitionLockService $competitionLockService
   */
  private $competitionLockService;

  public function __construct(
    EntityTypeInterface $entity_type,
    CompetitionLockService $competitionLockService
  ) {
    parent::__construct($entity_type);
    $this->competitionLockService = $competitionLockService;
  }

  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
     return new static(
        $entity_type,
        $container->get('piv_contest_competition_entry.competition_lock_service')
     );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view competition entry');

      case 'update':
        $is_locked = $this->competitionLockService->isLocked($entity);
        if ($is_locked) {
          $is_allowed_result = AccessResult::allowedIfHasPermission($account, 'edit locked competition');
          if ($is_allowed_result->isAllowed()) {
            \Drupal::messenger()->addWarning('This competition entry is locked. Only edit it if you know what you are doing.');
          }

          return $is_allowed_result;
        }
        return AccessResult::allowedIfHasPermissions($account, ['edit competition entry', 'administer competition entry'], 'OR');

      case 'delete':
        $is_locked = $this->competitionLockService->isLocked($entity);
        if ($is_locked) {
          $is_allowed_result = AccessResult::allowedIfHasPermission($account, 'edit locked competition');
          if ($is_allowed_result->isAllowed()) {
            \Drupal::messenger()->addWarning('This competition entry is locked. Only edit it if you know what you are doing.');
          }
          return $is_allowed_result;
        }

        $permissions = ['delete competition entry', 'administer competition entry'];
        if ($account->id() === $entity->getOwnerId()) {
          $permissions[] = 'delete own competition entry';
        }

        return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
      default:
        // No opinion.
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, ['create competition entry', 'administer competition entry'], 'OR');
  }

}
