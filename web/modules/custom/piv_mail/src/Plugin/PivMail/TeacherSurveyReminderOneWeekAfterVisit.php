<?php

namespace Drupal\piv_mail\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "teacher_survey_reminder_one_week_after_visit",
 *   label = @Translation("Teacher survey reminder 1 week after visit"),
 *   description = @Translation("E-mail the teacher one week after the school visit."),
 *   sources = {"node", "user", "visit_node", "teacher", "school"}
 * )
 */
class TeacherSurveyReminderOneWeekAfterVisit extends PivMailPluginBase {}
