<?php

namespace Drupal\piv_mail\Plugin\PivMail;

/**
 * Plugin implementation of the piv_mail.
 *
 * @PivMail(
 *   id = "node_created_writing_prompt",
 *   label = @Translation("Node created: Writing Prompt"),
 *   description = @Translation("Send an email to admin email when Writing Prompt node is created."),
 *   sources = {"node", "user"},
 * )
 */
class NodeCreatedWritingPrompt extends NodeCreatedBase {}
