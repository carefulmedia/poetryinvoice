<?php

namespace Drupal\piv_field\Plugin\EntityReferenceSelection;

use Drupal\node\Plugin\EntityReferenceSelection\NodeSelection;
use Drupal\Component\Utility\Html;

/**
 * Provides specific access control for the node entity type.
 *
 * @EntityReferenceSelection(
 *   id = "default:piv_school",
 *   label = @Translation("School node selection"),
 *   entity_types = {"node"},
 *   group = "default",
 *   weight = 1
 * )
 */
class SchoolSelection extends NodeSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('type', 'school');

    // Validate if should filter by the Label or Postal Code.
    $search = explode('pcode:', $match);
    if ($search && isset($search[1])) {
      $search = trim($search[1]);
      if (strlen($search) > 3) {
        $start = trim(substr($search, 0, 3));
        $end = trim(substr($search, 3));
        $search = "${start} ${end}";
      }
      $query->condition('field_address.postal_code', $search, $match_operator);
    }
    else {
      $query->condition('title', $match, $match_operator);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $target_type = $this->getConfiguration()['target_type'];

    $query = $this->buildEntityQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $result = $query->execute();

    if (empty($result)) {
      return [];
    }

    $options = [];
    $entities = $this->entityTypeManager->getStorage($target_type)->loadMultiple($result);
    foreach ($entities as $entity_id => $entity) {
      $translated = $this->entityRepository->getTranslationFromContext($entity);
      $bundle = $entity->bundle();
      $address = $translated->get('field_address')->first()->getValue();
      $label = $translated->label() ?? '';
      $postal_code = $address['postal_code'] ?? '';
      $administrative_area = $address['administrative_area'] ?? '';
      $locality = $address['locality'] ?? '';
      $options[$bundle][$entity_id] = Html::escape("${label} - ${locality} ${administrative_area}, ${postal_code}");
    }

    return $options;
  }

}
