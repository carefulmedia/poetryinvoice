<?php

namespace Drupal\piv_mail;

use Drupal\Core\Database\Connection;

/**
 * Queue emails.
 */
class PivMailQueue {

  /**
   * The table name.
   *
   * @var string
   */
  const TABLE_NAME = 'piv_mail_queue';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a PivMailQueue object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Claim a item from the queue.
   */
  public function claimItem($lease_time = 300) {
    $time = time();
    $item = $this->connection
      ->select(static::TABLE_NAME, 't')
      ->condition('date', $time, '<=')
      ->condition('expire', $time, '<=')
      ->fields('t')
      ->range(0, 1)
      ->execute()
      ->fetch();

    if (empty($item)) {
      return NULL;
    }

    $update = $this->connection
      ->update(static::TABLE_NAME)
      ->fields([
        'expire' => time() + $lease_time,
      ])
      ->condition('id', $item->id);

    // If there are affected rows, this update succeeded.
    if ($update->execute()) {
      $item->data = unserialize($item->data, ['allowed_classes' => FALSE]);
      return $item;
    }
    return NULL;
  }

  /**
   * Create a item.
   */
  public function createItem($key, $data, $date) {
    return $this->connection
      ->merge(self::TABLE_NAME)
      ->key('key', $key)
      ->fields([
        'data' => serialize($data),
        'date' => $date,
      ])
      ->execute();
  }

  /**
   * Delete a item.
   */
  public function deleteItem($item) {
    return $this->connection
      ->delete(static::TABLE_NAME)
      ->condition('id', $item->id)
      ->execute();
  }

  /**
   * Delete an item.
   */
  public function deleteItemByKey($key) {
    return $this->connection
      ->delete(static::TABLE_NAME)
      ->condition('key', $key)
      ->execute();
  }

  /**
   * Delete all items for node.
   *
   * This considers the key is "something:nid".
   */
  public function deleteItemsByNid($nid) {
    return $this->connection
      ->delete(static::TABLE_NAME)
      ->condition('key', "%:$nid", 'LIKE')
      ->execute();
  }

}
