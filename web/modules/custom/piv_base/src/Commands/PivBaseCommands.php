<?php

namespace Drupal\piv_base\Commands;

use CLI\Usage;
use CLI\Command;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Drush commandfile.
 */
final class PivBaseCommands extends DrushCommands {

  /**
   * Constructs a PivBaseCommands object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QueueFactory $queue,
  ) {
    parent::__construct();
  }

  /**
   * Fix terms migrations.
   *
   * @usage piv_base:queue-media-thumbnails-download
   *   Fix terms migrations.
   *
   * @command piv_base:queue-media-thumbnails-download
   */
  public function queueMediaThumbnailsDownload() {
    $files = $this->entityTypeManager->getStorage('file')->getQuery()
      ->condition('uri', 'public://media-icons/generic/video.png')
      ->accessCheck(FALSE)
      ->execute();
    if (!count($files)) {
      $this->logger()->success(dt('No media to update.'));
      return;
    }

    $medias = $this->entityTypeManager->getStorage('media')->getQuery()
      ->condition('thumbnail', $files, 'IN')
      ->accessCheck(FALSE)
      ->execute();
    if (!count($files)) {
      $this->logger()->success(dt('No media to update.'));
      return;
    }

    $queue = $this->queue->get('media_entity_thumbnail');
    foreach ($medias as $id) {
      $queue->createItem(['id' => $id]);
    }

    $this->logger()->success('{count} medias added to queue. Run queue with drush queue:run media_entity_thumbnail', [
      'count' => count($medias),
    ]);
  }

}
