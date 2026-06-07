<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../helpers/functions.php';

function current_user(): ?array
{
  session_start_secure();
  return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function current_admin_user(): ?array
{
  session_start_secure();
  return isset($_SESSION['admin_user']) && is_array($_SESSION['admin_user']) ? $_SESSION['admin_user'] : null;
}

function login_user(array $user): void
{
  session_start_secure();
  session_regenerate_id(true);

  $_SESSION['user'] = array(
    'id' => (int) ($user['id'] ?? 0),
    'email' => (string) ($user['email'] ?? ''),
    'role' => strtolower(trim((string) ($user['role'] ?? ''))),
    'name' => (string) ($user['name'] ?? ''),
    'last_name' => (string) ($user['last_name'] ?? ''),
    'is_active' => array_key_exists('is_active', $user) ? (int) ($user['is_active'] ?? 1) : 1,
  );
}

function login_admin_user(array $user): void
{
  session_start_secure();
  session_regenerate_id(true);

  $_SESSION['admin_user'] = array(
    'id' => (int) ($user['id'] ?? 0),
    'email' => (string) ($user['email'] ?? ''),
    'role' => strtolower(trim((string) ($user['role'] ?? ''))),
    'name' => (string) ($user['name'] ?? ''),
    'is_active' => array_key_exists('is_active', $user) ? (int) ($user['is_active'] ?? 1) : 1,
  );
  $_SESSION['admin_last_seen'] = time();
}

function logout_user(): void
{
  session_start_secure();
  unset($_SESSION['user'], $_SESSION['_csrf']);
  session_regenerate_id(true);
}

function logout_admin_user(): void
{
  session_start_secure();
  unset($_SESSION['admin_user'], $_SESSION['_csrf'], $_SESSION['admin_id'], $_SESSION['admin_email'], $_SESSION['admin_role'], $_SESSION['admin_last_seen']);
  session_regenerate_id(true);
}

function require_login(): void
{
  if (!current_user()) {
    redirect('admin/login.php');
  }
}

function require_user_login(): void
{
  if (!current_user()) {
    redirect('pages/connexion.php');
  }
}

function require_admin_login(): void
{
  if (!current_admin_user()) {
    redirect('admin/login.php');
  }
}

/**
 * @param string[] $roles
 */
function require_role(array $roles): void
{
  require_login();
  $user = current_user();
  $role = strtolower(trim((string) ($user['role'] ?? '')));
  $roles = array_values(array_map(fn ($r) => strtolower(trim((string) $r)), $roles));

  if (!$role || !in_array($role, $roles, true)) {
    log_error('[AUTH] Accès réservé: role="' . $role . '" allowed="' . implode(',', $roles) . '" uri="' . ((string) ($_SERVER['REQUEST_URI'] ?? '')) . '"');
    http_response_code(403);
    echo 'Accès réservé';
    exit;
  }
}

// ===== Compat (ancien nommage utilisé pendant le refactor) =====
function auth_start(): void { session_start_secure(); }
function auth_user(): ?array { return current_user(); }
function auth_login(array $user): void { login_user($user); }
function auth_logout(): void { logout_user(); }

// ===== API simple demandé (front/back V1) =====
function isLoggedIn(): bool
{
  return current_user() !== null;
}

function requireLogin(): void
{
  require_login();
}

function currentUserRole(): ?string
{
  $user = current_user();
  if (!$user) return null;
  $role = (string) ($user['role'] ?? '');
  return $role !== '' ? $role : null;
}