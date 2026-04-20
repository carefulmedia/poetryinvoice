<?php

namespace Drupal\piv_contest\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "senior_finalist_student_sorry",
 *   type = "competition",
 *   label = @Translation("Senior Finals - Student Sorry"),
 *   description = "",
 *   sources = {"node", "user"}
 * )
 */
class SeniorFinalistStudentSorry extends PivMailPluginBase {}
