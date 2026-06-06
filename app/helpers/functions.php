<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
env_load();

if (!function_exists('base_path')) {
  function base_path(string $path = ''): string
  {
    $root = dirname(__DIR__, 2);
    return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
  }
}

if (!function_exists('env')) {
  function env(string $key, $default = null)
  {
    $value = getenv($key);
    if ($value === false || $value === '') {
      return $default;
    }
    return $value;
  }
}

if (!function_exists('app_base_url')) {
  function app_base_url(): string
  {
    $envBase = (string) env('APP_BASE_URL', '');
    if ($envBase !== '') {
      $base = $envBase;
      if ($base[0] !== '/') {
        $base = '/' . $base;
      }
      return rtrim($base, '/') . '/';
    }

    $fallback = '/';

    $doc_root_fs = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    $project_root_fs = realpath(base_path(''));

    if ($doc_root_fs && $project_root_fs) {
      $doc_root_fs_norm = rtrim(str_replace('\\', '/', (string) $doc_root_fs), '/');
      $project_root_fs_norm = rtrim(str_replace('\\', '/', (string) $project_root_fs), '/');

      if (strpos($project_root_fs_norm, $doc_root_fs_norm) === 0) {
        $relative = substr($project_root_fs_norm, strlen($doc_root_fs_norm));
        $relative = '/' . ltrim((string) $relative, '/');
        return rtrim($relative, '/') . '/';
      }
    }

    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
    if ($scriptName !== '') {
      $scriptDir = str_replace('\\', '/', dirname($scriptName));
      if ($scriptDir !== '' && $scriptDir !== '.' && $scriptDir !== '/') {
        return rtrim($scriptDir, '/') . '/';
      }
    }

    return $fallback;
  }
}

if (!function_exists('password_min_length')) {
  function password_min_length(): int
  {
    return 10;
  }
}

if (!function_exists('password_meets_policy')) {
  function password_meets_policy(string $password): bool
  {
    $length = function_exists('mb_strlen')
      ? (int) mb_strlen($password, 'UTF-8')
      : strlen($password);
    return $length >= password_min_length();
  }
}

if (!function_exists('password_policy_message')) {
  function password_policy_message(): string
  {
    return 'Le mot de passe doit contenir au moins ' . password_min_length() . ' caracteres.';
  }
}

if (!function_exists('app_public_url')) {
  function app_public_url(): string
  {
    $envPublic = trim((string) env('APP_PUBLIC_URL', ''));
    if ($envPublic !== '') {
      return rtrim($envPublic, '/');
    }
    if (defined('APP_PUBLIC_URL')) {
      return rtrim((string) APP_PUBLIC_URL, '/');
    }
    if (defined('SITE_URL')) {
      return rtrim((string) SITE_URL, '/');
    }
    return 'http://localhost';
  }
}

if (!function_exists('e')) {
  function e($value): string
  {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('base_url')) {
  function base_url(string $path = ''): string
  {
    return app_base_url() . ltrim($path, '/');
  }
}

if (!function_exists('absolute_url')) {
  function absolute_url(string $path = ''): string
  {
    return app_public_url() . '/' . ltrim($path, '/');
  }
}

if (!function_exists('redirect')) {
  function redirect(string $path, int $status = 302): void
  {
    $target = (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))
      ? $path
      : base_url($path);

    header('Location: ' . $target, true, $status);
    exit;
  }
}

if (!function_exists('log_error')) {
  function log_error(string $message): void
  {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    $logFile = base_path('app/logs/app.log');
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
      @mkdir($dir, 0775, true);
    }
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
  }
}

