<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

api_require_method('POST');
api_rate_limit('order_track', 20, 60);
api_require_csrf();

/**
 * @return array<string,mixed>
 */
function order_track_read_input(): array
{
  $ct = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
  if (strpos($ct, 'application/json') !== false) {
    return json_read_body();
  }

  return array(
    'order_number' => $_POST['order_number'] ?? ($_POST['orderNumber'] ?? ''),
    'phone' => $_POST['phone'] ?? '',
  );
}

function order_track_status_norm(string $status): string
{
  $s = strtolower(trim($status));
  $map = array(
    'nouvelle' => 'nouveau',
    'confirmee' => 'confirme',
    'preparee' => 'en_preparation',
    'livree' => 'livre',
  );
  return $map[$s] ?? $s;
}

$body = order_track_read_input();
$orderNumber = trim((string) ($body['order_number'] ?? ($body['orderNumber'] ?? '')));
$phone = trim((string) ($body['phone'] ?? ''));
$phoneNormalized = api_normalize_mali_phone($phone);

if (
  $orderNumber === ''
  || $phone === ''
  || strlen($orderNumber) > 80
  || strlen($phone) > 40
  || $phoneNormalized === ''
) {
  json_response(400, array('ok' => false, 'message' => 'Champs invalides.'));
}

