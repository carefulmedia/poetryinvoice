<?php

namespace Drupal\piv_school\Commands;

use Drush\Commands\DrushCommands;

/**
 * Drush command file.
 */
class SchoolSyncCommands extends DrushCommands {

  /**
   * Execute the School synchronization.
   *
   * @command piv:school-sync
   * @aliases pss,piv-ss
   */
  public function SchoolSync(): void {
    $this->output()->writeln('Syncing the schools.');
    \Drupal::service('piv_school.sync')->start(TRUE);
  }

}
