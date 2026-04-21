<?php

namespace Drupal\piv_contest\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "senior_semifinalist_teacher_sorry",
 *   type = "competition",
 *   label = @Translation("Senior Semifinals - Teacher Sorry"),
 *   description = "",
 *   sources = {"competition_entry", "paragraph_rank"}
 * )
 */
class SeniorSemifinalistTeacherSorry extends PivMailPluginBase {}
