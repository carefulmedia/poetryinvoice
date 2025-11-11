<?php

namespace Drupal\piv_futureverse\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "journal_poem_futureverse_shortlisted",
 *   label = @Translation("Journal Poem: Futureverse shortlisted notification"),
 *   description = @Translation("Send an email to all poems in a year shortlisted for futureverse."),
 *   sources = {"user", "journal_poem"}
 * )
 */
class JournalPoemFutureverseShortlisted extends PivMailPluginBase {}
