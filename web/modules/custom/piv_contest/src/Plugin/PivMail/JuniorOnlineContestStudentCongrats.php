<?php

namespace Drupal\piv_contest\Plugin\PivMail;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "junior_online_contest_student_congrats",
 *   type = "competition",
 *   label = @Translation("Junior - Student Congrats"),
 *   description = "",
 *   sources = {"competition_entry", "paragraph_rank"}
 * )
 */
class JuniorOnlineContestStudentCongrats extends ContestBase {}
