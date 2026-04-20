<?php

namespace Drupal\piv_mail\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "one_week_before_visit_email_reminder",
 *   type = "default",
 *   label = @Translation("One week before visit e-mail reminder"),
 *   description = @Translation("E-mail the teachers and the poet one week before the school visit."),
 *   sources = {"node", "user", "visit_node", "teacher", "school"}
 * )
 */
class OneWeekBeforeVisitEmailReminder extends PivMailPluginBase {}
