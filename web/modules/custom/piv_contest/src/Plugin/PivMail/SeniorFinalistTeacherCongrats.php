<?php

namespace Drupal\piv_contest\Plugin\PivMail;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "senior_finalist_teacher_congrats",
 *   type = "competition",
 *   label = @Translation("Senior Finals - Teacher Congrats"),
 *   description = "",
 *   sources = {"competition_entry", "paragraph_rank"}
 * )
 */
class SeniorFinalistTeacherCongrats extends ContestBase {}
