<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
requireAdminCapability('exports.view');
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/services/AdminAuditService.php';

function export_parse_ymd($raw): string
{
  $raw = trim((string) $raw);
  if ($raw === '') return '';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return '';
  return $raw;
}

/**
 * @return string[]
 */
function export_table_columns(PDO $pdo, string $table): array
{
  if (function_exists('db_table_columns')) {
    return db_table_columns($pdo, $table);
  }
  try {
    $rows = $pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC) ?: array();
  } catch (Throwable $e) {
    return array();
  }

  $cols = array();
  foreach ($rows as $row) {
    if (!empty($row['Field'])) {
      $cols[] = (string) $row['Field'];
    }
  }
  return $cols;
}

function export_has_column(array $columns, string $name): bool
{
  return in_array($name, $columns, true);
}

$dateFrom = export_parse_ymd($_GET['date_from'] ?? ($_GET['from'] ?? ''));
$dateTo = export_parse_ymd($_GET['date_to'] ?? ($_GET['to'] ?? ''));

$pdo = db();

$orderCols = export_table_columns($pdo, 'orders');
$itemCols = export_table_columns($pdo, 'order_items');

$totalExpr = export_has_column($orderCols, 'subtotal_amount')
  ? 'COALESCE(o.subtotal_amount, 0)'
  : (export_has_column($orderCols, 'total_amount') ? 'COALESCE(o.total_amount, 0)' : '0');

$shippingExpr = export_has_column($orderCols, 'shipping_fee_amount')
  ? 'COALESCE(o.shipping_fee_amount, 0)'
  : (export_has_column($orderCols, 'shipping_fee') ? 'COALESCE(o.shipping_fee, 0)' : '0');

$grandExpr = export_has_column($orderCols, 'total_amount')
  ? 'COALESCE(o.total_amount, 0)'
  : (export_has_column($orderCols, 'grand_total')
      ? 'COALESCE(o.grand_total, 0)'
      : ('(' . $totalExpr . ' + ' . $shippingExpr . ')'));

$phoneExpr = export_has_column($orderCols, 'customer_phone')
  ? 'COALESCE(o.customer_phone, \'\')'
  : (export_has_column($orderCols, 'phone') ? 'COALESCE(o.phone, \'\')' : '\'\'');

$cityExpr = export_has_column($orderCols, 'city') ? 'COALESCE(o.city, \'\')' : '\'\'';
$districtExpr = export_has_column($orderCols, 'district') ? 'COALESCE(o.district, \'\')' : '\'\'';
$landmarkExpr = export_has_column($orderCols, 'landmark') ? 'COALESCE(o.landmark, \'\')' : '\'\'';

if (export_has_column($itemCols, 'qty')) {
  $itemsCountExpr = 'SUM(COALESCE(qty, 0))';
} elseif (export_has_column($itemCols, 'quantity')) {
  $itemsCountExpr = 'SUM(COALESCE(quantity, 0))';
} else {
  $itemsCountExpr = 'COUNT(*)';
}

$where = array();
$params = array();

if ($dateFrom !== '' && $dateTo !== '') {
  $where[] = 'o.created_at BETWEEN :from_dt AND :to_dt';
  $params['from_dt'] = $dateFrom . ' 00:00:00';
  $params['to_dt'] = $dateTo . ' 23:59:59';
} else {
  if ($dateFrom !== '') {
    $where[] = 'o.created_at >= :from_dt';
    $params['from_dt'] = $dateFrom . ' 00:00:00';
  }
  if ($dateTo !== '') {
    $where[] = 'o.created_at <= :to_dt';
    $params['to_dt'] = $dateTo . ' 23:59:59';
  }
}

$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
AdminAuditService::log($pdo, $adminId, 'owner_export_orders_period_csv');

header('Content-Type: text/csv; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="orders_export.csv"');

$out = fopen('php://output', 'wb');
if (!$out) {
  http_response_code(500);
  exit;
}

fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, array(
  'order_number',
  'created_at',
  'status',
  'total_amount',
  'shipping_fee',
  'grand_total',
  'customer_phone',
  'city',
  'district',
  'landmark',
  'items_count',
));

$limit = 500;
$offset = 0;

while (true) {
  $sql = 'SELECT
      o.order_number,
      o.created_at,
      o.status,
      ' . $totalExpr . ' AS total_amount,
      ' . $shippingExpr . ' AS shipping_fee,
      ' . $grandExpr . ' AS grand_total,
      ' . $phoneExpr . ' AS customer_phone,
      ' . $cityExpr . ' AS city,
      ' . $districtExpr . ' AS district,
      ' . $landmarkExpr . ' AS landmark,
      COALESCE(oi.items_count, 0) AS items_count
    FROM orders o
    LEFT JOIN (
      SELECT order_id, ' . $itemsCountExpr . ' AS items_count
      FROM order_items
      GROUP BY order_id
    ) oi ON oi.order_id = o.id'
    . $whereSql
    . ' ORDER BY o.id DESC LIMIT :limit OFFSET :offset';

  $stmt = $pdo->prepare($sql);
  foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
  }
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
  if (!$rows) {
    break;
  }

  foreach ($rows as $row) {
    fputcsv($out, array(
      (string) ($row['order_number'] ?? ''),
      (string) ($row['created_at'] ?? ''),
      (string) ($row['status'] ?? ''),
      (string) ((int) ($row['total_amount'] ?? 0)),
      (string) ((int) ($row['shipping_fee'] ?? 0)),
      (string) ((int) ($row['grand_total'] ?? 0)),
      (string) ($row['customer_phone'] ?? ''),
      (string) ($row['city'] ?? ''),
      (string) ($row['district'] ?? ''),
      (string) ($row['landmark'] ?? ''),
      (string) ((int) ($row['items_count'] ?? 0)),
    ));
  }

  $offset += $limit;
}

fclose($out);
exit;