<?php
declare(strict_types=1);

/* Export clients */

require_once __DIR__ . '/../_auth.php';
requireAdminCapability('exports.view');
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/services/AdminAuditService.php';

$out = null;

try {
  $pdo = db();

  // Verify customers table availability.
  $hasCustomers = false;
  if (function_exists('db_table_columns')) {
    $hasCustomers = (db_table_columns($pdo, 'customers') !== array());
  } else {
    $stmt = $pdo->query("SHOW TABLES LIKE 'customers'");
    $hasCustomers = (bool) ($stmt && $stmt->fetchColumn());
  }
  if (!$hasCustomers) {
    if (!headers_sent()) {
      http_response_code(500);
      header('Content-Type: text/plain; charset=utf-8');
      echo 'Export clients indisponible.';
    }
    exit;
  }

  $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
  AdminAuditService::log($pdo, $adminId, 'owner_export_customers_csv');

  header('Content-Type: text/csv; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Content-Disposition: attachment; filename="customers.csv"');

  $out = fopen('php://output', 'wb');
  if (!$out) {
    throw new RuntimeException('Unable to open output stream.');
  }
  fwrite($out, "\xEF\xBB\xBF");

  fputcsv($out, array('full_name', 'phone', 'email', 'city', 'district', 'is_blacklisted', 'orders_count', 'total_spent'));

  $limit = 500;
  $offset = 0;

  while (true) {
    $sql = "
      SELECT
        c.id,
        c.full_name,
        c.phone,
        c.email,
        c.city,
        c.district,
        c.is_blacklisted,
        (
          SELECT COUNT(*) FROM orders o
          WHERE o.customer_profile_id = c.id
        ) AS orders_count,
        (
          SELECT COALESCE(SUM(o.total_amount),0) FROM orders o
          WHERE o.customer_profile_id = c.id AND o.status = 'livree'
        ) AS total_spent
      FROM customers c
      ORDER BY c.id DESC
      LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    if (!$rows) break;

    foreach ($rows as $r) {
      fputcsv($out, array(
        (string) ($r['full_name'] ?? ''),
        (string) ($r['phone'] ?? ''),
        (string) ($r['email'] ?? ''),
        (string) ($r['city'] ?? ''),
        (string) ($r['district'] ?? ''),
        (string) ((int) ($r['is_blacklisted'] ?? 0)),
        (string) ((int) ($r['orders_count'] ?? 0)),
        (string) ((int) ($r['total_spent'] ?? 0)),
      ));
    }

    $offset += $limit;
  }

  if (is_resource($out)) {
    fclose($out);
  }
  exit;
} catch (Throwable $e) {
  if (class_exists('Logger')) {
    Logger::error('admin_export_customers_failed', array('error' => $e->getMessage()));
  } elseif (function_exists('log_error')) {
    log_error('[admin_export_customers_failed] ' . $e->getMessage());
  }

  if (is_resource($out)) {
    @fclose($out);
  }

  if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Une erreur interne est survenue. Veuillez reessayer.';
  }
  exit;
}