try {
  $pdo = api_pdo();

  $orderCols = api_table_columns($pdo, 'orders');
  if (!in_array('order_number', $orderCols, true) || !in_array('status', $orderCols, true)) {
    json_response(500, array('ok' => false, 'message' => 'Erreur serveur.'));
  }

  $hasCustomerPhone = in_array('customer_phone', $orderCols, true);
  $hasPhone = in_array('phone', $orderCols, true);

  if (!$hasCustomerPhone && !$hasPhone) {
    json_response(500, array('ok' => false, 'message' => 'Erreur serveur.'));
  }

  $updatedExpr = in_array('updated_at', $orderCols, true)
    ? 'updated_at'
    : (in_array('created_at', $orderCols, true) ? 'created_at' : "''");
  $createdExpr = in_array('created_at', $orderCols, true) ? 'created_at' : "''";
  $totalExpr = in_array('total_amount', $orderCols, true)
    ? 'total_amount'
    : (in_array('total_fcfa', $orderCols, true) ? 'total_fcfa' : '0');

  $customerPhoneExpr = $hasCustomerPhone ? 'customer_phone' : "''";
  $phoneExpr = $hasPhone ? 'phone' : "''";

  $sqlOrder = 'SELECT id, order_number, status, '
    . $createdExpr . ' AS created_at, '
    . $updatedExpr . ' AS updated_at, '
    . $totalExpr . ' AS total_amount, '
    . $customerPhoneExpr . ' AS customer_phone, '
    . $phoneExpr . ' AS phone'
    . ' FROM orders'
    . ' WHERE order_number = :n'
    . ' LIMIT 1';

  $stmtOrder = $pdo->prepare($sqlOrder);
  $stmtOrder->execute(array(
    'n' => $orderNumber,
  ));
  $order = $stmtOrder->fetch(PDO::FETCH_ASSOC) ?: null;

  if (!$order) {
    json_response(404, array('ok' => false, 'message' => 'Commande introuvable.'));
  }

  $expectedPhoneValues = array();
  if ($hasCustomerPhone) {
    $expectedPhoneValues[] = (string) ($order['customer_phone'] ?? '');
  }
  if ($hasPhone) {
    $expectedPhoneValues[] = (string) ($order['phone'] ?? '');
  }

  $phoneVerified = false;
  foreach ($expectedPhoneValues as $expectedPhoneRaw) {
    $expectedNormalized = api_normalize_mali_phone($expectedPhoneRaw);
    if ($expectedNormalized !== '' && hash_equals($expectedNormalized, $phoneNormalized)) {
      $phoneVerified = true;
      break;
    }
  }

  if (!$phoneVerified) {
    json_response(404, array('ok' => false, 'message' => 'Commande introuvable.'));
  }

  $orderId = (int) ($order['id'] ?? 0);

  // Items
  $items = array();
  try {
    $itemCols = api_table_columns($pdo, 'order_items');
    if ($itemCols && $orderId > 0) {
      $nameCol = in_array('product_name_snapshot', $itemCols, true) ? 'product_name_snapshot'
        : (in_array('product_name', $itemCols, true) ? 'product_name' : "''");
      $qtyCol = in_array('qty', $itemCols, true) ? 'qty'
        : (in_array('quantity', $itemCols, true) ? 'quantity' : '0');
      $unitCol = in_array('unit_price_snapshot', $itemCols, true) ? 'unit_price_snapshot'
        : (in_array('price_fcfa', $itemCols, true) ? 'price_fcfa' : '0');
      $lineCol = in_array('line_total', $itemCols, true) ? 'line_total'
        : (in_array('subtotal_fcfa', $itemCols, true) ? 'subtotal_fcfa' : '0');

      $stmtItems = $pdo->prepare(
        'SELECT '
        . $nameCol . ' AS product_name_snapshot, '
        . $qtyCol . ' AS qty, '
        . $unitCol . ' AS unit_price_snapshot, '
        . $lineCol . ' AS line_total'
        . ' FROM order_items WHERE order_id = :id ORDER BY id ASC'
      );
      $stmtItems->execute(array('id' => $orderId));
      $rows = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: array();

      foreach ($rows as $r) {
        $qty = (int) ($r['qty'] ?? 0);
        $unit = (int) ($r['unit_price_snapshot'] ?? 0);
        $line = (int) ($r['line_total'] ?? 0);
        if ($line <= 0 && $qty > 0 && $unit > 0) {
          $line = $qty * $unit;
        }

        $items[] = array(
          'product_name_snapshot' => (string) ($r['product_name_snapshot'] ?? ''),
          'qty' => $qty,
          'unit_price_snapshot' => $unit,
          'line_total' => $line,
        );
      }
    }
  } catch (Throwable $e) {
    $items = array();
  }

  // History (optional table)
  $history = array();
  try {
    $hCols = api_table_columns($pdo, 'order_status_history');
    $hasHistory = !empty($hCols);

    if ($hasHistory && $orderId > 0) {
      $noteCol = in_array('note', $hCols, true) ? 'note' : "''";
      $changedAtCol = in_array('changed_at', $hCols, true)
        ? 'changed_at'
        : (in_array('created_at', $hCols, true) ? 'created_at' : "''");

      $stmtHistory = $pdo->prepare(
        'SELECT old_status, new_status, '
        . $noteCol . ' AS note, '
        . $changedAtCol . ' AS changed_at'
        . ' FROM order_status_history'
        . ' WHERE order_id = :id'
        . ' ORDER BY changed_at ASC, id ASC'
      );
      $stmtHistory->execute(array('id' => $orderId));
      $rows = $stmtHistory->fetchAll(PDO::FETCH_ASSOC) ?: array();

      foreach ($rows as $r) {
        $history[] = array(
          'old_status' => order_track_status_norm((string) ($r['old_status'] ?? '')),
          'new_status' => order_track_status_norm((string) ($r['new_status'] ?? '')),
          'note' => (string) ($r['note'] ?? ''),
          'changed_at' => (string) ($r['changed_at'] ?? ''),
        );
      }
    }
  } catch (Throwable $e) {
    $history = array();
  }

  json_response(200, array(
    'ok' => true,
    'order' => array(
      'order_number' => (string) ($order['order_number'] ?? ''),
      'status' => order_track_status_norm((string) ($order['status'] ?? '')),
      'created_at' => (string) ($order['created_at'] ?? ''),
      'updated_at' => (string) ($order['updated_at'] ?? ''),
      'total_amount' => (int) ($order['total_amount'] ?? 0),
    ),
    'items' => $items,
    'history' => $history,
  ));
} catch (Throwable $e) {
  if (class_exists('Logger')) {
    Logger::error('api_order_track_failed', array(
      'error' => $e->getMessage(),
    ));
  } else {
    error_log('[api/order_track] ' . $e->getMessage());
  }
  json_response(500, array('ok' => false, 'message' => 'Erreur serveur.'));
}
