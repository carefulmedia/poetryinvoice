<?php

namespace Drupal\piv_futureverse\Plugin\PivMail;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "journal_poem_monthly_prize_winner",
 *   type = "default",
 *   label = @Translation("Journal Poem: Monthly Prize Winner"),
 *   description = @Translation("Send an email to the monthly winner for futureverse+."),
 *   sources = {"user", "journal_poem"}
 * )
 */
class JournalPoemMonthlyPrizeWinner extends JournalPoemAcceptedVoicesAnthology {}
