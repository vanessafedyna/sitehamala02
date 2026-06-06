<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

api_require_method('GET');
api_rate_limit('order_lookup', 40, 60);

require_once __DIR__ . '/../../app/models/OrderModel.php';

$orderNumber = trim((string) ($_GET['order_number'] ?? ($_GET['orderNumber'] ?? '')));
$phoneRaw = trim((string) ($_GET['phone'] ?? ''));
$otp = trim((string) ($_GET['otp'] ?? ($_GET['otp_code'] ?? '')));

if ($orderNumber === '' || strlen($orderNumber) > 80) {
  json_response(400, array('ok' => false, 'message' => 'order_number obligatoire.'));
}
if (strlen($phoneRaw) > 60) $phoneRaw = substr($phoneRaw, 0, 60);
if (strlen($otp) > 40) $otp = substr($otp, 0, 40);

try {
  $pdo = api_pdo();
  $model = new OrderModel($pdo);

  $order = $model->getByOrderNumber($orderNumber);
  if (!$order) {
    json_response(404, array('ok' => false, 'message' => 'Commande introuvable.'));
  }

  $expectedPhoneDigits = api_normalize_phone_digits((string) ($order['customer_phone'] ?? ''));
  $providedPhoneDigits = api_normalize_phone_digits($phoneRaw);

  $verified = false;
  if ($expectedPhoneDigits !== '' && $providedPhoneDigits !== '') {
    $verified = hash_equals($expectedPhoneDigits, $providedPhoneDigits);
  }

  if (!$verified && $otp !== '') {
    $otpStored = trim((string) ($order['otp_code'] ?? ''));
    if ($otpStored !== '') {
      $verified = hash_equals($otpStored, $otp);
      if ($verified) {
        $orderCols = api_table_columns($pdo, 'orders');
        if (in_array('otp_expires_at', $orderCols, true) && !empty($order['otp_expires_at'])) {
          $exp = strtotime((string) $order['otp_expires_at']);
          if ($exp !== false && $exp < time()) {
            $verified = false;
          }
        }
      }
    }
  }

  // Si aucune v?rification fournie ou v?rification KO => message g?n?rique (ne pas confirmer l'existence).
  if (!$verified) {
    json_response(404, array('ok' => false, 'message' => 'Commande introuvable.'));
  }

  $items = $model->items((int) ($order['id'] ?? 0));
  $timelineRows = $model->getHistory((int) ($order['id'] ?? 0));

  $timeline = array();
  foreach ($timelineRows as $r) {
    $timeline[] = array(
      'status' => (string) ($r['new_status'] ?? ''),
      'note' => array_key_exists('note', $r) ? (string) ($r['note'] ?? '') : '',
      'created_at' => (string) ($r['changed_at'] ?? ''),
    );
  }

  $outItems = array();
  foreach ($items as $it) {
    $outItems[] = array(
      'product_id' => (int) ($it['product_id'] ?? 0),
      'name' => (string) ($it['product_name_snapshot'] ?? ''),
      'qty' => (int) ($it['qty'] ?? 0),
      'unit_price' => (int) ($it['unit_price_snapshot'] ?? 0),
      'line_total' => (int) ($it['line_total'] ?? 0),
    );
  }

  json_response(200, array(
    'ok' => true,
    'order' => array(
      'order_number' => (string) ($order['order_number'] ?? ''),
      'status' => (string) ($order['status'] ?? ''),
      'status_updated_at' => (string) ($order['status_updated_at'] ?? ''),
      'created_at' => (string) ($order['created_at'] ?? ''),
      'total_amount' => (int) ($order['total_amount'] ?? 0),
      'items' => $outItems,
      'timeline' => $timeline,
    ),
  ));
} catch (Throwable $e) {
  error_log('[api/order_lookup] ' . $e->getMessage());
  json_response(500, array('ok' => false, 'message' => 'Impossible de recuperer la commande.'));
}

