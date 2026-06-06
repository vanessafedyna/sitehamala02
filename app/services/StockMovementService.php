<?php
declare(strict_types=1);

final class StockMovementService
{
  public static function record(
    PDO $pdo,
    int $productId,
    int $deltaQty,
    string $reason,
    ?int $relatedOrderId = null,
    ?int $adminId = null,
    ?string $note = null,
    array $context = array()
  ): void {
    $fields = function_exists('db_table_columns') ? db_table_columns($pdo, 'stock_movements') : array();
    if (!$fields || $productId <= 0 || $deltaQty === 0) {
      return;
    }

    $hasChangeQty = in_array('change_qty', $fields, true);
    $hasReason = in_array('reason', $fields, true);
    $hasRelatedOrder = in_array('related_order_id', $fields, true);
    $hasAdminId = in_array('admin_id', $fields, true);
    $hasIp = in_array('ip', $fields, true);
    $hasType = in_array('type', $fields, true);
    $hasQty = in_array('qty', $fields, true);
    $hasNote = in_array('note', $fields, true);
    $hasUserId = in_array('user_id', $fields, true);
    $hasVariantId = in_array('variant_id', $fields, true);

    $insertFields = array('product_id');
    $placeholders = array(':product_id');
    $params = array('product_id' => $productId);

    if ($hasType) {
      $insertFields[] = 'type';
      $placeholders[] = ':type';
      $params['type'] = $deltaQty > 0 ? 'add' : 'remove';
    }
    if ($hasQty) {
      $insertFields[] = 'qty';
      $placeholders[] = ':qty';
      $params['qty'] = abs($deltaQty);
    }
    if ($hasChangeQty) {
      $insertFields[] = 'change_qty';
      $placeholders[] = ':change_qty';
      $params['change_qty'] = $deltaQty;
    }
    if ($hasReason) {
      $insertFields[] = 'reason';
      $placeholders[] = ':reason';
      $params['reason'] = $reason;
    }
    if ($hasRelatedOrder) {
      $insertFields[] = 'related_order_id';
      $placeholders[] = ':related_order_id';
      $params['related_order_id'] = $relatedOrderId;
    }
    if ($hasVariantId && isset($context['variant_id'])) {
      $variantId = (int) $context['variant_id'];
      $insertFields[] = 'variant_id';
      $placeholders[] = ':variant_id';
      $params['variant_id'] = $variantId > 0 ? $variantId : null;
    }
    if ($hasAdminId) {
      $insertFields[] = 'admin_id';
      $placeholders[] = ':admin_id';
      $params['admin_id'] = $adminId;
    } elseif ($hasUserId) {
      $insertFields[] = 'user_id';
      $placeholders[] = ':user_id';
      $params['user_id'] = $adminId;
    }
    if ($hasIp) {
      $insertFields[] = 'ip';
      $placeholders[] = ':ip';
      $params['ip'] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
    if ($hasNote) {
      $insertFields[] = 'note';
      $placeholders[] = ':note';
      $params['note'] = ($note === null || trim($note) === '') ? null : trim($note);
    }

    $sql = 'INSERT INTO stock_movements (' . implode(', ', $insertFields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
  }
}
