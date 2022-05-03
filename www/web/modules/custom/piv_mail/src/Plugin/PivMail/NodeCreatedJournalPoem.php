<?php

namespace Drupal\piv_mail\Plugin\PivMail;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "node_created_journal_poem",
 *   label = @Translation("Node created: Journal Poem"),
 *   description = @Translation("Send an email to target email when Journal Poem node is created."),
 *   sources = {"node", "user"}
 * )
 */
class NodeCreatedJournalPoem extends NodeCreatedBase {}
