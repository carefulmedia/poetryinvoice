<?php

namespace Drupal\piv_futureverse_vote;

/**
 * Interface for managing futureverse votes.
 */
interface PivFutureverseVoteManagerInterface {

  /**
   * Check if there is an existing vote for the given email, year and language.
   *
   * @param string $voter_email
   *   The voter's email address.
   * @param string|int $year
   *   The competition year.
   * @param string $langcode
   *   The language code.
   *
   * @return bool
   *   Returns TRUE if an existing vote is found, FALSE otherwise.
   *
   * @throws \Exception
   */
  public function hasVoted(string $voter_email, string|int $year, string $langcode): bool;

  /**
   * Create a new vote.
   *
   * @param string $voter_email
   *   The voter's email address.
   * @param string $voter_name
   *   The voter's name.
   * @param string|int $journal_poem_id
   *   The journal poem node ID.
   * @param string|int $year
   *   The competition year.
   * @param string $langcode
   *   The language code.
   *
   * @return int|string|null
   *   The merge query result.
   *
   * @throws \Exception
   */
  public function vote(
    string $voter_email,
    string $voter_name,
    string|int $journal_poem_id,
    string|int $year,
    string $langcode,
  ): int|string|null;

  /**
   * Delete vote(s) by ID.
   *
   * @param array $ids
   *   An array of vote IDs to delete.
   *
   * @return int|string|null
   *   The number of rows deleted.
   *
   * @throws \Exception
   */
  public function delete(array $ids): int|string|null;

}
