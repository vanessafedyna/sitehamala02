<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

api_require_method('GET');
api_rate_limit('my_orders', 60, 60);

$user = api_require_user();
$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
  json_response(401, array('ok' => false, 'message' => 'Non authentifie.'));
}

try {
  $pdo = api_pdo();
  $cols = api_table_columns($pdo, 'orders');

  $userCol = in_array('user_id', $cols, true) ? 'user_id' : (in_array('customer_id', $cols, true) ? 'customer_id' : '');
  if ($userCol === '') {
    json_response(500, array('ok' => false, 'message' => 'orders.user_id/customer_id manquant.'));
  }

  $totalExpr = in_array('total_amount', $cols, true) ? 'total_amount AS total_amount'
    : (in_array('total_fcfa', $cols, true) ? 'total_fcfa AS total_amount' : '0 AS total_amount');

  $phoneExpr = in_array('customer_phone', $cols, true) ? 'customer_phone AS phone'
    : (in_array('phone', $cols, true) ? 'phone AS phone' : "'' AS phone");

  $statusUpdated = in_array('status_updated_at', $cols, true) ? 'status_updated_at' : 'NULL AS status_updated_at';

  $sql = 'SELECT id, order_number, status, ' . $statusUpdated . ', created_at, ' . $totalExpr . ', ' . $phoneExpr
    . ' FROM orders WHERE ' . $userCol . ' = :uid ORDER BY created_at DESC, id DESC LIMIT 50';

  $stmt = $pdo->prepare($sql);
  $stmt->execute(array('uid' => $userId));
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

  foreach ($rows as &$r) {
    $r['id'] = (int) ($r['id'] ?? 0);
    $r['total_amount'] = (int) ($r['total_amount'] ?? 0);
  }
  unset($r);

  json_response(200, array('ok' => true, 'orders' => $rows));
} catch (Throwable $e) {
  error_log('[api/my_orders] ' . $e->getMessage());
  json_response(500, array('ok' => false, 'message' => 'Impossible de charger vos commandes.'));
}

