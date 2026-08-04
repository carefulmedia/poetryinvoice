<?php

/**
 * @file
 * Shared roulette cache directory (writable on Pantheon).
 */

/**
 * Returns the directory where roulette JSON caches are stored.
 */
function roulette_cache_dir(): string {
  static $dir = NULL;
  if ($dir !== NULL) {
    return $dir;
  }

  if (!empty($_ENV['ROULETTE_CACHE_DIR'])) {
    $dir = $_ENV['ROULETTE_CACHE_DIR'];
  }
  else {
    $dir = dirname(__DIR__, 2) . '/sites/default/files/roulette-cache';
  }

  if (!is_dir($dir)) {
    @mkdir($dir, 0775, TRUE);
  }

  return $dir;
}

/**
 * Loads roulette cache JSON as an array of objects.
 */
function roulette_load_cache(string $filename): array {
  $path = roulette_cache_dir() . '/' . $filename;
  if (!is_readable($path)) {
    return [];
  }

  $json = file_get_contents($path);
  $nodes = json_decode($json);
  if (!is_array($nodes) && !($nodes instanceof Traversable)) {
    return [];
  }

  return is_array($nodes) ? $nodes : iterator_to_array($nodes);
}
