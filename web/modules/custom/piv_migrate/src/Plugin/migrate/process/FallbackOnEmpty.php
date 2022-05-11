<?php

namespace Drupal\piv_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Class ConcatValue
 *
 * @MigrateProcessPlugin(
 *   id = "fallback_on_empty",
 *   handle_multiples = TRUE
 * )
 */
class FallbackOnEmpty extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!$value) {
      if ($row->get($this->configuration['fallback_key'])) {
        return $row->get($this->configuration['fallback_key']);
      }
    }

    return $value;
  }
}
