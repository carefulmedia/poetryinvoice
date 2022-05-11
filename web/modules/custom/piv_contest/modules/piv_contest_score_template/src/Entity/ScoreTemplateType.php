<?php

namespace Drupal\piv_contest_score_template\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Score Template type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "score_template_type",
 *   label = @Translation("Score Template type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\piv_contest_score_template\Form\ScoreTemplateTypeForm",
 *       "edit" = "Drupal\piv_contest_score_template\Form\ScoreTemplateTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\piv_contest_score_template\ScoreTemplateTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer score template types",
 *   bundle_of = "score_template",
 *   config_prefix = "score_template_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/contest/config/score_template_types/add",
 *     "edit-form" = "/admin/contest/config/score_template_types/manage/{score_template_type}",
 *     "delete-form" = "/admin/contest/config/score_template_types/manage/{score_template_type}/delete",
 *     "collection" = "/admin/contest/config/score_template_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "scoreType",
 *     "uuid",
 *   }
 * )
 */
class ScoreTemplateType extends ConfigEntityBundleBase {

  /**
   * The machine name of this score template type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the score template type.
   *
   * @var string
   */
  protected $label;

  /**
   * The score type this score template creates.
   *
   * @var string
   */
  protected $scoreType;

  /**
   * Get the score type.
   */
  public function getScoreType() {
    return $this->scoreType;
  }

}
