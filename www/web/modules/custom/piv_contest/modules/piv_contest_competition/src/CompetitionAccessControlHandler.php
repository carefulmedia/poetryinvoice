<?php

namespace Drupal\piv_contest_competition;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the competition entity type.
 */
class CompetitionAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view competition');

      case 'update':
        return AccessResult::allowedIfHasPermissions($account, ['edit competition', 'administer competition'], 'OR');

      case 'delete':
        return AccessResult::allowedIfHasPermissions($account, ['delete competition', 'administer competition'], 'OR');

      default:
        // No opinion.
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, ['create competition', 'administer competition'], 'OR');
  }

}
