<?php

namespace Drupal\piv_mail\Plugin\PivMail;

use Drupal\piv_mail\PivMailPluginBase;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "node_created_journal_poem",
 *   label = @Translation("Node created: Journal Poem"),
 *   description = @Translation("Send an email to target email when Journal Poem node is created."),
 *   sources = {"journal_poem", "node", "user"}
 * )
 */
class NodeCreatedJournalPoem extends PivMailPluginBase {}
