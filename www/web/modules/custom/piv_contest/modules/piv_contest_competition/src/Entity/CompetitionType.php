<?php

namespace Drupal\piv_contest_competition\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Competition type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "competition_type",
 *   label = @Translation("Competition type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\piv_contest_competition\Form\CompetitionTypeForm",
 *       "edit" = "Drupal\piv_contest_competition\Form\CompetitionTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\piv_contest_competition\CompetitionTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer competition types",
 *   bundle_of = "competition",
 *   config_prefix = "competition_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/competition_types/add",
 *     "edit-form" = "/admin/structure/competition_types/manage/{competition_type}",
 *     "delete-form" = "/admin/structure/competition_types/manage/{competition_type}/delete",
 *     "collection" = "/admin/structure/competition_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   }
 * )
 */
class CompetitionType extends ConfigEntityBundleBase {

  /**
   * The machine name of this competition type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the competition type.
   *
   * @var string
   */
  protected $label;

}
