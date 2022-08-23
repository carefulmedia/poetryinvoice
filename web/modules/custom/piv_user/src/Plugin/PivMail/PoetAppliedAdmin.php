<?php

namespace Drupal\piv_user\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "poet_applied_admin",
 *   label = @Translation("A poet applied to the site (Admin notification)"),
 *   description = @Translation("Send email to admin about a poet that created a new user and is waiting for approval."),
 *   sources = {"user"}
 * )
 */
class PoetAppliedAdmin extends PivMailPluginBase {}
