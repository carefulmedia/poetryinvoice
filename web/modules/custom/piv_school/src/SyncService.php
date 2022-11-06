<?php

namespace Drupal\piv_school;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

class SyncService {
  
  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Construct PivotalEvent.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, ConfigFactoryInterface $config_factory, LoggerInterface $logger) {
    $this->entityManager = $entity_manager;
    $this->configFactory = $config_factory;
    $this->logger = $logger;
  }

  /**
   * Load the current uploaded CSV file.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The loaded file entity or NULL.
   */
  public function getCSVFile() {
    $config = $this->configFactory->get('piv_school.settings');
    if ($fid = $config->get('contacts_csv_file')) {
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

    foreach ($csv as $row) {
      // Only rows that record is 1 and Type 2 is not 1.
      if ($row['RECORD'] != 1 || $row['TYPE2'] == 1) {
        continue;
      }
      $rows[] = array_combine($keys, $row);
    }

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
      return $node;
    }

    return NULL;
  }

  /**
   * Creates an empty Node.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The node object.
   */
  public function createNode($fields) {
    return $this->entityManager->getStorage('node')->create([
      'type' => 'school',
      'langcode' => 'en',
      'uid' => 1,
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
  public function updateNode($node, $fields) {
    $node->title = $fields['name'];
    $node->field_cdb_id = $fields['id'];
    $node->field_school_phone = $fields['phone'];
    $node->field_story_link = $fields['website'];
    $node->field_contact_first_name = $fields['first_name'];
    $node->field_contact_last_name = $fields['last_name'];
    $node->field_contact_email = $fields['email'];
    $node->field_school_district_type1 = $fields['type1'];
    $node->field_address = [
      'country_code' => 'CA',
      'administrative_area' => $fields['prov'],
      'address_line1' => $fields['add1'],
      'address_line2' => $fields['add2'],
      'locality' => $fields['city'],
    ];
    return $node->save();
  }

  /**
   * Start a batch sync of all the CSV data.
   *
   * @param bool $is_drush
   *   If this is a drush call or not.
   */
  public function start($is_drush = FALSE) {
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
      'title' => 'Syncing Pivotal events',
    ];

    batch_set($batch);
    if ($is_drush) {
      drush_backend_batch_process();
    }
  }

  /**
   * Execute a event sync.
   */
  public static function processBatch($row, &$context) {
    if (!isset($context['results']['num'])) {
      $context['results']['num'] = 0;
    }

    $is_new = FALSE;
    $sync = \Drupal::service('piv_school.sync');
    $node = $sync->laodNode($row['id']);
    if (!$node) {
      $is_new = TRUE;
      $node = $sync->createNode($row['id']);
    }

    if ($sync->updateNode($node, $row)) {
      $action = $is_new ? 'created' : 'updated';
      \Drupal::logger('piv_school')->info("Node NID %nid - CDB ID %cdbid %action.", ['%nid' => $node->id(), '%cdbid' => $row['id'], '%action' => $action]);
    }
    else {
      \Drupal::logger('piv_school')->info("Faild to process the row ID: %cdbid", ['%cdbid' => $row['id']]);
    }
  }

  /**
   * The batch finish callback.
   */
  public static function finishBatch($success, $results, $operations) {
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

}
