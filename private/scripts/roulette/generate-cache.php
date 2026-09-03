<?php

/**
 * @file
 * Regenerate Poem Roulette JSON caches from Drupal views and upload to S3.
 */

require_once __DIR__ . '/roulette-s3-upload.php';

$root = dirname(__DIR__, 3);
$script = $root . '/web/roulette/cached/generate-cache.sh';

if (!is_readable($script)) {
  echo "Roulette cache script not found.\n";
  exit(1);
}

passthru('bash ' . escapeshellarg($script), $exit_code);
if ($exit_code !== 0) {
  exit($exit_code);
}

$data_dir = getenv('ROULETTE_CACHE_DIR');
if (!$data_dir) {
  $data_dir = $root . '/web/sites/default/files/roulette-cache';
}

if (!is_dir($data_dir)) {
  echo "Roulette cache directory not found: {$data_dir}\n";
  exit(1);
}

roulette_upload_cache_to_s3($data_dir);
exit(0);
