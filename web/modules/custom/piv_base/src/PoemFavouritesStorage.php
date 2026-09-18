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
   * Adds a single favourite without dropping existing entries.
   */
  public static function add(UserInterface $user, array $poem): array {
    $existing = self::load($user);
    return self::save($user, array_merge($existing, [$poem]));
  }

  /**
   * Removes a single favourite by poem path.
   */
  public static function remove(UserInterface $user, string $poem_path): array {
    $normalized_path = self::normalizePoemPath($poem_path);
    if ($normalized_path === '') {
      return self::load($user);
    }
    $existing = self::load($user);
    $filtered = array_values(array_filter(
      $existing,
      static fn (array $poem): bool => self::normalizePoemPath($poem['poemPath'] ?? $poem['poemId'] ?? '') !== $normalized_path,
    ));
    return self::save($user, $filtered);
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
