<?php

declare(strict_types=1);

namespace Drupal\piv_contest;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\piv_logs\Entity\Log;

/**
 * Notification logs helper service.
 */
final class NotificationLogs {

  /**
   * Constructs a NotificationLogs object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CacheBackendInterface $cache,
  ) {}

  /**
   * Return the timestamp for the latest revision.
   *
   * This only consider machine created logs, for that the
   * field_created_by_piv_mail is used.
   */
  public function getLastSendTimestamp(Log $log) : string|NULL {
    $cid = "piv_log:last_send:{$log->id()}";
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    $storage = $this->entityTypeManager->getStorage('piv_log');
    $vids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('id', $log->id())
      ->condition('field_created_by_piv_mail', 1)
      ->sort('revision_id', 'DESC')
      ->execute();

    $value = NULL;
    if ($vids) {
      $vid = reset($vids);
      $value = $storage->loadRevision($vid)->changed->value;
    }

    $this->cache->set($cid, $value, CacheBackendInterface::CACHE_PERMANENT, $log->getCacheTags());
    return $value;
  }

  /**
   * Return the log for a competition entry.
   *
   * There should be only one log for each competition entry, these are
   * revisionable so the same one should be updated. If none exists
   * then one gets created.
   */
  public function getLogsForCompetitionEntry($competition_entry, $competition_level, $notification_type, $langcode) {
    $properties = [
      'field_competition_entry' => $competition_entry,
      'field_competition_level' => $competition_level,
      'field_notification_type' => $notification_type,
      'field_stream_language' => $langcode,
    ];
    $logs = $this->doGetLogs($properties);
    if ($logs) {
      return reset($logs);
    }
    return $this->entityTypeManager
      ->getStorage('piv_log')
      ->create($properties + [
        'bundle' => 'competition_entry_notifications',
      ]);
  }

  /**
   * Get all notification logs for a competition's entries.
   *
   * @return array
   *   Keyed by "entryId:level:notificationType:langcode" => Log entity.
   */
  public function getLogsForCompetition($competition_id): array {
    $sessions = $this->entityTypeManager->getStorage('judging_session')
      ->loadByProperties(['field_competition' => $competition_id]);

    $entry_ids = [];
    foreach ($sessions as $session) {
      foreach ($session->field_competition_entries as $ref) {
        if ($ref->target_id) {
          $entry_ids[$ref->target_id] = $ref->target_id;
        }
      }
    }

    if (empty($entry_ids)) {
      return [];
    }

    $logs = $this->entityTypeManager->getStorage('piv_log')
      ->loadByProperties([
        'bundle' => 'competition_entry_notifications',
        'field_competition_entry' => array_values($entry_ids),
      ]);

    $result = [];
    foreach ($logs as $log) {
      $key = implode(':', [
        $log->field_competition_entry->target_id,
        $log->field_competition_level->value,
        $log->field_notification_type->value,
        $log->field_stream_language->target_id,
      ]);
      $result[$key] = $log;
    }

    return $result;
  }

  /**
   * Get logs.
   */
  private function doGetLogs(array $properties): array {
    $log_storage = $this->entityTypeManager->getStorage('piv_log');
    $properties['bundle'] = 'competition_entry_notifications';
    return $log_storage->loadByProperties($properties);
  }

}
