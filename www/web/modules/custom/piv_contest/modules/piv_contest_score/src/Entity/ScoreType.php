<?php

namespace Drupal\piv_contest_score\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Score type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "score_type",
 *   label = @Translation("Score type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\piv_contest_score\Form\ScoreTypeForm",
 *       "edit" = "Drupal\piv_contest_score\Form\ScoreTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\piv_contest_score\ScoreTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer score types",
 *   bundle_of = "score",
 *   config_prefix = "score_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/score_types/add",
 *     "edit-form" = "/admin/structure/score_types/manage/{score_type}",
 *     "delete-form" = "/admin/structure/score_types/manage/{score_type}/delete",
 *     "collection" = "/admin/structure/score_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   }
 * )
 */
class ScoreType extends ConfigEntityBundleBase {

  /**
   * The machine name of this score type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the score type.
   *
   * @var string
   */
  protected $label;

}
