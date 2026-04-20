<?php

namespace Drupal\piv_mail\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "teacher_survey_reminder_one_day_after_visit",
 *   type = "default",
 *   label = @Translation("Teacher survey reminder 1 day after visit"),
 *   description = @Translation("E-mail the teacher one day after the school visit."),
 *   sources = {"node", "user", "visit_node", "teacher", "school"}
 * )
 */
class TeacherSurveyReminderOneDayAfterVisit extends PivMailPluginBase {}
