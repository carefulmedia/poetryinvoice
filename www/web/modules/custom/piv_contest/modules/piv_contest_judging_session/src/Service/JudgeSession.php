<?php

namespace Drupal\piv_contest_judging_session\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\KeyValueStore\KeyValueFactory;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\NodeInterface;
use Drupal\piv_contest_judging_session\Entity\JudgingSession;
use Drupal\user\Entity\User;
use Drupal\piv_contest_competition_entry\Entity\CompetitionEntry;
use Drupal\piv_contest_recitation\Entity\Recitation;

/**
 * Judge Session service.
 *
 * @package Drupal\piv_contest_judging_session\Service
 */
class JudgeSession {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $db;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * Key value to store the session user is voting on.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  private $keyValueVote;

  /**
   * Constructor.
   */
  public function __construct(Connection $db, EntityTypeManager $entityTypeManager, KeyValueFactory $key_value) {
    $this->db = $db;
    $this->entityTypeManager = $entityTypeManager;
    $this->keyValueVote = $key_value->get('session_being_judged');
  }

  /**
   * Return a list of poems to be read in the session.
   */
  public function poemList(JudgingSession $session, User $judge): array {
    $list = [];

    foreach ($session->field_competition_entries->referencedEntities() as $competition_entry) {
      $recitations = $competition_entry->field_recitations->referencedEntities();
      foreach ($recitations as $recitation) {
        /** @var \Drupal\node\NodeInterface $poem */
        $poem = $recitation->field_poem->entity;
        $list[$poem->id()] = $poem;
      }
    }

    return $list;
  }

