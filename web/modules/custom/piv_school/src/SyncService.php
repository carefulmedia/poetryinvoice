<?php

namespace Drupal\piv_school;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\State;
use Drupal\Core\Entity\EntityStorageException;
use Psr\Log\LoggerInterface;

/**
 *
 */
class SyncService {
  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * The state store.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Construct PivotalEvent.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, State $state, LoggerInterface $logger) {
    $this->entityManager = $entity_manager;
    $this->state = $state;
    $this->logger = $logger;
  }

  /**
   * Load the current uploaded CSV file.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The loaded file entity or NULL.
   */
  public function getCSVFile() {
    if ($fid = $this->state->get('contacts_csv_file')) {
      $fid = reset($fid);
      $file = $this->entityManager->getStorage('file')->load($fid);
      return $file;
    }

    $this->logger->warning("The CSV file could not be loaded.");
    return NULL;
  }

  /**
   * Extract the CSV data.
   *
   * @return array
   *   The CSV array of rows.
   */
  protected function loadCSVData() {
    $entity = $this->getCSVFile();
    if (!$entity) {
      return [];
    }

    // Get the CSV rows and prepare to include the Header as an associative array.
    $csv = array_map('str_getcsv', file($entity->uri->getString()));
    $keys = array_shift($csv);
    $rows = [];

    foreach ($keys as $i => $key_name) {
      $keys[$i] = str_replace(' ', '_', strtolower($key_name));
    }

    $total = count($csv);
    $ignored = 0;
    foreach ($csv as $row) {
      // Only rows that record is 1 and Type 2 is not 1.
      if ($row[1] != 1 || $row[18] == 1) {
        $ignored++;
        continue;
      }
      if (count($keys) == count($row)) {
        $rows[] = array_combine($keys, $row);
      }
    }

    $valid = $total - $ignored;
    $params = ['%valid' => $valid, '%total' => $total, '%ignored' => $ignored];
    $this->logger->info('Loaded CSV. Total items: %valid (%total). Number of ignored items: %ignored', $params);
    return $rows;
  }

  /**
   * Get an existent Scholl by CDB ID.
   *
   * @param string $id
   *   The CDB ID value.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The node object or NULL.
   */
  public function loadNode($id) {
    $node = $this->entityManager->getStorage('node')->loadByProperties([
      'field_cdb_id' => $id,
      'type' => 'school',
    ]);

    if ($node) {
      return reset($node);
    }

    return NULL;
  }

  /**
   * Creates an empty Node.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The node object.
   */
  public function createNode() {
    return $this->entityManager->getStorage('node')->create([
      'type' => 'school',
      'langcode' => 'en',
      'uid' => 1,
      'field_allow_poet_visits_school' => 1,
    ]);
  }

  /**
   * Updates an node.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   The node object.
   * @param array $fields
   *   The fileds values.
   *
   * @return int
   *   The node save() response.
   */
  public function updateNode($node, $fields, $new = TRUE) {
    try {
      // Until the CSV sources manage accents, we do not update the title.
      $node->title = $fields['name'];
      $node->field_cdb_id = $fields['id'];
      $node->field_school_phone = $fields['phone'];
      $node->field_story_link = $fields['website'];
      $node->field_contact_first_name = $fields['first_name'];
      $node->field_contact_last_name = $fields['last_name'];
      $node->field_contact_email = $fields['email'];
      $node->field_school_district_type1 = $fields['type1'];
      $node->field_job_title = $fields['job_title'];
      $node->field_special_subjects = $fields['spec'];
      $node->field_number_of_students = $fields['enr'];

      if (isset($fields['language']) && !empty($fields['language'])) {
        $node->field_language_school = $fields['language'];
      }

      // Until the CSV sources manage accents, we only update the postal code.
      $node->field_address = [
        'country_code' => 'CA',
        'administrative_area' => $fields['prov'],
        'address_line1' => $fields['add1'],
        'address_line2' => $fields['add2'],
        'locality' => $fields['city'],
        'postal_code' => $fields['code'],
      ];
      return $node->save();
    }
    catch (EntityStorageException $e) {
      $this->logger->error("Error updating the node with CDB Id %id. Error: %error", ['%id' => $fields['id'], '%error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Start a batch sync of all the CSV data.
   *
   * @param bool $is_drush
   *   If this is a drush call or not.
   */
  public function start($is_drush = FALSE): void {
    $rows = $this->loadCSVData();
    $operations = [];

    foreach ($rows as $row) {
      $operations[] = [
        [static::class, 'processBatch'],
        [$row],
      ];
    }

    $batch = [
      'operations' => $operations,
      'finished' => [static::class, 'finishBatch'],
      'title' => 'Syncing schools...',
    ];

    batch_set($batch);
    if ($is_drush) {
      drush_backend_batch_process();
    }
  }

  /**
   * Execute a event sync.
   */
  public static function processBatch($row, &$context): void {
    if (!isset($context['results']['num'])) {
      $context['results']['num'] = 0;
    }

    $is_new = FALSE;
    $sync = \Drupal::service('piv_school.sync');
    $logger = \Drupal::logger('piv_school');
    $node = $sync->loadNode($row['id']);
    if (!$node) {
      $is_new = TRUE;
      $node = $sync->createNode();
    }

    if ($sync->updateNode($node, $row, $is_new)) {
      $action = $is_new ? 'created' : 'updated';
      $logger->info("Node NID %nid - CDB ID %cdbid %action.", ['%nid' => $node->id(), '%cdbid' => $row['id'], '%action' => $action]);
    }
    else {
      $logger->info("Faild to process the row ID: %cdbid", ['%cdbid' => $row['id']]);
    }

    $context['results']['num']++;
  }

  /**
   * The batch finish callback.
   */
  public static function finishBatch($success, $results, $operations): void {
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        $results['num'],
        'One row processed.', '@count rows processed.'
      );
    }
    else {
      $message = \Drupal::translation()->translate('Finished with an error.');
    }
    \Drupal::messenger()->addMessage($message);
  }

  /**
   * Updates all nodes to allow for poet visits.
   */
  public function updateNodesToAllowPoetVisits() {
    $batch_size = 50;

    $results = [
      'success' => 0,
      'failed' => 0,
      'total' => 0,
    ];

    try {
      $node_storage = $this->entityManager->getStorage('node');

      // Query all School nodes.
      $query = $node_storage->getQuery()
        ->condition('type', 'school')
        ->condition('field_allow_poet_visits_school', 0)
        ->accessCheck(FALSE);

      $nids = $query->execute();
      $results['total'] = count($nids);

      if (empty($nids)) {
        $this->logger->info('No School nodes found to update.');
        return $results;
      }

      $this->logger->info('Found @count School nodes to update.', ['@count' => $results['total']]);

      $batches = array_chunk($nids, $batch_size);

      foreach ($batches as $batch) {
        $nodes = $node_storage->loadMultiple($batch);

        foreach ($nodes as $node) {
          try {
            $node->set('field_allow_poet_visits_school', TRUE);
            $node->save();
            $results['success']++;
          }
          catch (\Exception $e) {
            $results['failed']++;
            $this->logger->error('Failed to update node @nid: @message', [
              '@nid' => $node->id(),
              '@message' => $e->getMessage(),
            ]);
          }
        }

        $node_storage->resetCache($batch);
      }

      $this->logger->info('Updated @success of @total School nodes. @failed failed.', [
        '@success' => $results['success'],
        '@total' => $results['total'],
        '@failed' => $results['failed'],
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Error during bulk update: @message', ['@message' => $e->getMessage()]);
    }

    return $results;
  }

}
