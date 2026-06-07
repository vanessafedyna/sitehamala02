<?php
declare(strict_types=1);

/* Export commandes */

require_once __DIR__ . '/../_auth.php';
requireAdminCapability('exports.view');
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/services/AdminAuditService.php';

function export_date(?string $raw): ?string
{
  $raw = trim((string) $raw);
  if ($raw === '') return null;
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return null;
  return $raw;
}

function map_status(?string $status): ?string
{
  $s = strtolower(trim((string) $status));
  if ($s === '') return null;

  $map = array(
    'pending' => 'nouvelle',
    'paid' => 'confirmee',
    'processing' => 'preparee',
    'shipped' => 'en_livraison',
    'delivered' => 'livree',
    'cancelled' => 'annulee',
    'canceled' => 'annulee',
    // autoriser aussi les statuts FR
    'nouvelle' => 'nouvelle',
    'confirmee' => 'confirmee',
    'preparee' => 'preparee',
    'en_livraison' => 'en_livraison',
    'livree' => 'livree',
    'annulee' => 'annulee',
  );

  return $map[$s] ?? null;
}

$out = null;

try {
  $from = export_date($_GET['from'] ?? null);
  $to = export_date($_GET['to'] ?? null);
  $status = map_status($_GET['status'] ?? null);

  // Filtrage dates: inclusif sur [from 00:00:00, to 23:59:59]
  $fromDt = $from ? ($from . ' 00:00:00') : null;
  $toDt = $to ? ($to . ' 23:59:59') : null;

  $pdo = db();
  $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
  AdminAuditService::log($pdo, $adminId, 'owner_export_orders_csv');

  header('Content-Type: text/csv; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Content-Disposition: attachment; filename="orders.csv"');

  $out = fopen('php://output', 'wb');
  if (!$out) {
    throw new RuntimeException('Unable to open output stream.');
  }

  // BOM UTF-8 (Excel)
  fwrite($out, "\xEF\xBB\xBF");

  fputcsv($out, array('order_number', 'created_at', 'status', 'total', 'customer_name', 'phone', 'city', 'items_count'));

  $where = array();
  $params = array();

  if ($status !== null) {
    $where[] = 'o.status = :status';
    $params['status'] = $status;
  }
  if ($fromDt !== null) {
    $where[] = 'o.created_at >= :from_dt';
    $params['from_dt'] = $fromDt;
  }
  if ($toDt !== null) {
    $where[] = 'o.created_at <= :to_dt';
    $params['to_dt'] = $toDt;
  }

  $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

  $limit = 500;
  $offset = 0;

  while (true) {
    $sql = 'SELECT o.order_number, o.created_at, o.status, o.total_amount, o.customer_name, o.customer_phone, o.city,
              (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS items_count
            FROM orders o'
            . $whereSql
            . ' ORDER BY o.id DESC LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
      $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

    if (!$rows) {
      break;
    }

    foreach ($rows as $r) {
      fputcsv($out, array(
        (string) ($r['order_number'] ?? ''),
        (string) ($r['created_at'] ?? ''),
        (string) ($r['status'] ?? ''),
        (string) ((int) ($r['total_amount'] ?? 0)),
        (string) ($r['customer_name'] ?? ''),
        (string) ($r['customer_phone'] ?? ''),
        (string) ($r['city'] ?? ''),
        (string) ((int) ($r['items_count'] ?? 0)),
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
    Logger::error('admin_export_orders_failed', array('error' => $e->getMessage()));
  } elseif (function_exists('log_error')) {
    log_error('[admin_export_orders_failed] ' . $e->getMessage());
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

