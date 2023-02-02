<?php

namespace Drupal\piv_contest;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\piv_contest_competition\CompetitionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\piv_contest_competition\Entity\Competition;
use Drupal\piv_contest_competition_entry\Entity\CompetitionEntry;
use Drupal\piv_contest_competition_entry\Service\CompetitionLockService;
use Drupal\user\UserInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * @file
 * Service allowing management of competition enrollment for a school.
 */

/**
 * Competition enrollment helper class.
 */
class CompetitionEnrollment {

  use StringTranslationTrait;

  /**
   * Check if instance is initiated.
   *
   * @var bool
   */
  private $isInitiated = FALSE;

  /**
   * School node.
   *
   * @var \Drupal\node\NodeInterface
   */
  private $school;

  /**
   * Competition entity.
   *
   * @var Drupal\piv_contest_competition\CompetitionInterface
   */
  private $competition;

  /**
   * Competition entry entities.
   *
   * @var Drupal\piv_contest_competition_entry\Entity\CompetitionEntry[]
   */
  private $entries = [];

  /**
   * User object.
   *
   * @var Drupal\user\UserInterface
   */
  private $teacher;

  /**
   * The lock service.
   *
   * @var \Drupal\piv_contest_competition_entry\Service\CompetitionLockService
   */
  private $lockService;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CompetitionLockService $lockService) {
    $this->entityTypeManager = $entity_type_manager;
    $this->lockService = $lockService;
  }

  /**
   * Init values for class.
   */
  public function init(NodeInterface $school, CompetitionInterface $competition, UserInterface $teacher) {
    $this->school = $school;
    $this->competition = $competition;
    $this->teacher = $teacher;
    $this->isInitiated = TRUE;
  }

  /**
   * Get enrollment progress for each competition stream.
   *
   * Return the value in an array that is suitable to generate a html table.
   */
  public function getStreams() {
    if ($this->competition->field_team_competition->value) {
      return $this->getStreamsTeam();
    }
    return $this->getStreamsIndividual();
  }

  /**
   * Individual competition.
   */
  private function getStreamsIndividual() {
    // Headers row.
    $streams_header = [
      'name' => $this->t('Stream'),
      'student' => $this->t('Student'),
      'recitations' => $this->t('Recitations'),
      'permission' => $this->t('Permission'),
      'criteria' => $this->t('Criteria'),
    ];

    $school_id = $this->school->id();
    $teacher_id = $this->teacher->id();
    $competition_id = $this->competition->id();

    // Set redirection after form submit.
    $destination = Url::fromRoute('piv_contest.competition', [
      'user' => $teacher_id,
      'competition' => $competition_id,
    ])->toString();
    $streams = [];
    foreach ($this->competition->field_competition_streams->referencedEntities() as $stream_entity) {
      $stream = [];

      // Get the competition entry for this stream.
      $entry = $this->entityTypeManager
        ->getStorage('competition_entry')
        ->loadByProperties([
          'field_school' => $school_id,
          'field_competition' => $competition_id,
          'field_stream' => $stream_entity->id(),
        ]);
      $entry = array_pop($entry);
      if (!empty($entry)) {
        $editUrl = Url::fromRoute('piv_contest.competition_entry_edit', [
          'user' => $teacher_id,
          'competition' => $competition_id,
          'competition_entry' => $entry->id(),
        ]);

        $deleteUrl = Url::fromRoute('entity.competition_entry.delete_form', [
          'competition_entry' => $entry->id(),
        ], [
          'query' => [
            'destination' => $destination,
          ],
        ]);

        $stream['student'] = piv_contest_get_student_name($entry);
        $stream['recitations'] = $this->t("@current out of @required", [
          '@current' => $this->getNumberOfValidRecitationsInEntry($entry),
          '@required' => $this->getRequiredRecitationsForStream($stream_entity),
        ]);
        $stream['permission'] = TRUE;
        $stream['is_recitations_completed'] = $this->isRecitationsCompleted($stream_entity, $entry);
        $stream['is_permissions_completed'] = $entry->field_release_form->entity != NULL;
        $stream['is_completed'] = (bool) $entry->field_complete->value;
        $stream['is_missing_criteria_completed'] = FALSE;

        $all_criteria = [];
        $missing_criteria = $this->getMissingCriteria($entry, $all_criteria);
        $stream['criteria'] = [];
        foreach ($all_criteria as $criteria) {
          $stream['criteria'][] = [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $criteria,
            '#attributes' => [
              'class' => in_array($criteria, $missing_criteria)
                ? ['criteria-is-missing']
                : ['criteria-is-met'],
            ],
          ];
        }

        if (empty($missing_criteria)) {
          $stream['is_missing_criteria_completed'] = TRUE;
        }

        $editTitle = $this->t('edit');
        if ($this->lockService->isLocked($entry)) {
          $editTitle = $this->t('view');
        }
        if ($editUrl->access()) {
          // Add link to edit existing entry.
          $stream['links'][] = [
            '#type' => 'link',
            '#title' => $editTitle,
            '#attributes' => [
              'class' => ['button'],
            ],
            '#url' => $editUrl ,
            '#cache' => [
              'tags' => $entry->getCacheTags(),
            ],
          ];
        }
        if ($deleteUrl->access()) {
          // Add link to delete existing entry.
          $stream['links'][] = [
            '#type' => 'link',
            '#title' => $this->t('delete'),
            '#attributes' => [
              'class' => ['button'],
            ],
            '#url' => $deleteUrl,
            '#cache' => [
              'tags' => $entry->getCacheTags(),
            ],
          ];
        }
      }

      // No competition entry for this stream.
      else {
        // Set a default value for each column.
        foreach ($streams_header as $k => $v) {
          $stream[$k] = NULL;
        }

        $addUrl = Url::fromRoute('piv_contest.competition_entry_add', [
          'user' => $this->teacher->id(),
          'competition' => $this->competition->id(),
          'stream' => $stream_entity->id(),
        ]);

        if ($addUrl->access()) {
          // Add link to create new entry.
          $stream['links'][] = [
            '#type' => 'link',
            '#title' => $this->t('Add'),
            '#attributes' => [
              'class' => ['button'],
            ],
            '#url' => $addUrl,
          ];
        }
      }

      $stream['name'] = $stream_entity->field_label->value;
      $streams[] = $stream;
    }

    // Prepend the table headers.
    array_unshift($streams_header, $streams);
    return $streams;
  }

  /**
   * Check if competition entry have all recitations required.
   */
  public function isRecitationsCompleted(EntityInterface $stream, EntityInterface $competitionEntry): bool {
    if ($this->getNumberOfValidRecitationsInEntry($competitionEntry) >= $this->getRequiredRecitationsForStream($stream)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get the number of valid recitations in a competition entry.
   */
  public function getNumberOfValidRecitationsInEntry(EntityInterface $competitionEntry): int {
    $competition = $competitionEntry->field_competition->entity;
    $is_online = (bool) $competition->field_online_competition->value;
    $number_of_valid_poems = 0;
    foreach ($competitionEntry->field_recitations->referencedEntities() as $entity) {

      $video = $entity->field_recitation_video->entity;
      $embed_code = $video->get('field_media_oembed_video')->value;

      // IF it's an online competition recitation must also contain a video.
      if ($is_online && is_null($embed_code)) {
        continue;
      }
      if ($poem = $entity->field_poem->entity) {
        $number_of_valid_poems++;
      }
    }

    return $number_of_valid_poems;
  }

  /**
   * Get the min required recitations for a stream.
   */
  public function getRequiredRecitationsForStream(EntityInterface $stream): int {
    return count($stream->field_stream_languages) * (int) $stream->field_min_recitations->value;
  }

  /**
   * Check if competition entry is complete.
   */
  public function isCompetitionEntryCompleted(CompetitionEntry $entity): bool {
    $stream = $entity->getStream();
    if (!$stream) {
      return FALSE;
    }

    $missing_criterias = $this->getMissingCriteria($entity);
    if (count($missing_criterias) > 0) {
      return FALSE;
    }

    $number_valid_recitations = $this->getNumberOfValidRecitationsInEntry($entity);
    $required_recitations_stream = $this->getRequiredRecitationsForStream($stream);
    if ($number_valid_recitations >= $required_recitations_stream && $entity->field_release_form->entity != NULL) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Check if a competition have the required criteria.
   */
  public function competitionHasRequiredCriteria(Competition $competition): bool {
    $required_criteria_list = $competition->field_criteria;
    if (count($required_criteria_list) === 0) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Get the missing criteria for a competition entry.
   *
   * Second argument will be populated by reference with all the required
   * criteria if a variable is provided.
   */
  public function getMissingCriteria(CompetitionEntry $entity, array &$all_criteria = []): array {
    $competition = $entity->field_competition->entity;
    if (!$competition) {
      return [];
    }

    $required_criteria_list = $competition->field_criteria;
    if (count($required_criteria_list) === 0) {
      return [];
    }

    $required_criteria = [];
    foreach ($required_criteria_list as $item) {
      $criteria = $item->entity;
      $required_criteria[$criteria->id()] = $criteria->label();
    }
    $all_criteria = $required_criteria;

    foreach ($entity->field_recitations as $recitation_item) {
      $recitation = $recitation_item->entity;
      if (!$recitation) {
        continue;
      }

      $poem = $recitation->field_poem->entity;
      if (!$poem) {
        continue;
      }

      foreach ($poem->field_poem_thems as $poem_item) {
        $criteria = $poem_item->entity;
        if (!$criteria) {
          continue;
        }

        // If we match the criteria remove from the requirements.
        if (isset($required_criteria[$criteria->id()])) {
          unset($required_criteria[$criteria->id()]);
        }
      }
    }

    return $required_criteria;
  }

  /**
   * Team competition.
   */
  private function getStreamsTeam() {
    // Team streams are not implemented yet.
    return $this->getStreamsIndividual();
    /*foreach ($this->competition->field_competition_streams as $stream_field) {
    // Student name is per recitation for team competitions, not per entry.
    }*/
  }

}
