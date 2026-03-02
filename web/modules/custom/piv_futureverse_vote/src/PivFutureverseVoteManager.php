<?php

namespace Drupal\piv_futureverse_vote;

use Drupal\Core\Database\Connection;

class PivFutureverseVoteManager implements PivFutureverseVoteManagerInterface {

  /**
   * The table name of our custom table where votes are stored.
   *
   * @var string
   */
  public const TABLE_NAME = 'piv_futureverse_vote';

  public function __construct(
    protected Connection $connection,
  ) {}

  /**
   * Check if there is an existing vote for the given email, year and language.
   *
   * @return bool
   *   Returns TRUE if an existing vote is found, FALSE otherwise.
   *
   * @throws \Exception
   */
  public function hasVoted(string $voter_email, string|int $year, string $langcode): bool {
    return (BOOL) $this->connection->select(self::TABLE_NAME, 't')
      ->condition('voter_email', $voter_email)
      ->condition('year', $year)
      ->condition('langcode', $langcode)
      ->fields('t', ['id'])
      ->execute()
      ->fetch();
  }

  /**
   * Create a new vote for a given email, name, 'journal_poem' node ID, year & langcode.
   *
   * @throws \Exception
   */
  public function vote(
    string $voter_email,
    string $voter_name,
    string|int $journal_poem_id,
    string|int $year,
    string $langcode,
  ): int|string|null{
    return $this->connection->merge(self::TABLE_NAME)
      ->insertFields([
        'created' => time(),
        'journal_poem_id' => $journal_poem_id,
        'voter_name' => $voter_name,
        'langcode' => $langcode,
        'year' => $year,
      ])
      ->updateFields([
        'journal_poem_id' => $journal_poem_id,
        'voter_name' => $voter_name,
      ])
      ->key('voter_email', $voter_email)
      ->key('year', $year)
      ->key('langcode', $langcode)
      ->execute();
  }

  /**
   * Delete vote(s) by ID.
   *
   * @throws \Exception
   */
  public function delete(array $ids): int|string|null {
    return $this->connection->delete(self::TABLE_NAME)
      ->condition('id', (array) $ids, 'IN')
      ->execute();
  }

}
