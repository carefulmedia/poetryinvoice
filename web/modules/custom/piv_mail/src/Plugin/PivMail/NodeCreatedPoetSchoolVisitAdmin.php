<?php

namespace Drupal\piv_mail\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "node_created_poet_school_visit_admin",
 *   type = "default",
 *   label = @Translation("Node created: Poet School Visit (Admin notification)"),
 *   description = @Translation("E-mail the admins when a school visit is created or fields were changed."),
 *   sources = {"node", "user", "school", "teacher", "visit_node"}
 * )
 */
class NodeCreatedPoetSchoolVisitAdmin extends PivMailPluginBase {}
