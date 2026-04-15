<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\piv_futureverse\FutureverseApplicationInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the futureverse application entity class.
 *
 * @ContentEntityType(
 *   id = "futureverse_application",
 *   label = @Translation("Futureverse Application"),
 *   label_collection = @Translation("Futureverse Applications"),
 *   label_singular = @Translation("futureverse application"),
 *   label_plural = @Translation("futureverse applications"),
 *   label_count = @PluralTranslation(
 *     singular = "@count futureverse applications",
 *     plural = "@count futureverse applications",
 *   ),
 *   bundle_label = @Translation("Futureverse Application type"),
 *   handlers = {
 *     "list_builder" = "Drupal\piv_futureverse\FutureverseApplicationListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\piv_futureverse\FutureverseApplicationAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\piv_futureverse\Form\FutureverseApplicationForm",
 *       "edit" = "Drupal\piv_futureverse\Form\FutureverseApplicationForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\piv_futureverse\Routing\FutureverseApplicationHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "futureverse_application",
 *   admin_permission = "administer futureverse_application types",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "bundle",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/futureverse-application",
 *     "add-form" = "/futureverse-application/add/{futureverse_application_type}",
 *     "add-page" = "/futureverse-application/add",
 *     "canonical" = "/futureverse-application/{futureverse_application}",
 *     "edit-form" = "/futureverse-application/{futureverse_application}",
 *     "delete-form" = "/futureverse-application/{futureverse_application}/delete",
 *     "delete-multiple-form" = "/admin/content/futureverse-application/delete-multiple",
 *   },
 *   bundle_entity_type = "futureverse_application_type",
 *   field_ui_base_route = "entity.futureverse_application_type.edit_form",
 * )
 */
final class FutureverseApplication extends ContentEntityBase implements FutureverseApplicationInterface {

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

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
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
      ->setDescription(t('The time that the futureverse application was created.'))
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
      ->setDescription(t('The time that the futureverse application was last edited.'));

    return $fields;
  }

}
