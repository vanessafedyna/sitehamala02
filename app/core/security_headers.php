<?php
declare(strict_types=1);

if (!function_exists('app_is_https_request')) {
  function app_is_https_request(): bool
  {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
      || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
  }
}

if (!function_exists('app_is_local_host')) {
  function app_is_local_host(string $host): bool
  {
    $h = strtolower(trim($host));
    if ($h === '') return true;
    if ($h === 'localhost' || $h === '127.0.0.1' || $h === '::1') return true;
    if (str_ends_with($h, '.localhost')) return true;
    return false;
  }
}

if (!function_exists('app_should_send_csp')) {
  function app_should_send_csp(): bool
  {
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
    $path = strtolower($path);

    if ($path === '') return true;
    if (strpos($path, '/public/api/') !== false) return false;
    if (str_ends_with($path, '.csv')) return false;
    if (str_ends_with($path, '.xml')) return false;
    if (str_ends_with($path, '.txt')) return false;

    return true;
  }
}

if (!function_exists('send_global_security_headers')) {
  function send_global_security_headers(): void
  {
    if (PHP_SAPI === 'cli') return;
    if (headers_sent()) return;

    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');

    if (app_should_send_csp()) {
      // CSP progressive: suffisamment stricte pour durcir, assez souple pour ne pas casser l'existant.
      $csp = array(
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
        "object-src 'none'",
        "script-src 'self' 'unsafe-inline'",
        "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com",
        "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        "img-src 'self' data: blob: https: http:",
        "connect-src 'self'",
      );
      header('Content-Security-Policy: ' . implode('; ', $csp));
    }

    // HSTS uniquement en HTTPS et hors environnement local.
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (app_is_https_request() && !app_is_local_host($host)) {
      header('Strict-Transport-Security: max-age=15552000');
    }
  }
}

