<?php

declare(strict_types=1);

namespace Drupal\piv_logs\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\piv_logs\Form\LogTypeForm;
use Drupal\piv_logs\LogTypeListBuilder;

/**
 * Defines the Log type configuration entity.
 */
#[ConfigEntityType(
  id: 'piv_log_type',
  label: new TranslatableMarkup('Log type'),
  label_collection: new TranslatableMarkup('Log types'),
  label_singular: new TranslatableMarkup('log type'),
  label_plural: new TranslatableMarkup('logs types'),
  config_prefix: 'piv_log_type',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => LogTypeListBuilder::class,
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
    'form' => [
      'add' => LogTypeForm::class,
      'edit' => LogTypeForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/structure/piv_log_types/add',
    'edit-form' => '/admin/structure/piv_log_types/manage/{piv_log_type}',
    'delete-form' => '/admin/structure/piv_log_types/manage/{piv_log_type}/delete',
    'collection' => '/admin/structure/piv_log_types',
  ],
  admin_permission: 'administer piv_log types',
  bundle_of: 'piv_log',
  label_count: [
    'singular' => '@count log type',
    'plural' => '@count logs types',
  ],
  config_export: [
    'id',
    'label',
    'uuid',
  ],
)]
final class LogType extends ConfigEntityBundleBase {

  /**
   * The machine name of this log type.
   */
  protected string $id;

  /**
   * The human-readable name of the log type.
   */
  protected string $label;

}
