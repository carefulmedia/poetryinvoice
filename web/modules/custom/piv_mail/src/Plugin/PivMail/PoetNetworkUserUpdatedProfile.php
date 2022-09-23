<?php

namespace Drupal\piv_mail\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "poet_network_user_updated_profile",
 *   label = @Translation("Poet network user updated profile"),
 *   description = @Translation("Send an email regarding a poet network user that updated their own profile."),
 *   sources = {"user", "user_diff"}
 * )
 */
class PoetNetworkUserUpdatedProfile extends PivMailPluginBase {}
