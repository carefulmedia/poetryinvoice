<?php

declare(strict_types=1);

namespace Drupal\piv_logs\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Entity\Form\RevisionDeleteForm;
use Drupal\Core\Entity\Form\RevisionRevertForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider;
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
    'revision' => 'revision_id',
    'bundle' => 'bundle',
    'label' => 'label',
    'owner' => 'uid',
    'published' => 'status',
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
      'revision-delete' => RevisionDeleteForm::class,
      'revision-revert' => RevisionRevertForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
      'revision' => RevisionHtmlRouteProvider::class,
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
    'revision' => '/log/{piv_log}/revision/{piv_log_revision}/view',
    'revision-delete-form' => '/log/{piv_log}/revision/{piv_log_revision}/delete',
    'revision-revert-form' => '/log/{piv_log}/revision/{piv_log_revision}/revert',
    'version-history' => '/log/{piv_log}/revisions',
  ],
  admin_permission: 'administer piv_log types',
  bundle_entity_type: 'piv_log_type',
  bundle_label: new TranslatableMarkup('Log type'),
  base_table: 'piv_log',
  revision_table: 'piv_log_revision',
  show_revision_ui: TRUE,
  label_count: [
    'singular' => '@count logs',
    'plural' => '@count logs',
  ],
  field_ui_base_route: 'entity.piv_log_type.edit_form',
  revision_metadata_keys: [
    'revision_user' => 'revision_uid',
    'revision_created' => 'revision_timestamp',
    'revision_log_message' => 'revision_log',
  ],
)]
class Log extends EditorialContentEntityBase implements LogInterface {

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
    // Force new revision for logs.
    $this->setNewRevision();
    $this->setRevisionCreationTime(\Drupal::time()->getRequestTime());
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
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

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setRevisionable(TRUE)
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['message'] = BaseFieldDefinition::create('string_long')
      ->setRevisionable(TRUE)
      ->setLabel(t('Message'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'above',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
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
      ->setDescription(t('The time that the log was last edited.'));

    return $fields;
  }

}
