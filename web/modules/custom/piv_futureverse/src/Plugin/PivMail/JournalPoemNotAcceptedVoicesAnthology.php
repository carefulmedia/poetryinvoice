<?php

namespace Drupal\piv_futureverse\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "journal_poem_not_accepted_voices_anthology",
 *   type = "default",
 *   label = @Translation("Journal Poem: Not Accepted Voices Anthology"),
 *   description = @Translation("Send an email to users not accepted for Voices Anthology."),
 *   sources = {"user", "journal_poem"}
 * )
 */
class JournalPoemNotAcceptedVoicesAnthology extends PivMailPluginBase {}
