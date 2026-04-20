<?php

namespace Drupal\piv_contest\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "senior_finalist_student_congrats",
 *   type = "competition",
 *   label = @Translation("Senior Finals - Student Congrats"),
 *   description = "",
 *   sources = {"node", "user"}
 * )
 */
class SeniorFinalistStudentCongrats extends PivMailPluginBase {}
