<?php

namespace Drupal\piv_mail\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "team_regional_entry_created",
 *   type = "default",
 *   label = @Translation("Team Regional Entry Created"),
 *   description = @Translation("Send an email to the user that created the team regional entry."),
 *   sources = {"team_regional_entry"}
 * )
 */
class TeamRegionalEntryCreated extends PivMailPluginBase {}
