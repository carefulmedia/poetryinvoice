<?php

namespace Drupal\piv_contest;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the node entity type.
 */
class ContestViewsData extends EntityViewsData {

  protected $entityTypeId;

  public function __construct(
    EntityTypeInterface $entity_type,
    SqlEntityStorageInterface $storage_controller,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    TranslationInterface $translation_manager,
    EntityFieldManagerInterface $entity_field_manager,
  ) {
    $this->entityTypeId = $entity_type->id();
    parent::__construct($entity_type, $storage_controller, $entity_type_manager, $module_handler, $translation_manager, $entity_field_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $types = [
      'competition',
      'competition_entry',
      'judging_session',
      'recitation',
      'score_template',
      'score',
    ];

    foreach ($types as $type) {
      $data[$type]['contest_bulk_form'] = [
        'title' => $this->t('Contest operations bulk form'),
        'help' => $this->t('Add a form element that lets you run operations on multiple contest entities.'),
        'field' => [
          'id' => 'contest_bulk_form',
        ],
      ];
    }

    return $data;
  }
}
