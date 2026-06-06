<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/functions.php';

function session_start_secure(): void
{
  if (session_status() === PHP_SESSION_ACTIVE) {
    return;
  }

  $appEnv = (string) env('APP_ENV', 'dev');
  $isProd = ($appEnv === 'prod');

  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

  // Évite les collisions de session entre projets sur localhost.
  session_name('MALISHOPSESSID');

  // Renforce la session même si php.ini reste permissif.
  ini_set('session.use_strict_mode', '1');
  ini_set('session.use_only_cookies', '1');
  ini_set('session.use_trans_sid', '0');
  ini_set('session.cookie_httponly', '1');
  ini_set('session.cookie_samesite', 'Lax');
  if ($isProd && $isHttps) {
    ini_set('session.cookie_secure', '1');
  }

  $sessionLifetime = (int) env('CLIENT_SESSION_LIFETIME', 2592000);
  if ($sessionLifetime <= 0) {
    $sessionLifetime = 2592000;
  }

  ini_set('session.gc_maxlifetime', (string) $sessionLifetime);

  session_set_cookie_params(array(
    'lifetime' => $sessionLifetime,
    'path' => app_base_url(),
    'secure' => ($isProd && $isHttps),
    'httponly' => true,
    'samesite' => 'Lax',
  ));

  session_start();
}
