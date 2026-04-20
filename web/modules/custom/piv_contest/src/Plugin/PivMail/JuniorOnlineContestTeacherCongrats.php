<?php

namespace Drupal\piv_contest\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "junior_online_contest_teacher_congrats",
 *   type = "competition",
 *   label = @Translation("JOC - Teacher Congrats"),
 *   description = "",
 *   sources = {"node", "user"}
 * )
 */
class JuniorOnlineContestTeacherCongrats extends PivMailPluginBase {}
