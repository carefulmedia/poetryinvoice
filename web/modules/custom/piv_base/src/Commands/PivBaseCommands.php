<?php

namespace Drupal\piv_base\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drush\Commands\DrushCommands;

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
   * Regenerate Poem Roulette JSON caches and upload them to S3.
   *
   * @usage piv_base:roulette-generate-cache
   *   Fetch Drupal roulette views and refresh local/S3 JSON caches.
   *
   * @command piv_base:roulette-generate-cache
   * @aliases roulette-generate-cache
   */
  public function rouletteGenerateCache(): void {
    $script = dirname(DRUPAL_ROOT) . '/private/scripts/roulette/generate-cache.php';
    if (!is_readable($script)) {
      throw new \RuntimeException('Roulette cache script not found.');
    }

    passthru('php ' . escapeshellarg($script), $exit_code);
    if ($exit_code !== 0) {
      throw new \RuntimeException('Roulette cache generation failed.');
    }

    $this->logger()->success(dt('Poem Roulette caches regenerated.'));
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
