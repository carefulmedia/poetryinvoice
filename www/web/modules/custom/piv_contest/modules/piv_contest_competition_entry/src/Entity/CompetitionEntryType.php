<?php

namespace Drupal\piv_contest_competition_entry\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Competition Entry type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "competition_entry_type",
 *   label = @Translation("Competition Entry type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\piv_contest_competition_entry\Form\CompetitionEntryTypeForm",
 *       "edit" = "Drupal\piv_contest_competition_entry\Form\CompetitionEntryTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\piv_contest_competition_entry\CompetitionEntryTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer competition entry types",
 *   bundle_of = "competition_entry",
 *   config_prefix = "competition_entry_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/competition_entry_types/add",
 *     "edit-form" = "/admin/structure/competition_entry_types/manage/{competition_entry_type}",
 *     "delete-form" = "/admin/structure/competition_entry_types/manage/{competition_entry_type}/delete",
 *     "collection" = "/admin/structure/competition_entry_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   }
 * )
 */
class CompetitionEntryType extends ConfigEntityBundleBase {

  /**
   * The machine name of this competition entry type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the competition entry type.
   *
   * @var string
   */
  protected $label;

}
