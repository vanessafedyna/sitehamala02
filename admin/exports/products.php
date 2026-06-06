<?php
declare(strict_types=1);

/* Export produits */

require_once __DIR__ . '/../_auth.php';
requireAdminCapability('exports.view');
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/services/AdminAuditService.php';

$out = null;

try {
  $pdo = db();
  $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
  AdminAuditService::log($pdo, $adminId, 'owner_export_products_csv');

  header('Content-Type: text/csv; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Content-Disposition: attachment; filename="products.csv"');

  $out = fopen('php://output', 'wb');
  if (!$out) {
    throw new RuntimeException('Unable to open output stream.');
  }
  fwrite($out, "\xEF\xBB\xBF");

  // Detect optional columns.
  $fields = function_exists('db_table_columns') ? db_table_columns($pdo, 'products') : array();
  $hasThreshold = in_array('low_stock_threshold', $fields, true);
  $hasStatus = in_array('status', $fields, true);

  fputcsv($out, array('sku', 'name', 'price', 'stock', 'low_stock_threshold', 'status', 'created_at'));

  $limit = 500;
  $offset = 0;
  while (true) {
    $sql = 'SELECT sku, name, price, stock, created_at'
      . ($hasThreshold ? ', low_stock_threshold' : ', 10 AS low_stock_threshold')
      . ($hasStatus ? ', status' : ", '' AS status")
      . ' FROM products ORDER BY id DESC LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    if (!$rows) break;

    foreach ($rows as $r) {
      fputcsv($out, array(
        (string) ($r['sku'] ?? ''),
        (string) ($r['name'] ?? ''),
        (string) ((int) ($r['price'] ?? 0)),
        (string) ((int) ($r['stock'] ?? 0)),
        (string) ((int) ($r['low_stock_threshold'] ?? 10)),
        (string) ($r['status'] ?? ''),
        (string) ($r['created_at'] ?? ''),
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
    Logger::error('admin_export_products_failed', array('error' => $e->getMessage()));
  } elseif (function_exists('log_error')) {
    log_error('[admin_export_products_failed] ' . $e->getMessage());
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