  /**
   * Check if a competition entry was already scored for accuracy.
   */
  public function entryWasScoredForAccuracy(CompetitionEntry $competition_entry) {
    foreach ($competition_entry->field_recitations->referencedEntities() as $recitation) {
      if ($recitation->field_score->isEmpty()) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Get the recitation score for a session.
   */
  public function getRecitationScore(Recitation $recitation, JudgingSession $judging_session) : int {
    $score_storage = $this->entityTypeManager->getStorage('score');
    $scores = $score_storage->loadByProperties([
      'judging_session' => $judging_session->id(),
      'recitation' => $recitation->id(),
    ]);
    $total = 0;
    foreach ($scores as $score) {
      $values = array_column($score->field_scores->getValue(), 'value');
      $total += array_sum($values);
    }
    return $total;
  }

  /**
   * Order the recitations in a session.
   */
  public function orderRecitationsList(JudgingSession $session, User $judge) : array {
    $languages = [];
    $judge_id = $judge->id();
    $french_judges = array_column($session->field_french_judge->getValue(), 'target_id');
    $english_judges = array_column($session->field_english_judge->getValue(), 'target_id');
    if (in_array($judge_id, $french_judges)) {
      $languages[] = 'fr';
    }
    if (in_array($judge_id, $english_judges)) {
      $languages[] = 'en';
    }

    static $list = [];
    $key = $session->id() . ':' . $judge->id();
    if (empty($list[$key])) {
      $competition_entries_ids = array_column($session->field_competition_entries->getValue(), 'target_id');
      if (!$competition_entries_ids) {
        return [];
      }

      $score_storage = $this->entityTypeManager->getStorage('score');
      foreach ($session->field_competition_entries->referencedEntities() as $competition_entry) {
        $recitations = $competition_entry->field_recitations->referencedEntities();
        $total_recitations = count($recitations);
        foreach ($recitations as $delta => $recitation) {
          // Only consider the languages this judge is assigned to.
          if (!in_array($recitation->langcode->value, $languages)) {
            continue;
          }
          $score = $score_storage->loadByProperties([
            'judge' => $judge->id(),
            'judging_session' => $session->id(),
            'recitation' => $recitation->id(),
          ]);
          $score = reset($score);
          $list[$key][$delta][] = [
            'recitation' => $recitation,
            'score' => $score,
            'last_of_round' => ($total_recitations == ($delta + 1)),
          ];
        }
      }
    }

    // Flatten the array.
    return array_merge(...$list[$key]);
  }

  /**
   * Total number of recitations.
   */
  public function totalNumberOfRecitations(JudgingSession $session, User $judge): int {
    return count($this->orderRecitationsList($session, $judge));
  }

  /**
   * Total number of poems.
   */
  public function totalNumberOfPoems(JudgingSession $session, User $judge): int {
    return count($this->poemList($session, $judge));
  }

  /**
   * Number of recitation evaluated by judge.
   */
  public function numberOfRecitationsEvaluatedByJudge(JudgingSession $session, User $judge): int {
    $list = $this->orderRecitationsList($session, $judge);
    return count(array_filter($list, fn ($item) => !empty($item['score'])));
  }

  /**
   * Next recitation to evaluate.
   */
  public function nextRecitation(JudgingSession $session, User $judge) {
    $list = $this->orderRecitationsList($session, $judge);
    foreach ($list as $recitation) {
      if (empty($recitation['score'])) {
        return $recitation;
      }
    }
    return FALSE;
  }

  /**
   * Get next poem to read.
   */
  public function nextPoemToRead(JudgingSession $session, User $judge) {
    $list = $this->poemList($session, $judge);
    $read_poems = $this->getReadPoemsByJudge($session, $judge);
    $poem = NULL;
    foreach ($list as $poem) {
      if (!isset($read_poems[$poem->id()])) {
        return $poem;
      }
    }

    return $poem;
  }

  /**
   * Return if session was evaluated by a judge.
   */
  public function isSessionEvaluatedByJudge(JudgingSession $session, User $judge): bool {
    return $this->nextRecitation($session, $judge) === FALSE;
  }

  /**
   * Check if user can judge the session. It should allow only 1 at the time.
   */
  public function canJudgeStartJudgingSession(JudgingSession $session, User $judge): bool {
    $session_being_judged = $this->keyValueVote->get("current_session_being_judged_{$judge->id()}");
    if (!$session_being_judged) {
      return TRUE;
    }

    return $session_being_judged === $session->id();
  }

  /**
   * Mark a session to being judged.
   */
  public function startJudgingSession(JudgingSession $session, User $judge) {
    $this->keyValueVote->set("current_session_being_judged_{$judge->id()}", $session->id());
  }

  /**
   * Remove session from being judged by a given judge.
   */
  public function removeSessionBeingJudged(User $judge) {
    $this->keyValueVote->delete("current_session_being_judged_{$judge->id()}");
  }

  /**
   * Get number of poems read by a judge.
   */
  public function numberOfPoemsReadByJudge(JudgingSession $session, User $judge): int {
    $query = $this->db->query("SELECT count(*) FROM {judging_session_poems_read} where session_id = :session and judge_id = :judge", [
      ':session' => $session->id(),
      ':judge' => $judge->id(),
    ]);

    $result = $query->fetchAll(\PDO::FETCH_COLUMN);
    if ($result) {
      return (int) $result[0];
    }

    return 0;
  }

  /**
   * Get recitation that have been read by a judge.
   */
  private function getReadPoemsByJudge(JudgingSession $session, User $judge): array {
    $query = $this->db->query("SELECT poem_id FROM {judging_session_poems_read} where session_id = :session and judge_id = :judge", [
      ':session' => $session->id(),
      ':judge' => $judge->id(),
    ]);

    return array_flip($query->fetchAll(\PDO::FETCH_COLUMN));
  }

  /**
   * Mark poem as read.
   */
  public function markPoemAsRead(JudgingSession $session, User $judge, NodeInterface $poem) {
    $now = new DrupalDateTime();
    $this->db->insert('judging_session_poems_read')
      ->fields([
        'session_id' => $session->id(),
        'judge_id' => $judge->id(),
        'poem_id' => $poem->id(),
        'created_at' => $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ])
      ->execute();
  }

}
