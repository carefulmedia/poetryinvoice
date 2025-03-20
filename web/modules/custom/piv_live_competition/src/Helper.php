<?php

declare(strict_types=1);

namespace Drupal\piv_live_competition;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Helper class for Live Competitions ('competition' nodes).
 */
final class Helper {

  /**
   * Constructs a Helper object.
   */
  public function __construct(
    private readonly CacheBackendInterface $cacheDefault,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

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
