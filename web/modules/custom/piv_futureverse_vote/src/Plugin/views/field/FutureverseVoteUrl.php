<?php

namespace Drupal\piv_futureverse_vote\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Renders the vote URL for a journal poem.
 */
#[ViewsField("futureverse_vote_url")]
class FutureverseVoteUrl extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // No query alteration needed — entity is available via _entity.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $node = $values->_entity;
    if (!$node) {
      return [];
    }

    return Url::fromRoute('piv_futureverse_vote.vote', ['node' => $node->id()])
      ->toString();
  }

}
