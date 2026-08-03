<?php

/**
 * @file
 * PIV site-specific Pantheon environment settings.
 */

if (!isset($_ENV['PANTHEON_ENVIRONMENT'])) {
  return;
}

$pantheon_env = $_ENV['PANTHEON_ENVIRONMENT'];

// Production (live).
if ($pantheon_env === 'live') {
  $config['system.logging']['error_level'] = 'hide';
  $config['reroute_email.settings']['enable'] = FALSE;
  $config['language.negotiation']['url']['source'] = 'domain';
  $config['language.negotiation']['url']['domains']['en'] = 'poetryinvoice.ca';
  $config['language.negotiation']['url']['domains']['fr'] = 'lesvoixdelapoesie.ca';
  $config['simple_sitemap_engines.settings']['index_now_enabled'] = TRUE;
  $config['simple_sitemap_engines.settings']['enabled'] = TRUE;

  if (PHP_SAPI !== 'cli') {
    ini_set('memory_limit', '512M');
  }
}
// Development and staging environments.
else {
  $config['system.logging']['error_level'] = 'verbose';
  $config['reroute_email.settings']['enable'] = TRUE;
  $config['language.negotiation']['url']['source'] = 'path_prefix';
}

// S3 file storage when credentials are set on the environment.
$s3_access = getenv('S3FS_ACCESS_KEY') ?: FALSE;
$s3_secret = getenv('S3FS_SECRET_KEY') ?: FALSE;
if ($s3_access && $s3_secret) {
  $settings['s3fs.access_key'] = $s3_access;
  $settings['s3fs.secret_key'] = $s3_secret;
  $settings['s3fs.use_s3_for_public'] = TRUE;
  $settings['s3fs.use_s3_for_private'] = TRUE;
  $config['s3fs.settings']['bucket'] = 'piv-lvp-images-and-files';
  $config['s3fs.settings']['public_folder'] = 'public';
  $config['s3fs.settings']['private_folder'] = 'private';
  $config['s3fs.settings']['region'] = 'ca-central-1';
}
