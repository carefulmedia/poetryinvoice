<?php

namespace Drupal\piv_contest_competition_entry\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\node\Plugin\EntityReferenceSelection\NodeSelection;

/**
 * Class LanguageEntityReferenceSelection
 *
 * @EntityReferenceSelection(
 *   id = "language_entity_reference:node",
 *   label = @Translation("Language entity reference Node selection"),
 *   entity_types = {"node"},
 *   group = "piv_contest_competition_entry",
 *   weight = 1
 * )
 */
class LanguageEntityReferenceSelection extends NodeSelection {
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $target_type = $this->getConfiguration()['target_type'];

    $query = $this->buildEntityQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $query->condition('langcode', $this->getConfiguration()['language']);

    $result = $query->execute();

    if (empty($result)) {
      return [];
    }

    $options = [];
    $entities = $this->entityTypeManager->getStorage($target_type)->loadMultiple($result);
    foreach ($entities as $entity_id => $entity) {
      $bundle = $entity->bundle();
      $options[$bundle][$entity_id] = Html::escape($this->entityRepository->getTranslationFromContext($entity)->label() ?? '');
    }

    return $options;
  }
}
