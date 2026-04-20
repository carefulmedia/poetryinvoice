<?php

namespace Drupal\piv_mail\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "node_created_poet_school_visit_teacher",
 *   type = "default",
 *   label = @Translation("Node created: Poet School Visit (Teachers notification)"),
 *   description = @Translation("E-mail the teachers when a school visit is created."),
 *   sources = {"node", "user", "school", "teacher", "visit_node"}
 * )
 */
class NodeCreatedPoetSchoolVisitTeacher extends PivMailPluginBase {}
