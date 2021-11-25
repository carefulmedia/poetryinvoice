<?php

namespace Drupal\piv_contest_competition_entry;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the competition entry entity type.
 */
class CompetitionEntryAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view competition entry');

      case 'update':
        return AccessResult::allowedIfHasPermissions($account, ['edit competition entry', 'administer competition entry'], 'OR');

      case 'delete':
        return AccessResult::allowedIfHasPermissions($account, ['delete competition entry', 'administer competition entry'], 'OR');

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
