<?php

namespace Drupal\piv_contest_recitation;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the recitation entity type.
 */
class RecitationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view recitation');

      case 'update':
        return AccessResult::allowedIfHasPermissions($account, ['edit recitation', 'administer recitation'], 'OR');

      case 'delete':
        return AccessResult::allowedIfHasPermissions($account, ['delete recitation', 'administer recitation'], 'OR');

      default:
        // No opinion.
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, ['create recitation', 'administer recitation'], 'OR');
  }

}
