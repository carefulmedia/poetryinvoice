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
        $permissions = ['edit recitation', 'administer recitation'];

        if ($entity->get('uid')->target_id === $account->id()) {
          $permissions[] = 'edit own recitation';
        }

        return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');

      case 'delete':
        $permissions =  ['delete recitation', 'administer recitation'];

        if ($entity->get('uid')->target_id === $account->id()) {
          $permissions[] = 'delete own recitation';
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
    return AccessResult::allowedIfHasPermissions($account, ['create recitation', 'administer recitation'], 'OR');
  }

}
