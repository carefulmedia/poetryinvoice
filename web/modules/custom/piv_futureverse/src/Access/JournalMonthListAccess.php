<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control for Journal Month list.
 */
class JournalMonthListAccess {

  /**
   * Checks access for the journal month list page.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    // Only allow user ID 1 to view the list.
    if ($account->id() === '1') {
      return AccessResult::allowed()->cachePerUser();
    }
    
    return AccessResult::forbidden()->cachePerUser();
  }

}