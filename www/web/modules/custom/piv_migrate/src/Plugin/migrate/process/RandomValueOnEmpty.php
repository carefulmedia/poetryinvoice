<?php

namespace Drupal\piv_migrate\Plugin\migrate\process;

use Drupal\Component\Utility\Random;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Class RandomValueOnEmpty
 *
 * @MigrateProcessPlugin(
 *   id = "random_value_on_empty",
 *   handle_multiples = TRUE
 * )
 */
class RandomValueOnEmpty extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $random = new Random();
    return $value ?: $random->string();
  }
}
