<?php

namespace Drupal\piv_contest\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "senior_finalist_teacher_sorry",
 *   type = "competition",
 *   label = @Translation("Senior Finals - Teacher Sorry"),
 *   description = "",
 *   sources = {"competition_entry", "paragraph_rank"}
 * )
 */
class SeniorFinalistTeacherSorry extends PivMailPluginBase {}
