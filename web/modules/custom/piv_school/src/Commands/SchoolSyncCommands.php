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

  /**
   * Execute the update to all school to allow for poet visits.
   *
   * @command piv:school-allow-poet-visits
   * @aliases psapv,piv-sapv
   */
  public function allowPoetVisit(): void {
    $this->output()->writeln('Updating all schools to allow poet visits...');

    $results = \Drupal::service('piv_school.allow_poet_visits')->updateNodesToAllowPoetVisits();

    $this->output()->writeln(sprintf(
      'Update complete. Success: %d, Failed: %d, Total: %d',
      $results['success'],
      $results['failed'],
      $results['total']
    ));
  }

}
