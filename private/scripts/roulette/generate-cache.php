<?php

/**
 * @file
 * Regenerate roulette JSON caches after code sync/deploy.
 */

$root = dirname(__DIR__, 3);
$script = $root . '/web/roulette/cached/generate-cache.sh';

if (!is_readable($script)) {
  echo "Roulette cache script not found.\n";
  exit(1);
}

passthru('bash ' . escapeshellarg($script), $exit_code);
exit($exit_code);
