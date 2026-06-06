<?php
declare(strict_types=1);

// Common bootstrap for public JSON API endpoints.

require_once __DIR__ . '/../../app/bootstrap.php';
auth_start();
require_once dirname(__DIR__, 2) . '/app/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/Logger.php';

function api_pdo(): PDO
{
  try {
    return db();
  } catch (Throwable $e) {
    if (class_exists('Logger')) {
      Logger::error('api_db_connection_failed', array(
        'error' => $e->getMessage(),
      ));
    } else {
      log_error('[API][DB] ' . $e->getMessage());
    }
    json_response(500, array('ok' => false, 'error' => 'Erreur serveur'));
    exit;
  }
}

function api_base_path(): string
{
  return app_base_url();
}

function api_current_user(): ?array
{
  return current_user();
}

/**
 * @return array<string,mixed>
 */
function api_require_user(): array
{
  $u = api_current_user();
  if (!$u) {
    json_response(401, array('ok' => false, 'message' => 'Non authentifie.'));
    exit;
  }
  return $u;
}

function api_require_csrf(?string $token = null): void
{
  if ($token === null || $token === '') {
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? ''));
  }

  if (!csrf_verify($token)) {
    json_response(403, array('ok' => false, 'message' => 'Session expirée. Rechargez la page.'));
    exit;
  }
}

function api_client_ip(): string
{
  $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
  return $ip !== '' ? $ip : 'unknown';
}

function api_require_method(string $method): void
{
  $m = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
  $method = strtoupper($method);
  if ($m !== $method) {
    header('Allow: ' . $method);
    json_response(405, array('ok' => false, 'message' => 'Methode non autorisee.'));
    exit;
  }
}

function api_rate_limit(string $name, int $limit, int $windowSeconds): void
{
  $ip = api_client_ip();
  $scopeKey = $name . '|' . $ip;

  $allowed = null;
  try {
    $allowed = api_rate_limit_mysql_allow($scopeKey, $limit, $windowSeconds);
  } catch (Throwable $e) {
    // keep compatibility path below
    if (class_exists('Logger')) {
      Logger::error('api_rate_limit_mysql_failed', array(
        'key' => $name,
        'ip' => $ip,
        'error' => $e->getMessage(),
      ));
    } else {
      log_error('[API][rate_limit] ' . $e->getMessage());
    }
  }

  if ($allowed === null) {
    $legacyKey = 'api:' . $name . ':' . $ip;
    $allowed = rate_limit_allow($legacyKey, $limit, $windowSeconds);
  }

  if (!$allowed) {
    json_response(429, array('ok' => false, 'message' => 'Trop de requetes. Reessayez plus tard.'));
    exit;
  }
}

function api_rate_limit_mysql_allow(string $scopeKey, int $limit, int $windowSeconds): ?bool
{
  if ($limit < 1) return true;
  if ($windowSeconds < 1) $windowSeconds = 1;

  $pdo = api_pdo();
  if (!api_rate_limits_table_exists($pdo)) {
    return null;
  }

  $now = time();
  $windowStart = intdiv($now, $windowSeconds) * $windowSeconds;

  $sql = 'INSERT INTO rate_limits (scope_key, window_start, count, updated_at)
          VALUES (:scope_key, :window_start, 1, NOW())
          ON DUPLICATE KEY UPDATE
            window_start = IF(window_start = VALUES(window_start), window_start, VALUES(window_start)),
            count = IF(window_start = VALUES(window_start), count + 1, 1),
            updated_at = NOW()';

  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(
      ':scope_key' => $scopeKey,
      ':window_start' => $windowStart,
    ));

    $read = $pdo->prepare('SELECT count FROM rate_limits WHERE scope_key = :scope_key LIMIT 1');
    $read->execute(array(':scope_key' => $scopeKey));
    $count = (int) ($read->fetchColumn() ?: 0);
    return $count <= $limit;
  } catch (Throwable $e) {
    // Table missing/migration not applied => compatibility fallback.
    $msg = strtolower((string) $e->getMessage());
    if (strpos($msg, 'rate_limits') !== false || strpos($msg, '1146') !== false) {
      return null;
    }
    throw $e;
  }
}

function api_rate_limits_table_exists(PDO $pdo): bool
{
  static $cache = null;
  if ($cache !== null) {
    return $cache;
  }

  try {
    $cols = api_table_columns($pdo, 'rate_limits');
    $cache = !empty($cols);
    return $cache;
  } catch (Throwable $e) {
    $cache = false;
    return false;
  }
}

/**
 * @return string[]
 */
function api_table_columns(PDO $pdo, string $table): array
{
  if (function_exists('db_table_columns')) {
    return db_table_columns($pdo, $table);
  }
  return array();
}

function api_normalize_phone_digits(string $raw): string
{
  $v = trim($raw);
  $v = preg_replace('/\\D+/', '', $v);
  $v = (string) $v;

  // Support international:
  // - "+CC..." ou "00CC..." ou chiffres bruts.
  // Ici on ne garde que les chiffres, et on retire un éventuel préfixe "00".
  if (strpos($v, '00') === 0 && strlen($v) > 2) {
    $v = substr($v, 2);
  }

  return $v;
}

function api_normalize_phone_storage(string $raw): string
{
  $raw = trim($raw);
  $digits = api_normalize_phone_digits($raw);
  if ($digits === '') return '';

  $hasPlus = str_starts_with($raw, '+');
  $has00 = str_starts_with($raw, '00');

  // Si l'utilisateur saisit déjà un format international (+ / 00), on stocke en E.164 simplifié.
  if ($hasPlus || $has00) {
    return '+' . $digits;
  }

  // Si 8–10 chiffres, on considère un numéro local Mali => +223 + digits.
  if (strlen($digits) >= 8 && strlen($digits) <= 10) {
    return '+223' . $digits;
  }

  // Sinon (11–15 chiffres), on considère que l'indicatif pays est déjà inclus.
  return '+' . $digits;
}

function api_normalize_mali_phone(string $raw): string
{
  $raw = trim($raw);
  if ($raw === '') return '';

  $digits = api_normalize_phone_digits($raw);
  if ($digits === '') return '';

  if (str_starts_with($digits, '223')) {
    $digits = substr($digits, 3);
  }

  return preg_match('/^\d{8}$/', $digits) ? ('+223' . $digits) : '';
}

function api_is_valid_mali_phone(string $raw): bool
{
  return api_normalize_mali_phone($raw) !== '';
}

function api_is_valid_email(string $email): bool
{
  $email = trim($email);
  if ($email === '' || strlen($email) > 190) return false;
  return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * @return array{
 *   password_col:string,
 *   name_col:?string,
 *   role_col:?string,
 *   phone_col:?string,
 *   last_name_col:?string,
 *   deleted_at_col:?string
 * }
 */
function api_users_columns(PDO $pdo): array
{
  $cols = api_table_columns($pdo, 'users');

  $passwordCol = in_array('password_hash', $cols, true) ? 'password_hash'
    : (in_array('password', $cols, true) ? 'password' : '');

  return array(
    'password_col' => $passwordCol,
    'name_col' => in_array('name', $cols, true) ? 'name' : (in_array('full_name', $cols, true) ? 'full_name' : null),
    'role_col' => in_array('role', $cols, true) ? 'role' : null,
    'phone_col' => in_array('phone', $cols, true) ? 'phone' : (in_array('customer_phone', $cols, true) ? 'customer_phone' : null),
    'last_name_col' => in_array('last_name', $cols, true) ? 'last_name' : null,
    'deleted_at_col' => in_array('deleted_at', $cols, true) ? 'deleted_at' : null,
  );
}

