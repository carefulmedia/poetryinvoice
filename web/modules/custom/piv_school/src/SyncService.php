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
   * Return the current uploaded File.
   */
  public function getCSVFile() {
    $config = $this->configFactory->get('piv_school.settings');
    if ($fid = $config->get('contacts_csv_file')) {
      $fid = reset($fid);
      $file = $this->entityManager->getStorage('file')->load($fid);
      return $file;
    }

    return NULL;
  }

  public function start() {

  }

}