if (!function_exists('db_table_columns')) {
  /**
   * Retourne les colonnes d'une table avec cache statique (durée de la requête PHP).
   *
   * @return string[]
   */
  function db_table_columns(PDO $pdo, string $table): array
  {
    static $cache = array();

    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: '';
    if ($table === '') {
      return array();
    }

    $pdoKey = (string) spl_object_id($pdo);
    if (isset($cache[$pdoKey][$table]) && is_array($cache[$pdoKey][$table])) {
      return $cache[$pdoKey][$table];
    }

    try {
      $rows = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) ?: array();
      $cols = array();
      foreach ($rows as $r) {
        if (!empty($r['Field'])) {
          $cols[] = (string) $r['Field'];
        }
      }
      $cache[$pdoKey][$table] = $cols;
      return $cols;
    } catch (Throwable $e) {
      $cache[$pdoKey][$table] = array();
      return array();
    }
  }
}

if (!function_exists('db_has_column')) {
  function db_has_column(PDO $pdo, string $table, string $column): bool
  {
    $column = trim($column);
    if ($column === '') {
      return false;
    }
    return in_array($column, db_table_columns($pdo, $table), true);
  }
}

/* Helpers utilitaires génériques */
if (!function_exists('slugify')) {
  /**
   * Slug simple et stable.
   */
  function slugify(string $value): string
  {
    $value = trim($value);
    if ($value === '') return '';

    $v = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);

    // translit si possible (accents -> ascii)
    if (function_exists('iconv')) {
      $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
      if (is_string($t) && $t !== '') {
        $v = $t;
      }
    }

    $v = preg_replace('/[^a-z0-9]+/i', '-', $v) ?: '';
    $v = trim($v, '-');
    $v = preg_replace('/-+/', '-', $v) ?: '';
    return $v;
  }
}

if (!function_exists('is_debug_allowed')) {
  function is_debug_allowed(): bool
  {
    if (!defined('APP_DEBUG') || APP_DEBUG !== true) {
      return false;
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip === '') {
      return false;
    }

    $allowed = defined('APP_DEBUG_ALLOWED_IPS') ? (array) APP_DEBUG_ALLOWED_IPS : array('127.0.0.1', '::1');
    return in_array($ip, $allowed, true);
  }
}

// =========================================================
// JSON / API helpers (V1)
// =========================================================

if (!function_exists('json_response')) {
  /**
   * Envoie une réponse JSON standardisée et termine le script.
   *
   * @param array<string,mixed> $payload
   */
  function json_response(int $statusCode, array $payload): void
  {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    echo json_encode(
      $payload,
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
  }
}

if (!function_exists('json_read_body')) {
  /**
   * Lit le body JSON et retourne un tableau (ou [] si invalide).
   *
   * @return array<string,mixed>
   */
  function json_read_body(int $maxBytes = 20000): array
  {
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || $raw === '') {
      return array();
    }
    if (strlen($raw) > $maxBytes) {
      return array();
    }

    try {
      $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
      return array();
    }

    return is_array($data) ? $data : array();
  }
}

if (!function_exists('rate_limit_allow')) {
  /**
   * Rate-limit simple (session). Retourne true si autorisé.
   */
  function rate_limit_allow(string $key, int $limit, int $windowSeconds): bool
  {
    if ($limit < 1) return true;
    if ($windowSeconds < 1) $windowSeconds = 1;

    if (function_exists('session_start_secure')) {
      session_start_secure();
    } elseif (session_status() !== PHP_SESSION_ACTIVE) {
      @session_start();
    }

    if (!isset($_SESSION['_rl']) || !is_array($_SESSION['_rl'])) {
      $_SESSION['_rl'] = array();
    }

    $now = time();
    $bucket = $_SESSION['_rl'][$key] ?? array();
    if (!is_array($bucket)) $bucket = array();

    // Garder seulement les hits dans la fenêtre.
    $bucket = array_values(array_filter($bucket, fn ($t) => is_int($t) && $t >= ($now - $windowSeconds)));

    if (count($bucket) >= $limit) {
      $_SESSION['_rl'][$key] = $bucket;
      return false;
    }

    $bucket[] = $now;
    $_SESSION['_rl'][$key] = $bucket;
    return true;
  }
}
