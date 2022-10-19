<?php

namespace Drupal\piv_field\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'entity_reference_school' entity field type.
 *
 * Supported settings (below the definition's 'settings' key) are:
 * - target_type: The entity type to reference. Required.
 *
 * @FieldType(
 *   id = "entity_reference_school",
 *   label = @Translation("Entity reference school"),
 *   description = @Translation("An entity field containing an entity reference."),
 *   category = @Translation("Reference"),
 *   default_widget = "entity_reference_autocomplete",
 *   default_formatter = "entity_reference_label",
 *   list_class = "\Drupal\Core\Field\EntityReferenceFieldItemList",
 * )
 */
class EntityReferenceSchool extends EntityReferenceItem {
  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $definition = DataDefinition::create('boolean')
      ->setLabel(t('Search by Postal Code?'))
      ->setDescription(t('Default search will use the School title, check this option to search by the Postal Code instead.'));
    $properties['postal_code'] = $definition;
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);
    $schema['columns']['postal_code'] = [
      'description' => 'Flag to control whether this reference is searched by Postal Code.',
      'type' => 'int',
      'size' => 'tiny',
      'unsigned' => TRUE,
      'default' => 0,
    ];
  
    return $schema;
  }
}
