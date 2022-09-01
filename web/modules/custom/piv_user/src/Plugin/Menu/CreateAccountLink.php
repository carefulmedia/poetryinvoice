<?php

namespace Drupal\piv_user\Plugin\Menu;

use Drupal\user\Plugin\Menu\LoginLogoutMenuLink;

/**
 * A menu link for /create-account that is only enabled for anonymous.
 */
class CreateAccountLink extends LoginLogoutMenuLink {

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Create new account');
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteName() {
    return 'piv_user.create_account';
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return $this->currentUser->isAnonymous();
  }

}
