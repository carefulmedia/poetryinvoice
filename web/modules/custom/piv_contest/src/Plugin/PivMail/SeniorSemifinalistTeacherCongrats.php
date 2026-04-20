<?php

namespace Drupal\piv_contest\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "senior_semifinalist_teacher_congrats",
 *   type = "competition",
 *   label = @Translation("Senior Semifinals - Teacher Congrats"),
 *   description = "",
 *   sources = {"node", "user"}
 * )
 */
class SeniorSemifinalistTeacherCongrats extends PivMailPluginBase {}
