<?php

namespace Drupal\piv_popular_vote;

use Drupal\Core\Database\Connection;

/**
 * Manage votes for popular voting.
 */
class PivPopularVoteManager {

  const TABLE_NAME = 'piv_popular_vote';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a PivPopularVoteManager object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Create a vote.
   */
  public function vote($voter_email, $voter_name, $competition_entry_id, $competition_id, $langcode) {
    return $this->connection->merge(self::TABLE_NAME)
      ->insertFields([
        'created' => time(),
        'competition_entry_id' => $competition_entry_id,
        'voter_name' => $voter_name,
        'langcode' => $langcode,
      ])
      ->updateFields([
        'competition_entry_id' => $competition_entry_id,
        'voter_name' => $voter_name,
      ])
      ->key('voter_email', $voter_email)
      ->key('competition_id', $competition_id)
      ->execute();
  }

  /**
   * Delete a vote by id.
   */
  public function delete($ids) {
    return $this->connection->delete(self::TABLE_NAME)
      ->condition('id', (array) $ids, 'IN')
      ->execute();
  }

}
