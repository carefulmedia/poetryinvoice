<?php

namespace Drupal\piv_mail\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "booked_visit_change_notifications",
 *   type = "default",
 *   label = @Translation("Booked visit change notifications"),
 *   description = @Translation("Send an email to selected recipients when there are changes on a visit and the field booked was already checked."),
 *   sources = {"node", "user", "visit_node", "teacher", "school"}
 * )
 */
class BookedVisitChangeNotifications extends PivMailPluginBase {}
