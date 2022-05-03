<?php

namespace Drupal\piv_mail\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "one_day_before_visit_email_reminder",
 *   label = @Translation("One day before visit e-mail reminder"),
 *   description = @Translation("E-mail the teachers and the poet one day before the school visit."),
 *   sources = {"node", "user", "visit_node", "teacher", "school"}
 * )
 */
class OneDayBeforeVisitEmailReminder extends PivMailPluginBase {}
