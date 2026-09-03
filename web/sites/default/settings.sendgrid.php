<?php

/**
 * @file
 * SendGrid API key from environment (never commit the key).
 */

$sendgrid_api_key = FALSE;
if (function_exists('pantheon_get_secret')) {
  $sendgrid_api_key = pantheon_get_secret('SENDGRID_API_KEY') ?: FALSE;
}
if (!$sendgrid_api_key) {
  $sendgrid_api_key = getenv('SENDGRID_API_KEY') ?: FALSE;
}
if ($sendgrid_api_key) {
  $config['sendgrid_integration.settings']['apikey'] = $sendgrid_api_key;
}
