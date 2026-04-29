<?php

namespace Drupal\piv_contest\Plugin\PivMail;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "senior_semifinalist_student_congrats",
 *   type = "competition",
 *   label = @Translation("Senior Semifinals - Student Congrats"),
 *   description = "",
 *   sources = {"competition_entry", "paragraph_rank"}
 * )
 */
class SeniorSemifinalistStudentCongrats extends ContestBase {}
