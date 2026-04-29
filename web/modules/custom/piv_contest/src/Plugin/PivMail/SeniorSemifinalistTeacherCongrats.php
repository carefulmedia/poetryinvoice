<?php

namespace Drupal\piv_contest\Plugin\PivMail;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "senior_semifinalist_teacher_congrats",
 *   type = "competition",
 *   label = @Translation("Senior Semifinals - Teacher Congrats"),
 *   description = "",
 *   sources = {"competition_entry", "paragraph_rank"}
 * )
 */
class SeniorSemifinalistTeacherCongrats extends ContestBase {}
