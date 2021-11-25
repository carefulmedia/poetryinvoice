<?php

namespace Drupal\piv_contest_judging_session\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Judging Session type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "judging_session_type",
 *   label = @Translation("Judging Session type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\piv_contest_judging_session\Form\JudgingSessionTypeForm",
 *       "edit" = "Drupal\piv_contest_judging_session\Form\JudgingSessionTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\piv_contest_judging_session\JudgingSessionTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer judging session types",
 *   bundle_of = "judging_session",
 *   config_prefix = "judging_session_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/judging_session_types/add",
 *     "edit-form" = "/admin/structure/judging_session_types/manage/{judging_session_type}",
 *     "delete-form" = "/admin/structure/judging_session_types/manage/{judging_session_type}/delete",
 *     "collection" = "/admin/structure/judging_session_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   }
 * )
 */
class JudgingSessionType extends ConfigEntityBundleBase {

  /**
   * The machine name of this judging session type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the judging session type.
   *
   * @var string
   */
  protected $label;

}
