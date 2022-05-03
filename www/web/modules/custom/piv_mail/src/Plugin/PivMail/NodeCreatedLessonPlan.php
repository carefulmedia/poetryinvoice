<?php

namespace Drupal\piv_mail\Plugin\PivMail;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "node_created_lesson_plan",
 *   label = @Translation("Node created: Lesson Plan"),
 *   description = @Translation("Send an email to admin email when Lesson Plan node is created."),
 *   sources = {"node", "user"}
 * )
 */
class NodeCreatedLessonPlan extends NodeCreatedBase {}
