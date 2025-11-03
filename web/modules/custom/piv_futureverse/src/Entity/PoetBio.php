<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\piv_futureverse\PoetBioInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the poet bio entity class.
 *
 * @ContentEntityType(
 *   id = "poet_bio",
 *   label = @Translation("Poet Bio"),
 *   label_collection = @Translation("Poet Bios"),
 *   label_singular = @Translation("poet bio"),
 *   label_plural = @Translation("poet bios"),
 *   label_count = @PluralTranslation(
 *     singular = "@count poet bios",
 *     plural = "@count poet bios",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\piv_futureverse\PoetBioListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\piv_futureverse\PoetBioAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\piv_futureverse\Form\PoetBioForm",
 *       "edit" = "Drupal\piv_futureverse\Form\PoetBioForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\piv_futureverse\Routing\PoetBioHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "poet_bio",
 *   admin_permission = "administer poet_bio",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/poet-bio",
 *     "add-form" = "/poet-bio/add",
 *     "canonical" = "/poet-bio/{poet_bio}",
 *     "edit-form" = "/poet-bio/{poet_bio}",
 *     "delete-form" = "/poet-bio/{poet_bio}/delete",
 *     "delete-multiple-form" = "/admin/content/poet-bio/delete-multiple",
 *   },
 *   field_ui_base_route = "entity.poet_bio.settings",
 * )
 */
final class PoetBio extends ContentEntityBase implements PoetBioInterface {

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
      ->setDescription(t('The time that the poet bio was created.'))
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
      ->setDescription(t('The time that the poet bio was last edited.'));

    return $fields;
  }

}
