<?php

namespace Drupal\piv_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Class ConcatValue.
 *
 * @MigrateProcessPlugin(
 *   id = "concat_value",
 *   handle_multiples = TRUE
 * )
 */
class ConcatValue extends ProcessPluginBase {

  /**
   *
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (is_array($value)) {
      $delimiter = $this->configuration['delimiter'] ?? '';

      $result_values = [];
      foreach ($value as $v) {
        if (isset($v[0]['value'])) {
          $result_values[] = $v[0]['value'];
        }
      }

      return implode($delimiter, $result_values);
    }

    throw new MigrateException(sprintf('%s is not an array', var_export($value, TRUE)));
  }

}
