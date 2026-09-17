<?php

/**
 * @file
 * Rebuild Drupal caches after deploy (registers new routes, etc.).
 */

passthru('drush cr 2>&1', $exit_code);
exit($exit_code);
