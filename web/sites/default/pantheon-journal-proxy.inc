<?php

/**
 * @file
 * Proxy VOICES/VOIX journal flipbooks from S3 on the site domain.
 *
 * FlowPaper licenses are tied to poetryinvoice.ca. Redirecting to S3 breaks
 * the license check; proxying keeps the browser on the site hostname.
 */

/**
 * Proxies /journal/journal-N/* requests to the S3 flipbook bucket.
 */
function piv_pantheon_proxy_journal(string $uri_path, string $s3_base): void {
  if (!preg_match('#^/journal/(journal-\d+)(?:/(.*))?$#', $uri_path, $matches)) {
    return;
  }

  $issue = $matches[1];
  $subpath = $matches[2] ?? '';
  $subpath = rtrim($subpath, '/');

  if ($subpath === '') {
    $object_path = "/journal/{$issue}/index.html";
  }
  else {
    $object_path = "/journal/{$issue}/{$subpath}";
  }

  $query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
  $url = $s3_base . $object_path . ($query ? '?' . $query : '');

  if (!function_exists('curl_init')) {
    header('HTTP/1.1 503 Service Unavailable');
    exit;
  }

  $mime = piv_pantheon_journal_mime($object_path);
  $stream = piv_pantheon_journal_should_stream($object_path);

  $ch = curl_init($url);
  $status = 0;
  curl_setopt_array($ch, [
    CURLOPT_FOLLOWLOCATION => TRUE,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 300,
    CURLOPT_HEADERFUNCTION => static function ($curl, string $header_line) use (&$status): int {
      if (preg_match('#^HTTP/\S+\s(\d{3})#', $header_line, $code)) {
        $status = (int) $code[1];
      }
      return strlen($header_line);
    },
  ]);

  if ($stream) {
    $headers_sent = FALSE;
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($curl, string $data) use (&$status, &$headers_sent, $mime): int {
      if (!$headers_sent) {
        if ($status < 200 || $status >= 300) {
          header('HTTP/1.1 ' . ($status >= 400 ? $status : 502));
          return -1;
        }
        header('HTTP/1.1 200 OK');
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=3600');
        $headers_sent = TRUE;
      }
      echo $data;
      return strlen($data);
    });
    curl_exec($ch);
    curl_close($ch);
    exit;
  }

  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  $body = curl_exec($ch);
  $error = curl_errno($ch);
  curl_close($ch);

  if ($error || $status < 200 || $status >= 300 || $body === FALSE) {
    header('HTTP/1.1 ' . ($status >= 400 ? $status : 502));
    exit;
  }

  header('HTTP/1.1 200 OK');
  header('Content-Type: ' . $mime);
  header('Cache-Control: public, max-age=3600');
  echo $body;
  exit;
}

/**
 * Whether to stream the S3 response directly (large binary assets).
 */
function piv_pantheon_journal_should_stream(string $path): bool {
  $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  return in_array($extension, ['pdf', 'bin'], TRUE);
}

/**
 * Maps S3 object paths to MIME types for journal flipbook assets.
 */
function piv_pantheon_journal_mime(string $path): string {
  $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  return match ($extension) {
    'html', 'htm' => 'text/html; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    'css' => 'text/css; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'webp' => 'image/webp',
    'pdf' => 'application/pdf',
    'woff', 'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'eot' => 'application/vnd.ms-fontobject',
    default => 'application/octet-stream',
  };
}
