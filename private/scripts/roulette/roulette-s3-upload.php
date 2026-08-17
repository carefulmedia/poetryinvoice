<?php

/**
 * @file
 * Upload Poem Roulette JSON caches to S3.
 */

use Aws\S3\S3Client;

/**
 * Returns S3 credentials from Pantheon secrets or environment variables.
 *
 * @return array{key: string, secret: string}|null
 *   Credentials array, or NULL when unavailable.
 */
function roulette_s3_credentials(): ?array {
  $key = FALSE;
  $secret = FALSE;

  if (function_exists('pantheon_get_secret')) {
    $key = pantheon_get_secret('S3FS_ACCESS_KEY') ?: FALSE;
    $secret = pantheon_get_secret('S3FS_SECRET_KEY') ?: FALSE;
  }

  if (!$key || !$secret) {
    $key = getenv('S3FS_ACCESS_KEY') ?: $key;
    $secret = getenv('S3FS_SECRET_KEY') ?: $secret;
  }

  if (!$key || !$secret) {
    return NULL;
  }

  return [
    'key' => $key,
    'secret' => $secret,
  ];
}

/**
 * Uploads generated roulette JSON files to S3.
 *
 * @param string $data_dir
 *   Directory containing cached_*.json files.
 *
 * @return bool
 *   TRUE when all files were uploaded, FALSE when upload was skipped.
 */
function roulette_upload_cache_to_s3(string $data_dir): bool {
  $credentials = roulette_s3_credentials();
  if (!$credentials) {
    echo "Skipping S3 upload: S3FS credentials not configured.\n";
    return FALSE;
  }

  $bucket = getenv('ROULETTE_S3_BUCKET') ?: 'piv-lvp-images-and-files';
  $region = getenv('ROULETTE_S3_REGION') ?: 'us-east-2';
  $prefix = rtrim(getenv('ROULETTE_S3_PREFIX') ?: 'roulette/cached', '/');

  $files = glob($data_dir . '/cached_*.json') ?: [];
  if (!$files) {
    echo "No roulette cache files found in {$data_dir}.\n";
    return FALSE;
  }

  $root = dirname(__DIR__, 3);
  require_once $root . '/vendor/autoload.php';

  $client = new S3Client([
    'version' => 'latest',
    'region' => $region,
    'credentials' => $credentials,
  ]);

  foreach ($files as $path) {
    $filename = basename($path);
    $key = $prefix . '/' . $filename;
    $client->putObject([
      'Bucket' => $bucket,
      'Key' => $key,
      'SourceFile' => $path,
      'ContentType' => 'application/json',
      'CacheControl' => 'max-age=3600, public',
    ]);
    echo "Uploaded {$filename} to s3://{$bucket}/{$key}\n";
  }

  return TRUE;
}
