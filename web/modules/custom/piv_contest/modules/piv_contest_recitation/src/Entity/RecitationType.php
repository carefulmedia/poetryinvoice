<?php

namespace Drupal\piv_contest_recitation\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Recitation type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "recitation_type",
 *   label = @Translation("Recitation type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\piv_contest_recitation\Form\RecitationTypeForm",
 *       "edit" = "Drupal\piv_contest_recitation\Form\RecitationTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\piv_contest_recitation\RecitationTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer recitation types",
 *   bundle_of = "recitation",
 *   config_prefix = "recitation_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/contest/config/recitation_types/add",
 *     "edit-form" = "/admin/contest/config/recitation_types/manage/{recitation_type}",
 *     "delete-form" = "/admin/contest/config/recitation_types/manage/{recitation_type}/delete",
 *     "collection" = "/admin/contest/config/recitation_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   }
 * )
 */
class RecitationType extends ConfigEntityBundleBase {

  /**
   * The machine name of this recitation type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the recitation type.
   *
   * @var string
   */
  protected $label;

}
