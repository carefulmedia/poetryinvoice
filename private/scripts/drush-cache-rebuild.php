<?php

/**
 * @file
 * Rebuild Drupal caches after deploy (registers new routes, etc.).
 */

passthru('drush updb -y 2>&1', $exit_code);
if ($exit_code !== 0) {
  exit($exit_code);
}
passthru('drush cr 2>&1', $exit_code);
exit($exit_code);
