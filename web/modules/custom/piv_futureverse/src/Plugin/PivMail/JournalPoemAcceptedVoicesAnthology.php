<?php

namespace Drupal\piv_futureverse\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "journal_poem_accepted_voices_anthology",
 *   label = @Translation("Journal Poem: Accepted Voices Anthology"),
 *   description = @Translation("Send an email to users accepted for Voices Anthology."),
 *   sources = {"user", "journal_poem"}
 * )
 */
class JournalPoemAcceptedVoicesAnthology extends PivMailPluginBase {}
