<?php

namespace Drupal\piv_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\Core\Database\Database;

/**
 * Redirect translation fix.
 *
 * @MigrateProcessPlugin(
 *   id = "redirect_translation",
 *   handle_multiples = FALSE
 * )
 */
class RedirectTranslation extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Expected value is internal:/node/nid/...
    $parts = explode('/', $value);

    if (count($parts) > 2 && $parts[1] == 'node' && is_numeric($parts[2])) {
      // Tnid is the translation id, the node with the original language. In d7
      // the nodes have different ids per language, but in d9 we use the same
      // nid for all languages.
      $tnid = Database::getConnection('default', 'migrate')
        ->select('node', 'n')
        ->condition('nid', $parts[2])
        ->fields('n', ['tnid'])
        ->execute()
        ->fetchCol();
      if (count($tnid) && $tnid[0] > 0) {
        $parts[2] = $tnid[0];
        return implode('/', $parts);
      }
    }
    return $value;
  }

}
