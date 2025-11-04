<?php

namespace Drupal\piv_futureverse\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "journal_poem_monthly_prize_losers",
 *   label = @Translation("Journal Poem: Monthly Prize Losers"),
 *   description = @Translation("Send an email to the monthly losers for futureverse+."),
 *   sources = {"user", "journal_poem"}
 * )
 */
class JournalPoemMonthlyPrizeLosers extends PivMailPluginBase {}
