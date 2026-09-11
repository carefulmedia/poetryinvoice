<?php

namespace Drupal\piv_base;

use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;

/**
 * Stores poem favourites on the user account (logged-in users only).
 */
final class PoemFavouritesStorage {

  public const MODULE = 'piv_base';

  public const DATA_KEY = 'poem_favourites';

  /**
   * Loads favourites for a user account.
   */
  public static function load(UserInterface $user): array {
    $raw = self::userData()->get(self::MODULE, (int) $user->id(), self::DATA_KEY);
    if (!$raw) {
      return [];
    }
    $decoded = json_decode($raw, TRUE);
    return is_array($decoded) ? self::normalize($decoded) : [];
  }

  /**
   * Saves favourites for a user account.
   */
  public static function save(UserInterface $user, array $favourites): array {
    $normalized = self::normalize($favourites);
    self::userData()->set(
      self::MODULE,
      (int) $user->id(),
      self::DATA_KEY,
      json_encode($normalized),
    );
    return $normalized;
  }

  /**
   * Normalizes favourite entries to a consistent shape.
   */
  public static function normalize(array $favourites): array {
    $seen = [];
    $normalized = [];

    foreach ($favourites as $poem) {
      if (!is_array($poem)) {
        continue;
      }
      $path = self::normalizePoemPath($poem['poemPath'] ?? $poem['poemId'] ?? '');
      if ($path === '' || isset($seen[$path])) {
        continue;
      }
      $seen[$path] = TRUE;
      $normalized[] = [
        'poemId' => $path,
        'poemPath' => $path,
        'title' => trim((string) ($poem['title'] ?? '')),
        'poet' => trim((string) ($poem['poet'] ?? '')),
      ];
    }

    return $normalized;
  }

  /**
   * Normalizes a poem path for storage and comparison.
   */
  public static function normalizePoemPath(?string $path): string {
    if ($path === NULL || $path === '') {
      return '';
    }

    if (preg_match('#^https?://#i', $path)) {
      $parts = parse_url($path);
      $path = $parts['path'] ?? '';
    }

    $path = rtrim($path, '/');
    return $path === '' ? '/' : $path;
  }

  /**
   * Returns the user data storage service.
   */
  protected static function userData(): UserDataInterface {
    return \Drupal::service('user.data');
  }

}
