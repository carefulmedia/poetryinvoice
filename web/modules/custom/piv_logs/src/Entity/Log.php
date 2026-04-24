<?php

declare(strict_types=1);

namespace Drupal\piv_logs\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\piv_logs\Form\LogForm;
use Drupal\piv_logs\LogAccessControlHandler;
use Drupal\piv_logs\LogInterface;
use Drupal\piv_logs\LogListBuilder;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;

/**
 * Defines the log entity class.
 */
#[ContentEntityType(
  id: 'piv_log',
  label: new TranslatableMarkup('Log'),
  label_collection: new TranslatableMarkup('Logs'),
  label_singular: new TranslatableMarkup('log'),
  label_plural: new TranslatableMarkup('logs'),
  entity_keys: [
    'id' => 'id',
    'langcode' => 'langcode',
    'bundle' => 'bundle',
    'label' => 'label',
    'owner' => 'uid',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => LogListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => LogAccessControlHandler::class,
    'form' => [
      'add' => LogForm::class,
      'edit' => LogForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/content/piv-log',
    'add-form' => '/log/add/{piv_log_type}',
    'add-page' => '/log/add',
    'canonical' => '/log/{piv_log}',
    'edit-form' => '/log/{piv_log}/edit',
    'delete-form' => '/log/{piv_log}/delete',
    'delete-multiple-form' => '/admin/content/piv-log/delete-multiple',
  ],
  admin_permission: 'administer piv_log types',
  bundle_entity_type: 'piv_log_type',
  bundle_label: new TranslatableMarkup('Log type'),
  base_table: 'piv_log',
  data_table: 'piv_log_field_data',
  translatable: TRUE,
  label_count: [
    'singular' => '@count logs',
    'plural' => '@count logs',
  ],
  field_ui_base_route: 'entity.piv_log_type.edit_form',
)]
class Log extends ContentEntityBase implements LogInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setTranslatable(TRUE)
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the log was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the log was last edited.'));

    return $fields;
  }

}
