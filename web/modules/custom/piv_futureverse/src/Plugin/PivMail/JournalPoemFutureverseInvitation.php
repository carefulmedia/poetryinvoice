<?php

namespace Drupal\piv_futureverse\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "journal_poem_futureverse_invitation",
 *   label = @Translation("Journal Poem: Futureverse invitation"),
 *   description = @Translation("Send an email to all poems in a year with acceptance level of Yes or Monthly prize winner."),
 *   sources = {"user", "journal_poem"}
 * )
 */
class JournalPoemFutureverseInvitation extends PivMailPluginBase {}
