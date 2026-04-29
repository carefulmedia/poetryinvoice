<?php

declare(strict_types=1);

namespace Drupal\piv_logs;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the log entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class LogAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission($this->entityType->getAdminPermission())) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return match($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view piv_log'),
      'update' => AccessResult::allowedIfHasPermission($account, 'edit piv_log'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete piv_log'),
      'delete revision' => AccessResult::allowedIfHasPermission($account, 'delete piv_log revision'),
      'view all revisions', 'view revision' => AccessResult::allowedIfHasPermissions($account, ['view piv_log revision', 'view piv_log']),
      'revert' => AccessResult::allowedIfHasPermissions($account, ['revert piv_log revision', 'edit piv_log']),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    // Deny access to create logs, these are created programmatically
    // and can be edited.
    return AccessResult::forbidden();
  }

}
