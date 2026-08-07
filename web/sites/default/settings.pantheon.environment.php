<?php

/**
 * @file
 * PIV site-specific Pantheon environment settings.
 */

if (!isset($_ENV['PANTHEON_ENVIRONMENT'])) {
  return;
}

// Legacy file redirects to S3. Platform.sh handled these at the edge; Pantheon
// ignores .htaccess, so perform redirects before Drupal bootstrap.
if (PHP_SAPI !== 'cli' && !empty($_SERVER['REQUEST_URI'])) {
  $s3_base = 'https://piv-lvp-images-and-files.s3.us-east-2.amazonaws.com';
  $uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
  if (is_string($uri_path)) {
    if (preg_match('#^/sites/default/files/(?!styles/)(.+)$#', $uri_path, $matches)) {
      header('HTTP/1.1 301 Moved Permanently');
      header('Location: ' . $s3_base . '/public/' . $matches[1]);
      exit;
    }
    if (preg_match('#^/downloads/(.+)$#', $uri_path, $matches)) {
      header('HTTP/1.1 301 Moved Permanently');
      header('Location: ' . $s3_base . '/downloads/' . $matches[1]);
      exit;
    }
    if (preg_match('#^/images/(.+)$#', $uri_path, $matches)) {
      header('HTTP/1.1 301 Moved Permanently');
      header('Location: ' . $s3_base . '/images/' . $matches[1]);
      exit;
    }
    if (preg_match('#^/telechargements/(.+)$#', $uri_path, $matches)) {
      header('HTTP/1.1 301 Moved Permanently');
      header('Location: ' . $s3_base . '/telechargements/' . $matches[1]);
      exit;
    }
    // VOICES/VOIX journal flipbooks (static HTML on S3). Proxy on the site
    // domain so FlowPaper license validation passes (not poetryinvoice.ca → S3).
    if (preg_match('#^/journal/(journal-\d+)$#', $uri_path, $journal_matches)) {
      $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
      $location = '/journal/' . $journal_matches[1] . '/';
      if ($query) {
        $location .= '?' . $query;
      }
      header('HTTP/1.1 301 Moved Permanently');
      header('Cache-Control: no-store');
      header('Location: ' . $location);
      exit;
    }
    if (preg_match('#^/journal/journal-\d+(?:/|$)#', $uri_path)) {
      require_once __DIR__ . '/pantheon-journal-proxy.inc';
      piv_pantheon_proxy_journal($uri_path, $s3_base);
    }
  }
}

$pantheon_env = $_ENV['PANTHEON_ENVIRONMENT'];

// Writable paths on Pantheon (Platform.sh set these explicitly; Pantheon upstream
// usually does too, but define them here so uploads work after env:wipe).
if (!isset($settings['file_private_path'])) {
  $settings['file_private_path'] = 'sites/default/files/private';
}
if (!isset($settings['file_temp_path'])) {
  $settings['file_temp_path'] = sys_get_temp_dir();
}

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
// Pantheon secrets use type=runtime and pantheon_get_secret(), not getenv().
$s3_access = FALSE;
$s3_secret = FALSE;
if (function_exists('pantheon_get_secret')) {
  $s3_access = pantheon_get_secret('S3FS_ACCESS_KEY') ?: FALSE;
  $s3_secret = pantheon_get_secret('S3FS_SECRET_KEY') ?: FALSE;
}
if (!$s3_access || !$s3_secret) {
  $s3_access = getenv('S3FS_ACCESS_KEY') ?: $s3_access;
  $s3_secret = getenv('S3FS_SECRET_KEY') ?: $s3_secret;
}
if ($s3_access && $s3_secret) {
  $settings['s3fs.access_key'] = $s3_access;
  $settings['s3fs.secret_key'] = $s3_secret;
  $settings['s3fs.use_s3_for_public'] = TRUE;
  $settings['s3fs.use_s3_for_private'] = TRUE;
  $config['s3fs.settings']['bucket'] = 'piv-lvp-images-and-files';
  $config['s3fs.settings']['public_folder'] = 'public';
  $config['s3fs.settings']['private_folder'] = 'private';
  $config['s3fs.settings']['region'] = 'us-east-2';
}
