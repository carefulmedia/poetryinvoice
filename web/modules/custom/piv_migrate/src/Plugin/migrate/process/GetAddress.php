<?php

namespace Drupal\piv_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\migrate\Plugin\MigrateProcessInterface;
use Drupal\Core\Database\Database;

/**
 * Migrate alias from nodes to groups.
 *
 * @MigrateProcessPlugin(
 *   id = "get_address",
 *   handle_multiples = FALSE
 * )
 */
class GetAddress extends ProcessPluginBase implements MigrateProcessInterface {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrateExecutable, Row $row, $destination_property) {
    // Expected $value is a vid, not a nid.
    if (!is_numeric($value)) {
      return [];
    }

    $query = Database::getConnection('default', 'migrate')
      ->select('location_instance', 'i');
    $query->join('location', 'l', 'l.lid = i.lid');
    $address = $query->condition('i.vid', $value)
      ->fields('l')
      ->execute()
      ->fetch();

    if ($address) {
      // Map.
      return [
        'country_code' => empty($address->country) ? NULL : strtoupper($address->country),
        'administrative_area' => empty($address->province) ? NULL : strtoupper($address->province),
        'locality' => $address->city ?? NULL,
        'dependent_locality' => NULL,
        'postal_code' => $address->postal_code ?? NULL,
        'sorting_code' => NULL,
        'address_line1' => $address->street ?? NULL,
        'address_line2' => $address->additional ?? NULL,
        'organization' => NULL,
        'given_name' => NULL,
        'additional_name' => NULL,
        'family_name' => NULL,
      ];
    }

    return [];
  }

}
