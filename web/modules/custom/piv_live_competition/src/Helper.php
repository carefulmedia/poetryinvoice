<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * Helper class for Live Competitions ('competition' nodes).
 */
final class Helper {

  /**
   * Get judges in order, keyed by id.
   */
  public function getJudges(NodeInterface $node): array {
    // Performance judges are added to arrays keyed by language.
    $performance_judges = ['en' => [], 'fr' => []];
    foreach ($node->field_judges->referencedEntities() as $judge_paragraph) {
      if (!($judge = $judge_paragraph->field_judge->entity)) {
        continue;
      }
      $lang_id = $judge->preferred_langcode->value;
      $performance_judges[$lang_id][$judge->id()] = $judge;
    }

    // Return an array of entities keyed by id.
    $keyed = function ($entities) : array {
      $map = [];
      foreach ($entities as $entity) {
        $map[$entity->id()] = $entity;
      }
      return $map;
    };

    return [
      $performance_judges['en'],
      $performance_judges['fr'],
      $keyed($node->field_accuracy_judge_en->referencedEntities()),
      $keyed($node->field_accuracy_judge_fr->referencedEntities()),
    ];
  }

  /**
   * Get prompters in order, keyed by id.
   */
  public function getPrompters(NodeInterface $node): array {
    // Return an array of entities keyed by id.
    $keyed = function ($entities) : array {
      $map = [];
      foreach ($entities as $entity) {
        $map[$entity->id()] = $entity;
      }
      return $map;
    };

    return [
      $keyed($node->field_prompters_en->referencedEntities()),
      $keyed($node->field_prompters_fr->referencedEntities()),
    ];
  }

  /**
   * Constructs a Helper object.
   */
  public function __construct(
    private readonly CacheBackendInterface $cacheDefault,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Get the options from the Language Stream field.
   */
  public function getStreams() {
    // Get all existing streams based on the options set on
    // "Language Stream (for finals))" field.
    $field_language_stream = $this->entityFieldManager
      ->getFieldStorageDefinitions('node')['field_language_stream'];
    $streams = $field_language_stream->getSetting('allowed_values');
    $streams['_none'] = '- None -';
    return $streams;
  }

  /**
   * Get a list of recitations in the correct sort order.
   */
  public function getRecitationsInOrder(NodeInterface $node) : array {
    $cid = "piv_live_competition:recitations_in_order:{$node->id()}";
    if ($cache = $this->cacheDefault->get($cid)) {
      return $cache->data;
    }

    $team_regionals = $this->entityTypeManager->getStorage('node')
      ->loadByProperties([
        'type' => 'team_regionals_entry',
        'field_contest_association' => $node->id(),
      ]);
    $entries = [];
    foreach ($team_regionals as $team_regional) {
      $entries = array_merge($entries, $team_regional->field_tr_student->referencedEntities());
    }

    // Sort by order, if its the same value then use the id, if no value
    // is set then its infinite (push to last).
    usort($entries, function ($a, $b) {
      $a_order = $a->field_recitation_order->value ?? INF;
      $b_order = $b->field_recitation_order->value ?? INF;
      if ($a_order == $b_order) {
        return $a->id() <=> $b->id();
      }
      return $a_order <=> $b_order;
    });

    $tags = ['paragraph_list:tr_student'];
    $this->cacheDefault->set($cid, $entries, CacheBackendInterface::CACHE_PERMANENT, $tags);
    return $entries;
  }

  /**
   * Get student name from recitation.
   */
  public function getStudentName(ParagraphInterface $recitation) : ?string {
    return $recitation->field_stage_name->value == 1 && !empty($recitation->field_student_name_1)
      ? $recitation->field_student_name_1->value
      : $recitation->field_legal_name->value;
  }

}
