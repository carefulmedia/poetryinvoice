<?php

namespace Drupal\piv_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Class Transform alias if it for a node.
 *
 * @MigrateProcessPlugin(
 *   id = "transform_alias_if_node",
 *   handle_multiples = TRUE
 * )
 */
class TransformAliasIfNode extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $migrated_nid_field = $this->configuration['migrated_nid'] ?? '';
    $parts = array_filter(explode('/', $value));
    if (count($parts) === 2 && $parts[0] === 'node' && $migrated_nid_field) {
      $migrated_nid = $row->get($migrated_nid_field);
      if ($migrated_nid) {
        return "/node/{$migrated_nid}";
      }
    }
    // Need to always add a / to beggining if there isn't one.
    if (is_string($value)) {
      if ($value[0] != '/') {
        return "/{$value}";
      }
    }
    return $value;
  }

}
