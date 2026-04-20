<?php

namespace Drupal\piv_mail\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "notification_booked_status_applied_to_visit",
 *   type = "default",
 *   label = @Translation("Notification booked status applied to visit"),
 *   description = @Translation("Send an email to selected recipients when visit node is checked as booked."),
 *   sources = {"node", "user", "school", "teacher", "visit_node"}
 * )
 */
class NotificationBookedStatusAppliedToVisit extends PivMailPluginBase {}
