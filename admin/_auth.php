<?php
declare(strict_types=1);

/* Accès admin partagé */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/config/database.php';

auth_start();
require_admin_login();

function admin_enforce_idle_timeout(): void
{
  auth_start();

  $timeout = (int) env('ADMIN_SESSION_IDLE_TIMEOUT', 14400);
  if ($timeout <= 0) {
    $timeout = 14400;
  }

  $now = time();
  $lastSeen = isset($_SESSION['admin_last_seen']) ? (int) $_SESSION['admin_last_seen'] : 0;
  if ($lastSeen > 0 && ($now - $lastSeen) > $timeout) {
    logout_admin_user();
    redirect('admin/login.php?expired=1');
  }

  $_SESSION['admin_last_seen'] = $now;
}

/* Réponse 403 admin */
function admin_forbidden(string $message = 'Acces reserve'): void
{
  http_response_code(403);
  $forbidden_message = $message;
  include __DIR__ . '/403.php';
  exit;
}

/* Mapping des rôles admin */
function admin_map_role_to_admin_role(string $dbRole): string
{
  $r = strtolower(trim($dbRole));
  if ($r === 'admin') return 'owner';
  if ($r === 'partner') return 'partner';
  return '';
}

/* Rôle admin courant */
function admin_current_role(): string
{
  auth_start();

  $role = isset($_SESSION['admin_role']) ? (string) $_SESSION['admin_role'] : '';
  $role = strtolower(trim($role));
  if ($role === 'owner' || $role === 'partner') {
    return $role;
  }

  $user = current_admin_user();
  $id = $user ? (int) ($user['id'] ?? 0) : 0;
  $email = $user ? (string) ($user['email'] ?? '') : '';

  if ($id > 0 && (!isset($_SESSION['admin_id']) || (int) $_SESSION['admin_id'] <= 0)) {
    $_SESSION['admin_id'] = $id;
  }
  if ($email !== '' && (!isset($_SESSION['admin_email']) || (string) $_SESSION['admin_email'] === '')) {
    $_SESSION['admin_email'] = $email;
  }

  $mapped = $user ? admin_map_role_to_admin_role((string) ($user['role'] ?? '')) : '';
  if ($mapped !== '') {
    $_SESSION['admin_role'] = $mapped;
    return $mapped;
  }

  $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;
  if ($adminId > 0) {
    try {
      $pdo = db();
      $stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
      $stmt->execute(array('id' => $adminId));
      $dbRole = (string) ($stmt->fetchColumn() ?: '');
      $mapped = admin_map_role_to_admin_role($dbRole);
      if ($mapped !== '') {
        $_SESSION['admin_role'] = $mapped;
        return $mapped;
      }
    } catch (Throwable $e) {
      // Ne pas bloquer: on laissera la verification de role refuser l'acces proprement.
    }
  }

  return '';
}

/* Accès par rôle */
function requireRole(string $role): void
{
  $current = admin_current_role();
  $wanted = strtolower(trim($role));
  if ($wanted !== $current) {
    admin_forbidden('Acces reserve.');
  }
}

/**
 * @param string[] $roles
 */
/* Accès par rôles */
function requireAnyRole(array $roles): void
{
  $current = admin_current_role();
  $normalized = array_values(array_map(fn ($r) => strtolower(trim((string) $r)), $roles));
  if ($current === '' || !in_array($current, $normalized, true)) {
    admin_forbidden('Acces reserve.');
  }
}

/**
 * @return array<string, string[]>
 */
function admin_role_capabilities(): array
{
  return array(
    'owner' => array('*'),
    'partner' => array(
      'orders.view',
      'orders.update_status',
      'orders.note',
      'products.create',
      'products.stock.adjust',
      'products.view',
    ),
  );
}

function admin_has_capability(string $capability): bool
{
  $capability = strtolower(trim($capability));
  if ($capability === '') {
    return false;
  }

  $role = admin_current_role();
  $map = admin_role_capabilities();
  $caps = $map[$role] ?? array();

  return in_array('*', $caps, true) || in_array($capability, $caps, true);
}

function requireAdminCapability(string $capability, string $message = 'Acces reserve.'): void
{
  if (!admin_has_capability($capability)) {
    admin_forbidden($message);
  }
}

// Acces staff minimum pour toutes les pages /admin (owner ou partner)
/* Accès staff de base */
admin_enforce_idle_timeout();
requireAnyRole(array('owner', 'partner'));

/* Déconnecte les comptes désactivés avant l'accès à l'administration. */
try {
  $pdo = db();
  $fields = function_exists('db_table_columns') ? db_table_columns($pdo, 'users') : array();

  if (in_array('is_active', $fields, true)) {
    $u = current_admin_user();
    $id = $u ? (int) ($u['id'] ?? 0) : 0;
    if ($id > 0) {
      $stmt = $pdo->prepare('SELECT is_active FROM users WHERE id = :id LIMIT 1');
      $stmt->execute(array('id' => $id));
      $active = (int) ($stmt->fetchColumn() ?? 1);
      if ($active !== 1) {
        logout_admin_user();
        redirect('admin/login.php?disabled=1');
      }
    }
  }
} catch (Throwable $e) {
  // best-effort
}
