<?php

namespace Drupal\piv_futureverse\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "journal_poem_bio_created",
 *   label = @Translation("Journal Poem: Bio Created"),
 *   description = @Translation("Send an email when a poet bio is created."),
 *   sources = {"user", "poet_bio"}
 * )
 */
class JournalPoemBioCreated extends PivMailPluginBase {}
