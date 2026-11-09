<?php

namespace Drupal\piv_futureverse\Plugin\PivMail;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "journal_poem_futureverse_invitation",
 *   type = "default",
 *   label = @Translation("Journal Poem: Futureverse invitation"),
 *   description = @Translation("Send an email to eligible journal poems (acceptance Yes or Monthly prize winner, grades 10-12) without a FutureVerse application."),
 *   sources = {"user", "journal_poem"}
 * )
 */
class JournalPoemFutureverseInvitation extends JournalPoemAcceptedVoicesAnthology {}
