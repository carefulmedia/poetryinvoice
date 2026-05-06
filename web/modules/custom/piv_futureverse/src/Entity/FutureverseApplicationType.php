<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Futureverse Application type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "futureverse_application_type",
 *   label = @Translation("Futureverse Application type"),
 *   label_collection = @Translation("Futureverse Application types"),
 *   label_singular = @Translation("futureverse application type"),
 *   label_plural = @Translation("futureverse applications types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count futureverse applications type",
 *     plural = "@count futureverse applications types",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\piv_futureverse\Form\FutureverseApplicationTypeForm",
 *       "edit" = "Drupal\piv_futureverse\Form\FutureverseApplicationTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\piv_futureverse\FutureverseApplicationTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer futureverse_application types",
 *   bundle_of = "futureverse_application",
 *   config_prefix = "futureverse_application_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/futureverse_application_types/add",
 *     "edit-form" = "/admin/structure/futureverse_application_types/manage/{futureverse_application_type}",
 *     "delete-form" = "/admin/structure/futureverse_application_types/manage/{futureverse_application_type}/delete",
 *     "collection" = "/admin/structure/futureverse_application_types",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   },
 * )
 */
final class FutureverseApplicationType extends ConfigEntityBundleBase {

  /**
   * The machine name of this futureverse application type.
   */
  protected string $id;

  /**
   * The human-readable name of the futureverse application type.
   */
  protected string $label;

}
