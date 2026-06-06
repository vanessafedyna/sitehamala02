<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
requireAnyRole(array('owner', 'partner'));
require_once __DIR__ . '/../_flash.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/OrderModel.php';
require_once __DIR__ . '/../../app/services/AdminAuditService.php';
require_once __DIR__ . '/../../includes/NotificationService.php';
require_once __DIR__ . '/../../includes/Logger.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$adminRole = admin_current_role();
$isOwner = ($adminRole === 'owner');

$page_title = 'Admin - Detail commande';
$page_css = 'pages/admin-products.css';
$page_js = '';

function admin_status_norm(string $status): string
{
  $s = strtolower(trim($status));
  $map = array(
    'nouvelle' => 'nouveau',
    'confirmee' => 'confirme',
    'preparee' => 'en_preparation',
    'livree' => 'livre',
    'pending' => 'nouveau',
    'confirmed' => 'confirme',
    'processing' => 'en_preparation',
    'prepared' => 'en_preparation',
    'shipped' => 'en_livraison',
    'delivering' => 'en_livraison',
    'delivered' => 'livre',
    'cancelled' => 'annulee',
  );
  return $map[$s] ?? $s;
}

function admin_order_status_label(string $status): string
{
  $status = admin_status_norm($status);
  $map = array(
    'nouveau' => 'Nouveau',
    'confirme' => 'Confirmée',
    'en_preparation' => 'En préparation',
    'en_livraison' => 'En livraison',
    'livre' => 'Livrée',
    'annulee' => 'Annulée',
  );
  return $map[$status] ?? $status;
}

function admin_order_badge_class(string $status): string
{
  $status = admin_status_norm($status);
  if ($status === 'livre') return 'admin-badge admin-badge--ok';
  if ($status === 'annulee') return 'admin-badge admin-badge--off';
  return 'admin-badge';
}

function admin_money_fcfa(int $amount): string
{
  return number_format($amount, 0, ',', ' ') . ' FCFA';
}

/**
 * @return string[]
 */
function admin_allowed_next_statuses(string $current): array
{
  $targets = admin_allowed_target_statuses($current);
  foreach ($targets as $st) {
    if ($st !== 'annulee') {
      return array($st);
    }
  }
  return array();
}

/**
 * @return string[]
 */
function admin_allowed_target_statuses(string $current): array
{
  $current = admin_status_norm($current);
  $flow = array('nouveau', 'confirme', 'en_preparation', 'en_livraison', 'livre');

  if ($current === 'livre' || $current === 'annulee') {
    return array();
  }

  $i = array_search($current, $flow, true);
  if ($i === false) return array();

  $out = array();
  if (isset($flow[$i + 1])) {
    $out[] = $flow[$i + 1];
  }
  $out[] = 'annulee';

  return array_values(array_unique($out));
}

function admin_order_notes_supported(PDO $pdo): bool
{
  return admin_table_supported($pdo, 'order_notes');
}

function admin_notification_jobs_supported(PDO $pdo): bool
{
  return admin_table_supported($pdo, 'notification_jobs');
}

function admin_table_supported(PDO $pdo, string $table): bool
{
  $table = trim($table);
  if ($table === '') return false;

  if (function_exists('db_table_columns')) {
    return db_table_columns($pdo, $table) !== array();
  }
  try {
    $safeTable = str_replace("'", "''", $table);
    $stmt = $pdo->query("SHOW TABLES LIKE '" . $safeTable . "'");
    return (bool) ($stmt && $stmt->fetchColumn());
  } catch (Throwable $e) {
    return false;
  }
}

/**
 * Charge les articles d'une commande de facon robuste (schema legacy + nouveau).
 *
 * @return array<int, array<string,mixed>>
 */
function admin_load_order_items(PDO $pdo, int $orderId): array
{
  if ($orderId <= 0) return array();

  $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id ORDER BY id ASC');
  $stmt->execute(array('id' => $orderId));
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

  $out = array();
  foreach ($rows as $r) {
    $name = (string) ($r['product_name_snapshot'] ?? ($r['product_name'] ?? ''));
    $sku = (string) ($r['sku_snapshot'] ?? ($r['sku'] ?? ''));
    $variantId = isset($r['variant_id']) ? (int) $r['variant_id'] : 0;
    $size = trim((string) ($r['size_snapshot'] ?? ''));
    $color = trim((string) ($r['color_snapshot'] ?? ''));
    $qty = (int) ($r['qty'] ?? ($r['quantity'] ?? ($r['qte'] ?? 0)));
    $unit = (int) ($r['unit_price_snapshot'] ?? ($r['price_fcfa'] ?? ($r['unit_price'] ?? ($r['price'] ?? 0))));
    $line = (int) ($r['line_total'] ?? ($r['subtotal_fcfa'] ?? ($r['subtotal'] ?? 0)));

    if ($line <= 0 && $unit > 0 && $qty > 0) {
      $line = $unit * $qty;
    }
    if ($qty <= 0 && $unit > 0 && $line > 0) {
      $qty = (int) max(1, floor($line / $unit));
    }

    if ($name === '' && $sku === '' && $qty <= 0 && $unit <= 0 && $line <= 0) {
      continue;
    }

    $out[] = array(
      'product_name_snapshot' => $name,
      'variant_id' => $variantId > 0 ? $variantId : null,
      'size_snapshot' => $size,
      'color_snapshot' => $color !== '' ? $color : null,
      'sku_snapshot' => $sku,
      'qty' => $qty,
      'unit_price_snapshot' => $unit,
      'line_total' => $line,
    );
  }

  return $out;
}

function admin_is_list_array(array $arr): bool
{
  if (function_exists('array_is_list')) {
    return array_is_list($arr);
  }
  $i = 0;
  foreach ($arr as $k => $_) {
    if ($k !== $i) return false;
    $i++;
  }
  return true;
}

$errors = array();
$data = null;
$history = array();
$notes = array();
$notifications = array();
$notes_supported = false;
$notifications_supported = false;
$model = null;
$pdo = null;
$flash = admin_flash_get('orders');

try {
  $pdo = db();
  $model = new OrderModel($pdo);
  $data = $model->findWithItems($id);
  $history = $model->getHistory($id);

  $notes_supported = admin_order_notes_supported($pdo);
  if ($notes_supported && $data) {
    $stmtNotes = $pdo->prepare(
      'SELECT n.id, n.note, n.created_at, n.admin_id, u.email AS admin_email, u.name AS admin_name
       FROM order_notes n
       LEFT JOIN users u ON u.id = n.admin_id
       WHERE n.order_id = :id
       ORDER BY n.created_at DESC, n.id DESC
       LIMIT 50'
    );
    $stmtNotes->execute(array('id' => (int) $id));
    $notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC) ?: array();
  }

  $notifications_supported = admin_notification_jobs_supported($pdo);
  if ($notifications_supported && $data) {
    $stmtN = $pdo->prepare(
      'SELECT id, type, recipient, status, attempts, max_attempts, last_error, next_retry_at, updated_at, created_at
       FROM notification_jobs
       WHERE order_id = :id
       ORDER BY id DESC
       LIMIT 100'
    );
    $stmtN->execute(array('id' => (int) $id));
    $rows = $stmtN->fetchAll(PDO::FETCH_ASSOC) ?: array();

    $latestByType = array();
    foreach ($rows as $r) {
      $t = (string) ($r['type'] ?? '');
      if ($t === '') continue;
      if (!isset($latestByType[$t])) {
        $latestByType[$t] = $r;
      }
    }

    foreach (array('admin_new_order', 'client_order_created', 'client_status_update') as $type) {
      $notifications[] = $latestByType[$type] ?? array(
        'type' => $type,
        'status' => 'none',
        'recipient' => '-',
        'attempts' => 0,
        'max_attempts' => 0,
        'last_error' => '',
        'next_retry_at' => '',
        'updated_at' => '',
      );
    }
  }
} catch (Throwable $e) {
  $data = null;
  $errors[] = 'Impossible de charger la commande.';
}

if ($data && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $errors[] = 'Session expirée. Veuillez réessayer.';
  } else {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_note') {
      $note = trim((string) ($_POST['note'] ?? ''));
      if ($note === '') {
        $errors[] = 'Note vide.';
      } elseif (function_exists('mb_strlen') ? mb_strlen($note) > 2000 : strlen($note) > 2000) {
        $errors[] = 'Note trop longue (max 2000).';
      } elseif (!$notes_supported) {
        $errors[] = 'Table order_notes manquante. Executez: database/patch_orders_pro.sql';
      } else {
        try {
          $pdo = db();
          $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
          $stmt = $pdo->prepare('INSERT INTO order_notes (order_id, admin_id, note) VALUES (:oid, :aid, :note)');
          $stmt->execute(array(
            'oid' => (int) $id,
            'aid' => $adminId && $adminId > 0 ? $adminId : null,
            'note' => $note,
          ));

          AdminAuditService::log($pdo, $adminId, 'order_note_added', 'order', (int) $id, array(
            'actor_role' => admin_current_role(),
          ));
          admin_flash_set('orders', 'success', 'Note ajoutée.');
          redirect('admin/orders/show.php?id=' . $id);
        } catch (Throwable $e) {
          $errors[] = 'Erreur lors de l ajout de la note.';
        }
      }
    } elseif ($action === 'resend_notifications') {
      if (!admin_has_capability('orders.notifications')) {
        $errors[] = 'Action réservée au propriétaire.';
      } else {
        try {
          $order = (array) ($data['order'] ?? array());
          $items = (array) ($data['items'] ?? array());

          NotificationService::notifyAdminNewOrder($order, $items);
          NotificationService::notifyClientOrderCreated($order);
          if (admin_status_norm((string) ($order['status'] ?? '')) === 'en_livraison') {
            NotificationService::notifyClientShipped($order);
          }

          $pdo = db();
          $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
          AdminAuditService::log($pdo, $adminId, 'owner_requeued_notifications', 'order', (int) $id);

          admin_flash_set('orders', 'success', 'Notifications remises en file d’attente.');
          redirect('admin/orders/show.php?id=' . $id);
        } catch (Throwable $e) {
          $errors[] = 'Erreur lors du renvoi des notifications.';
        }
      }
    } else {
      $newStatus = '';
      if ($action === 'quick_confirm') {
        $newStatus = 'confirme';
      } else {
        $newStatus = trim((string) ($_POST['new_status'] ?? ''));
      }

      try {
        $oldStatus = admin_status_norm((string) (($data['order']['status'] ?? 'nouveau')));
        $newStatusNorm = admin_status_norm($newStatus);
        if ($newStatusNorm === 'annulee' && !admin_has_capability('orders.cancel')) {
          throw new RuntimeException('forbidden_cancel');
        }
        $user = current_admin_user();
        $changedBy = $user ? (int) ($user['id'] ?? 0) : 0;
        $model->updateStatus($id, $newStatus, $changedBy > 0 ? $changedBy : null, 'Mise a jour (admin)');

        $pdo = db();
        $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
        AdminAuditService::log($pdo, $adminId, 'order_status_changed', 'order', (int) $id, array(
          'actor_role' => admin_current_role(),
          'old_status' => $oldStatus,
          'new_status' => $newStatusNorm,
        ));

        $fresh = $model->findById((int) $id);
        $statusChanged = ($oldStatus !== $newStatusNorm);
        if ($fresh && $statusChanged && admin_status_norm((string) ($fresh['status'] ?? '')) === 'en_livraison') {
          NotificationService::notifyClientShipped($fresh);
        }

        if (class_exists('Logger')) {
          Logger::info('order_status_updated', array(
            'order_id' => (int) $id,
            'old' => $oldStatus,
            'new' => $newStatusNorm,
            'by' => $changedBy > 0 ? $changedBy : null,
          ));
        }

        admin_flash_set('orders', 'success', 'Statut mis à jour.');
        redirect('admin/orders/show.php?id=' . $id);
      } catch (Throwable $e) {
        $oldStatus = admin_status_norm((string) (($data['order']['status'] ?? 'nouveau')));
        $newStatusNorm = admin_status_norm($newStatus);
        $raw = trim((string) $e->getMessage());
        if (class_exists('Logger')) {
          Logger::error('order_status_update_failed', array(
            'order_id' => (int) $id,
            'old' => $oldStatus,
            'new' => $newStatusNorm,
            'error' => $raw,
          ));
        }

        $rawLower = strtolower($raw);
        if ($raw === 'forbidden_cancel' || strpos($rawLower, 'forbidden_cancel') !== false) {
          $errors[] = 'Annulation réservée au propriétaire.';
        } elseif (
          (strpos($rawLower, 'transition') !== false && strpos($rawLower, 'statut') !== false)
          || strpos($rawLower, 'statut invalide') !== false
        ) {
          $errors[] = 'Transition de statut non autorisée.';
        } else {
          $errors[] = 'Erreur lors de la mise a jour du statut.';
        }
      }
    }
  }
}

if (!$data) {
  http_response_code(404);
  $order = null;
  $items = array();
} else {
  $order = $data['order'];
  $items = array();

  if ($pdo instanceof PDO) {
    $items = admin_load_order_items($pdo, (int) ($order['id'] ?? 0));
  }

  if (!$items) {
    $rawItems = $data['items'] ?? array();
    if (is_array($rawItems) && !admin_is_list_array($rawItems)) {
      $rawItems = array($rawItems);
    }
    $items = array_values(array_filter((array) $rawItems, 'is_array'));
  }
}

require_once __DIR__ . '/../_layout_header.php';
?>

<style>
  .admin-order-page {
    display: grid;
    gap: 16px;
    min-width: 0;
    overflow-x: clip;
  }
  .admin-order-page .admin-page-header {
    align-items: flex-start;
    gap: 18px;
  }
  .admin-order-page .admin-page-header__content {
    min-width: 0;
  }
  .admin-order-page .admin-page-header__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
  }
  .admin-order-page .admin-page-header__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 14px;
  }
  .admin-order-page .admin-page-header__title,
  .admin-order-page .admin-page-header__subtitle {
    overflow-wrap: anywhere;
  }
  .admin-order-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    max-width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.78);
    color: var(--admin-text-muted);
    font-size: 0.84rem;
    overflow-wrap: anywhere;
  }
  .admin-order-chip strong {
    color: var(--admin-ink);
  }
  .admin-order-page .admin-badge,
  .admin-order-page .admin-status-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 34px;
    padding: 7px 12px;
    border-radius: 999px;
    border: 1px solid rgba(31, 122, 79, 0.14);
    background: rgba(31, 122, 79, 0.08);
    color: #17613f;
    font-size: 0.82rem;
    font-weight: 700;
    line-height: 1.2;
    text-align: center;
    white-space: normal;
  }
  .admin-order-page .admin-badge--ok,
  .admin-order-page .admin-status-pill--success {
    border-color: rgba(31, 122, 79, 0.16);
    background: rgba(31, 122, 79, 0.12);
    color: #155a39;
  }
  .admin-order-page .admin-badge--off,
  .admin-order-page .admin-status-pill--danger {
    border-color: rgba(182, 59, 32, 0.16);
    background: rgba(182, 59, 32, 0.1);
    color: #8f2c18;
  }
  .admin-order-page .btn,
  .admin-order-page .admin-btn {
    border-radius: 14px;
    background-image: none;
  }
  .admin-order-page .btn.btn-primary,
  .admin-order-page .btn.btn-secondary,
  .admin-order-page .admin-btn--primary {
    background: #1f7a4f;
    border-color: #1f7a4f;
    color: #ffffff;
  }
  .admin-order-page .btn.btn-primary:hover,
  .admin-order-page .btn.btn-primary:focus-visible,
  .admin-order-page .btn.btn-secondary:hover,
  .admin-order-page .btn.btn-secondary:focus-visible,
  .admin-order-page .admin-btn--primary:hover,
  .admin-order-page .admin-btn--primary:focus-visible {
    background: #17613f;
    border-color: #17613f;
    color: #ffffff;
  }
  .admin-order-page .btn.btn-outline,
  .admin-order-page .admin-btn--secondary {
    background: rgba(248, 251, 249, 0.96);
    border-color: rgba(31, 122, 79, 0.14);
    color: #1f7a4f;
  }
  .admin-order-page .btn.btn-outline:hover,
  .admin-order-page .btn.btn-outline:focus-visible,
  .admin-order-page .admin-btn--secondary:hover,
  .admin-order-page .admin-btn--secondary:focus-visible {
    background: rgba(31, 122, 79, 0.08);
    border-color: rgba(31, 122, 79, 0.22);
    color: #17613f;
  }
  .admin-order-page .btn:focus-visible,
  .admin-order-page .admin-btn:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(31, 122, 79, 0.14);
  }
  .admin-order-grid {
    display: grid;
    gap: 16px;
    grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.9fr);
  }
  .admin-order-stack {
    display: grid;
    gap: 16px;
    min-width: 0;
  }
  .admin-order-overview {
    display: grid;
    gap: 14px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .admin-order-panel {
    min-width: 0;
  }
  .admin-order-panel.admin-panel,
  .admin-order-panel.feature-card {
    padding: 18px;
  }
  .admin-order-panel__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
  }
  .admin-order-panel__title {
    margin: 0;
    color: var(--admin-ink);
    font-size: 1rem;
    line-height: 1.25;
  }
  .admin-order-panel__subtitle {
    margin: 4px 0 0;
    color: var(--admin-text-muted);
    font-size: 0.88rem;
    line-height: 1.5;
  }
  .admin-order-kv {
    display: grid;
    gap: 12px;
  }
  .admin-order-kv__item {
    display: grid;
    gap: 5px;
  }
  .admin-order-kv__label {
    color: var(--admin-text-muted);
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
  }
  .admin-order-kv__value {
    color: var(--admin-ink);
    font-weight: 700;
    line-height: 1.5;
    overflow-wrap: anywhere;
  }
  .admin-order-kv__value--muted {
    color: var(--admin-text);
    font-weight: 600;
  }
  .admin-order-table-shell {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
  }
  .admin-order-table-shell .admin-table {
    min-width: 680px;
  }
  .admin-order-table-shell tbody tr:hover {
    background: rgba(31, 122, 79, 0.035);
  }
  .admin-order-table-shell tfoot th,
  .admin-order-table-shell tfoot td {
    background: #f7faf8;
  }
  .admin-order-col-price {
    white-space: nowrap;
  }
  .admin-order-col-total {
    color: var(--admin-ink);
    font-size: 1rem;
  }
  .admin-order-mobile-cards {
    display: grid;
    gap: 12px;
  }
  .admin-order-detail-card {
    display: grid;
    gap: 12px;
    border: 1px solid var(--admin-border);
    border-radius: 18px;
    background: var(--admin-surface);
    box-shadow: var(--admin-shadow-sm);
  }
  .admin-order-detail-card--total {
    background: #f7faf8;
  }
  .admin-order-detail-card .admin-mobile-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
  }
  .admin-order-detail-card .admin-mobile-card__grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .admin-order-detail-card .admin-mobile-card__field {
    min-width: 0;
    padding: 12px;
    border: 1px solid rgba(31, 122, 79, 0.08);
    border-radius: 14px;
    background: #fbfcfb;
  }
  .admin-order-detail-card .admin-mobile-card__label {
    display: block;
    margin-bottom: 6px;
    color: var(--admin-text-muted);
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
  }
  .admin-order-detail-card .admin-mobile-card__value,
  .admin-order-detail-card .admin-mobile-card__title,
  .admin-order-note-card__text {
    overflow-wrap: anywhere;
  }
  .admin-order-workflow {
    display: grid;
    gap: 16px;
  }
  .admin-order-workflow__intro {
    display: grid;
    gap: 6px;
  }
  .admin-order-workflow__title {
    margin: 0;
    color: var(--admin-ink);
    font-size: 1rem;
  }
  .admin-order-workflow__hint,
  .admin-order-workflow__next {
    margin: 0;
  }
  .admin-order-workflow__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }
  .admin-order-workflow__actions form {
    margin: 0;
  }
  .admin-order-workflow__status-form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }
  .admin-order-workflow__status-select {
    min-width: min(220px, 100%);
  }
  .admin-order-history-card {
    position: relative;
    padding-left: 20px;
  }
  .admin-order-history-card__line {
    position: absolute;
    inset: 14px auto 14px 8px;
    width: 2px;
    border-radius: 999px;
    background: rgba(31, 122, 79, 0.16);
  }
  .admin-order-timeline {
    display: grid;
    gap: 12px;
  }
  .admin-order-timeline__item {
    display: grid;
    grid-template-columns: 12px minmax(0, 1fr);
    gap: 12px;
    align-items: start;
  }
  .admin-order-timeline__dot {
    width: 12px;
    height: 12px;
    margin-top: 6px;
    border-radius: 999px;
    background: #1f7a4f;
    box-shadow: 0 0 0 6px rgba(31, 122, 79, 0.08);
  }
  .admin-order-timeline__body {
    display: grid;
    gap: 6px;
    padding: 12px 14px;
    border: 1px solid rgba(31, 122, 79, 0.08);
    border-radius: 16px;
    background: #fbfcfb;
  }
  .admin-order-timeline__title {
    color: var(--admin-ink);
    font-weight: 700;
    overflow-wrap: anywhere;
  }
  .admin-order-timeline__meta {
    color: var(--admin-text-muted);
    font-size: 0.88rem;
    line-height: 1.5;
    overflow-wrap: anywhere;
  }
  .admin-order-note-form {
    display: grid;
    gap: 12px;
    padding: 16px;
    border: 1px solid rgba(31, 122, 79, 0.08);
    border-radius: 18px;
    background: #fbfcfb;
  }
  .admin-order-note-card__text {
    color: var(--admin-text);
    line-height: 1.6;
  }
  .admin-order-empty {
    padding: 22px;
  }
  .admin-order-page .admin-alert ul {
    margin: 10px 0 0;
    padding-left: 18px;
  }
  @media (max-width: 1024px) {
    .admin-order-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
  @media (max-width: 768px) {
    .admin-order-overview {
      grid-template-columns: minmax(0, 1fr);
    }
    .admin-order-page .admin-page-header__actions,
    .admin-order-workflow__actions,
    .admin-order-workflow__status-form {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
    }
    .admin-order-page .admin-page-header__actions .btn,
    .admin-order-workflow__actions .btn,
    .admin-order-workflow__status-form .btn,
    .admin-order-workflow__status-form select,
    .admin-order-workflow__actions form {
      width: 100%;
    }
    .admin-order-detail-card .admin-mobile-card__grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
  @media (max-width: 430px) {
    .admin-order-page .admin-page-header__meta {
      gap: 8px;
    }
    .admin-order-detail-card .admin-mobile-card__header {
      flex-direction: column;
    }
  }
</style>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-order-page">
        <?php if (!$order): ?>
          <div class="admin-page-header">
            <div class="admin-page-header__content">
              <p class="admin-page-header__eyebrow">Commandes</p>
              <h1 class="admin-page-header__title">Detail commande</h1>
              <p class="admin-page-header__subtitle">Cette commande n'est plus disponible ou n'a pas pu etre chargee.</p>
            </div>
          </div>

          <div class="admin-panel admin-empty-panel admin-order-empty">
            <p class="admin-empty-panel__title">Commande introuvable.</p>
            <p class="admin-empty-panel__text">Retournez a la liste des commandes pour continuer votre navigation dans le back-office.</p>
            <div class="admin-empty-panel__actions">
              <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/orders/index.php')); ?>">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour aux commandes
              </a>
            </div>
          </div>
        <?php else: ?>
          <?php
            $num = (string) ($order['order_number'] ?? '');
            $name = (string) ($order['customer_name'] ?? '');
            $phone = (string) ($order['customer_phone'] ?? '');
            $phoneDigits = preg_replace('/\D+/', '', $phone);
            $waPhone = $phoneDigits;
            if (strpos($waPhone, '223') !== 0) {
              $waPhone = '223' . ltrim($waPhone, '0');
            }
            $city = (string) ($order['city'] ?? '');
            $district = trim((string) ($order['district'] ?? ''));
            $deliveryLabel = $city;
            if ($district !== '') {
              $deliveryLabel .= ' - ' . $district;
            }
            $landmark = (string) ($order['landmark'] ?? '');
            $st = admin_status_norm((string) ($order['status'] ?? 'nouveau'));
            $createdAt = (string) ($order['created_at'] ?? '');
            $updatedAt = (string) ($order['status_updated_at'] ?? ($order['updated_at'] ?? ''));
            $subtotalAmount = (int) ($order['subtotal_amount'] ?? 0);
            $discountAmount = (int) ($order['discount_amount'] ?? 0);
            $totalAmount = (int) ($order['total_amount'] ?? 0);
            $couponCode = strtoupper(trim((string) ($order['coupon_code'] ?? '')));
            $hasPromo = ($discountAmount > 0 || $couponCode !== '');
            $nextStatuses = admin_allowed_next_statuses($st);
            $allowedStatusTargets = admin_allowed_target_statuses($st);
            if (!$isOwner) {
              $allowedStatusTargets = array_values(array_filter(
                $allowedStatusTargets,
                static fn (string $status): bool => $status !== 'annulee'
              ));
            }
            $waMessage = 'Bonjour ' . ($name !== '' ? $name . ' ' : '') . 'commande #' . $num;
          ?>

          <div class="admin-page-header">
            <div class="admin-page-header__content">
              <p class="admin-page-header__eyebrow">Commandes</p>
              <h1 class="admin-page-header__title">Detail commande</h1>
              <p class="admin-page-header__subtitle">Suivez la commande, ses informations client et son historique depuis une vue plus claire et mieux structuree.</p>
              <div class="admin-page-header__meta" aria-label="Résumé commande">
                <span class="admin-order-chip"><strong><?php echo e($num !== '' ? $num : ('Commande #' . (string) ((int) ($order['id'] ?? 0)))); ?></strong></span>
                <span class="admin-order-chip">Client <strong><?php echo e($name !== '' ? $name : '-'); ?></strong></span>
                <?php if ($createdAt !== ''): ?>
                  <span class="admin-order-chip">Créée le <strong><?php echo e($createdAt); ?></strong></span>
                <?php endif; ?>
                <span class="<?php echo e(admin_order_badge_class($st)); ?>"><?php echo e(admin_order_status_label($st)); ?></span>
              </div>
            </div>
            <div class="admin-page-header__actions">
              <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/orders/index.php')); ?>">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour aux commandes
              </a>
              <?php if ($phoneDigits !== ''): ?>
                <a class="btn admin-btn admin-btn--secondary" href="tel:<?php echo e($phoneDigits); ?>">Appeler</a>
                <a class="btn admin-btn admin-btn--primary" target="_blank" rel="noopener" href="https://wa.me/<?php echo e($waPhone); ?>?text=<?php echo e(urlencode($waMessage)); ?>">WhatsApp</a>
              <?php endif; ?>
            </div>
          </div>

          <nav class="admin-breadcrumb" aria-label="Fil d'Ariane">
            <a href="<?php echo e(base_url('admin/index.php')); ?>">Dashboard</a>
            <span class="admin-breadcrumb__sep" aria-hidden="true">/</span>
            <a href="<?php echo e(base_url('admin/orders/index.php')); ?>">Commandes</a>
            <span class="admin-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Detail commande</span>
          </nav>

          <?php if ($errors): ?>
            <div class="admin-alert admin-alert--error admin-panel admin-panel--padded" role="alert">
              <strong>Erreur :</strong>
              <ul>
                <?php foreach ($errors as $err): ?>
                  <li><?php echo e($err); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if ($flash): ?>
            <div class="admin-alert admin-alert--<?php echo e($flash['type']); ?> admin-panel admin-panel--padded" role="status" aria-live="polite">
              <strong><?php echo e($flash['message']); ?></strong>
            </div>
          <?php endif; ?>

          <div class="admin-order-grid">
            <div class="admin-order-stack">
              <div class="admin-order-overview" aria-label="Infos commande">
                <div class="feature-card admin-panel admin-order-panel">
                  <div class="admin-order-panel__header">
                    <div>
                      <h3 class="admin-order-panel__title">Client</h3>
                      <p class="admin-order-panel__subtitle">Coordonnees et contact principal.</p>
                    </div>
                  </div>
                  <div class="admin-order-kv">
                    <div class="admin-order-kv__item">
                      <span class="admin-order-kv__label">Nom</span>
                      <div class="admin-order-kv__value"><?php echo e($name !== '' ? $name : '-'); ?></div>
                    </div>
                    <div class="admin-order-kv__item">
                      <span class="admin-order-kv__label">Téléphone</span>
                      <div class="admin-order-kv__value admin-order-kv__value--muted"><?php echo e($phone !== '' ? $phone : '-'); ?></div>
                    </div>
                  </div>
                </div>

                <div class="feature-card admin-panel admin-order-panel">
                  <div class="admin-order-panel__header">
                    <div>
                      <h3 class="admin-order-panel__title">Livraison</h3>
                      <p class="admin-order-panel__subtitle">Adresse resumee et repere.</p>
                    </div>
                  </div>
                  <div class="admin-order-kv">
                    <div class="admin-order-kv__item">
                      <span class="admin-order-kv__label">Zone</span>
                      <div class="admin-order-kv__value"><?php echo e($deliveryLabel !== '' ? $deliveryLabel : '-'); ?></div>
                    </div>
                    <?php if ($landmark !== ''): ?>
                      <div class="admin-order-kv__item">
                        <span class="admin-order-kv__label">Repere</span>
                        <div class="admin-order-kv__value admin-order-kv__value--muted"><?php echo e($landmark); ?></div>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="feature-card admin-panel admin-order-panel">
                  <div class="admin-order-panel__header">
                    <div>
                      <h3 class="admin-order-panel__title">Chronologie</h3>
                      <p class="admin-order-panel__subtitle">Dates de creation et de mise a jour.</p>
                    </div>
                  </div>
                  <div class="admin-order-kv">
                    <div class="admin-order-kv__item">
                      <span class="admin-order-kv__label">Créée</span>
                      <div class="admin-order-kv__value"><?php echo e($createdAt !== '' ? $createdAt : '-'); ?></div>
                    </div>
                    <div class="admin-order-kv__item">
                      <span class="admin-order-kv__label">Mise à jour</span>
                      <div class="admin-order-kv__value admin-order-kv__value--muted"><?php echo e($updatedAt !== '' ? $updatedAt : '-'); ?></div>
                    </div>
                  </div>
                </div>

                <div class="feature-card admin-panel admin-order-panel">
                  <div class="admin-order-panel__header">
                    <div>
                      <h3 class="admin-order-panel__title">Paiement</h3>
                      <p class="admin-order-panel__subtitle">Sous-total, réduction et total final enregistrés.</p>
                    </div>
                  </div>
                  <div class="admin-order-kv">
                    <div class="admin-order-kv__item">
                      <span class="admin-order-kv__label">Sous-total</span>
                      <div class="admin-order-kv__value"><?php echo e(admin_money_fcfa($subtotalAmount)); ?></div>
                    </div>
                    <div class="admin-order-kv__item">
                      <span class="admin-order-kv__label">Code promo</span>
                      <div class="admin-order-kv__value admin-order-kv__value--muted"><?php echo e($couponCode !== '' ? $couponCode : 'Aucun code promo'); ?></div>
                    </div>
                    <?php if ($hasPromo): ?>
                      <div class="admin-order-kv__item">
                        <span class="admin-order-kv__label">Réduction</span>
                        <div class="admin-order-kv__value admin-order-kv__value--muted">-<?php echo e(admin_money_fcfa($discountAmount)); ?></div>
                      </div>
                    <?php endif; ?>
                    <div class="admin-order-kv__item">
                      <span class="admin-order-kv__label">Total payé</span>
                      <div class="admin-order-kv__value"><?php echo e(admin_money_fcfa($totalAmount)); ?></div>
                    </div>
                    <div class="admin-order-kv__item">
                      <span class="admin-order-kv__label">Paiement</span>
                      <div class="admin-order-kv__value admin-order-kv__value--muted"><?php
                        $pm = strtolower(trim((string) ($order['payment_method'] ?? '')));
                        if ($pm === 'cod' || $pm === '') {
                          echo 'Paiement à la livraison';
                        } else {
                          echo e($pm);
                        }
                      ?></div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="feature-card admin-panel admin-order-panel" aria-label="Articles">
                <div class="admin-order-panel__header">
                  <div>
                    <h3 class="admin-order-panel__title">Produits commandes</h3>
                    <p class="admin-order-panel__subtitle">Lecture detaillee des lignes de commande et du total.</p>
                  </div>
                </div>
                <?php
                  $displayItems = array();
                  try {
                    if ($pdo instanceof PDO) {
                      $displayItems = admin_load_order_items($pdo, (int) ($order['id'] ?? 0));
                    }
                  } catch (Throwable $e) {
                    $displayItems = array();
                  }
                  if (!$displayItems) {
                    $displayItems = $items;
                  }
                ?>
                <div class="admin-table-shell admin-order-table-shell admin-order-detail-desktop">
                  <table class="admin-table">
                    <thead>
                      <tr>
                        <th>Produit</th>
                        <th>SKU</th>
                        <th>Qte</th>
                        <th>Prix</th>
                        <th>Sous-total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!$displayItems): ?>
                        <tr><td colspan="5">Aucun article.</td></tr>
                      <?php endif; ?>
                      <?php
                        $sum = 0;
                        foreach ($displayItems as $it):
                          if (!is_array($it)) continue;
                          $pname = trim((string) ($it['product_name_snapshot'] ?? ($it['product_name'] ?? '')));
                          $skuSnap = trim((string) ($it['sku_snapshot'] ?? ($it['sku'] ?? '')));
                          $sizeSnap = trim((string) ($it['size_snapshot'] ?? ''));
                          $colorSnap = trim((string) ($it['color_snapshot'] ?? ''));
                          $qty = (int) ($it['qty'] ?? ($it['quantity'] ?? 0));
                          $unit = (int) ($it['unit_price_snapshot'] ?? ($it['price_fcfa'] ?? ($it['unit_price'] ?? 0)));
                          $line = (int) ($it['line_total'] ?? ($it['subtotal_fcfa'] ?? ($it['subtotal'] ?? ($unit * $qty))));
                          if ($qty <= 0 && $line > 0 && $unit > 0) {
                            $qty = (int) max(1, floor($line / $unit));
                          }
                          if ($pname === '' && $skuSnap === '' && $qty <= 0 && $unit <= 0 && $line <= 0) {
                            continue;
                          }
                          $sum += $line;
                      ?>
                        <tr>
                          <td>
                            <div><?php echo e($pname); ?></div>
                            <?php if ($sizeSnap !== ''): ?><div class="admin-help">Taille : <?php echo e($sizeSnap); ?></div><?php endif; ?>
                            <?php if ($colorSnap !== ''): ?><div class="admin-help">Couleur : <?php echo e($colorSnap); ?></div><?php endif; ?>
                          </td>
                          <td><?php echo e($skuSnap); ?></td>
                          <td><?php echo e((string) $qty); ?></td>
                          <td class="admin-order-col-price"><?php echo e(admin_money_fcfa($unit)); ?></td>
                          <td class="admin-order-col-price"><strong><?php echo e(admin_money_fcfa($line)); ?></strong></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                      <tr>
                        <th colspan="4" style="text-align:right;">Total</th>
                        <th class="admin-order-col-total"><?php echo e(admin_money_fcfa($sum)); ?></th>
                      </tr>
                    </tfoot>
                  </table>
                </div>
                <div class="admin-mobile-cards admin-order-mobile-cards admin-order-detail-mobile" aria-label="Articles mobile">
                  <?php if (!$displayItems): ?>
                    <div class="admin-mobile-card admin-order-detail-card">
                      <p class="admin-help">Aucun article.</p>
                    </div>
                  <?php endif; ?>
                  <?php
                    $sumMobile = 0;
                    foreach ($displayItems as $it):
                      if (!is_array($it)) continue;
                      $pname = trim((string) ($it['product_name_snapshot'] ?? ($it['product_name'] ?? '')));
                      $skuSnap = trim((string) ($it['sku_snapshot'] ?? ($it['sku'] ?? '')));
                      $sizeSnap = trim((string) ($it['size_snapshot'] ?? ''));
                      $colorSnap = trim((string) ($it['color_snapshot'] ?? ''));
                      $qty = (int) ($it['qty'] ?? ($it['quantity'] ?? 0));
                      $unit = (int) ($it['unit_price_snapshot'] ?? ($it['price_fcfa'] ?? ($it['unit_price'] ?? 0)));
                      $line = (int) ($it['line_total'] ?? ($it['subtotal_fcfa'] ?? ($it['subtotal'] ?? ($unit * $qty))));
                      if ($qty <= 0 && $line > 0 && $unit > 0) {
                        $qty = (int) max(1, floor($line / $unit));
                      }
                      if ($pname === '' && $skuSnap === '' && $qty <= 0 && $unit <= 0 && $line <= 0) {
                        continue;
                      }
                      $sumMobile += $line;
                  ?>
                    <article class="admin-mobile-card admin-order-detail-card">
                      <div class="admin-mobile-card__header">
                        <div>
                          <h4 class="admin-mobile-card__title"><?php echo e($pname !== '' ? $pname : 'Article'); ?></h4>
                          <div class="admin-mobile-card__meta"><?php echo e($skuSnap !== '' ? $skuSnap : 'SKU -'); ?></div>
                          <?php if ($sizeSnap !== ''): ?><div class="admin-mobile-card__meta">Taille : <?php echo e($sizeSnap); ?></div><?php endif; ?>
                          <?php if ($colorSnap !== ''): ?><div class="admin-mobile-card__meta">Couleur : <?php echo e($colorSnap); ?></div><?php endif; ?>
                        </div>
                        <span class="admin-badge"><?php echo e((string) $qty); ?> x</span>
                      </div>
                      <div class="admin-mobile-card__grid">
                        <div class="admin-mobile-card__field">
                          <span class="admin-mobile-card__label">Prix unitaire</span>
                          <div class="admin-mobile-card__value"><?php echo e(admin_money_fcfa($unit)); ?></div>
                        </div>
                        <div class="admin-mobile-card__field">
                          <span class="admin-mobile-card__label">Sous-total</span>
                          <div class="admin-mobile-card__value"><?php echo e(admin_money_fcfa($line)); ?></div>
                        </div>
                      </div>
                    </article>
                  <?php endforeach; ?>
                  <?php if ($displayItems): ?>
                    <div class="admin-mobile-card admin-order-detail-card admin-order-detail-card--total">
                      <div class="admin-mobile-card__field">
                        <span class="admin-mobile-card__label">Total commande</span>
                        <div class="admin-mobile-card__value"><?php echo e(admin_money_fcfa($sumMobile)); ?></div>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="feature-card admin-panel admin-order-panel" aria-label="Notifications">
                <div class="admin-order-panel__header">
                  <div>
                    <h3 class="admin-order-panel__title">Notifications</h3>
                    <p class="admin-order-panel__subtitle">État des envois liés à la commande.</p>
                  </div>
                </div>
                <?php if (!$notifications_supported): ?>
                  <div class="admin-empty-panel">
                    <p class="admin-empty-panel__text">Table <code>notification_jobs</code> absente. Executez <code>database/patch_notification_queue.sql</code>.</p>
                  </div>
                <?php else: ?>
                  <div class="admin-table-shell admin-order-table-shell">
                    <table class="admin-table">
                      <thead>
                        <tr>
                          <th>Type</th>
                          <th>Recipient</th>
                          <th>Statut</th>
                          <th>Attempts</th>
                          <th>Retry</th>
                          <th>Erreur</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($notifications as $n): ?>
                          <?php
                            $type = (string) ($n['type'] ?? '');
                            $recipient = (string) ($n['recipient'] ?? '-');
                            $statusN = (string) ($n['status'] ?? 'none');
                            $attemptsN = (int) ($n['attempts'] ?? 0);
                            $maxAttemptsN = (int) ($n['max_attempts'] ?? 0);
                            $nextRetry = (string) ($n['next_retry_at'] ?? '');
                            $errN = (string) ($n['last_error'] ?? '');
                          ?>
                          <tr>
                            <td><?php echo e($type); ?></td>
                            <td><?php echo e($recipient); ?></td>
                            <td><span class="admin-status-pill"><?php echo e($statusN); ?></span></td>
                            <td><?php echo e((string) $attemptsN); ?><?php echo $maxAttemptsN > 0 ? ('/' . e((string) $maxAttemptsN)) : ''; ?></td>
                            <td><?php echo e($nextRetry !== '' ? $nextRetry : '-'); ?></td>
                            <td><?php echo e($errN !== '' ? $errN : '-'); ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="admin-order-stack">
              <div class="admin-panel admin-panel--padded admin-order-workflow" aria-label="Changer le statut">
                <div class="admin-order-workflow__intro">
                  <h3 class="admin-order-workflow__title">Actions commande</h3>
                  <p class="admin-help admin-order-workflow__hint">Workflow : Nouveau -> Confirmée -> En préparation -> En livraison -> Livrée.</p>
                </div>

                <div class="admin-order-workflow__actions" aria-label="Actions statut">
                  <?php if ($st === 'nouveau'): ?>
                    <form method="post" action="">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="action" value="quick_confirm">
                      <button class="btn btn-primary" type="submit">Confirmer</button>
                    </form>
                  <?php endif; ?>

                  <form method="post" action="" class="admin-order-workflow__status-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="change_status">
                    <label class="sr-only" for="new_status">Nouveau statut</label>
                    <select id="new_status" name="new_status" class="admin-input admin-order-workflow__status-select">
                      <?php foreach ($allowedStatusTargets as $opt): ?>
                        <option value="<?php echo e($opt); ?>" <?php echo $st === $opt ? 'selected' : ''; ?>><?php echo e(admin_order_status_label($opt)); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline" type="submit">Mettre à jour</button>
                  </form>

                  <?php if ($isOwner): ?>
                    <form method="post" action="">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="action" value="resend_notifications">
                      <button class="btn btn-secondary" type="submit">Renvoyer notifications</button>
                    </form>
                  <?php endif; ?>
                </div>

                <?php if ($nextStatuses): ?>
                  <p class="admin-help admin-order-workflow__next">Prochaine étape recommandée : <strong><?php echo e(admin_order_status_label($nextStatuses[0])); ?></strong></p>
                <?php endif; ?>
              </div>

              <div class="feature-card admin-panel admin-order-panel" aria-label="Historique statuts">
                <div class="admin-order-panel__header">
                  <div>
                    <h3 class="admin-order-panel__title">Historique</h3>
                    <p class="admin-order-panel__subtitle">Trace des changements de statut sur cette commande.</p>
                  </div>
                </div>
                <?php if (!$history): ?>
                  <div class="admin-empty-panel">
                    <p class="admin-empty-panel__text">Aucun changement enregistré pour le moment.</p>
                  </div>
                <?php else: ?>
                  <div class="admin-order-timeline admin-desktop-only" role="list">
                    <?php foreach ($history as $h): ?>
                      <?php
                        $old = (string) ($h['old_status'] ?? '');
                        $new = (string) ($h['new_status'] ?? '');
                        $at = (string) ($h['changed_at'] ?? '');
                        $by = (string) ($h['changed_by_name'] ?? '');
                      ?>
                      <div class="admin-order-timeline__item" role="listitem">
                        <div class="admin-order-timeline__dot" aria-hidden="true"></div>
                        <div class="admin-order-timeline__body">
                          <div class="admin-order-timeline__title">
                            <?php if ($old !== ''): ?>
                              <?php echo e(admin_order_status_label($old)); ?> ->
                            <?php endif; ?>
                            <strong><?php echo e(admin_order_status_label($new)); ?></strong>
                          </div>
                          <div class="admin-order-timeline__meta">
                            <?php echo e($at); ?>
                            <?php if ($by !== ''): ?>
                              - par <?php echo e($by); ?>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <div class="admin-mobile-cards admin-order-mobile-cards admin-order-detail-mobile" aria-label="Historique mobile">
                    <?php foreach ($history as $h): ?>
                      <?php
                        $old = (string) ($h['old_status'] ?? '');
                        $new = (string) ($h['new_status'] ?? '');
                        $at = (string) ($h['changed_at'] ?? '');
                        $by = (string) ($h['changed_by_name'] ?? '');
                      ?>
                      <article class="admin-mobile-card admin-order-detail-card admin-order-history-card">
                        <div class="admin-order-history-card__line" aria-hidden="true"></div>
                        <div class="admin-mobile-card__field">
                          <span class="admin-mobile-card__label">Transition</span>
                          <div class="admin-mobile-card__value">
                            <?php if ($old !== ''): ?>
                              <?php echo e(admin_order_status_label($old)); ?> ->
                            <?php endif; ?>
                            <strong><?php echo e(admin_order_status_label($new)); ?></strong>
                          </div>
                        </div>
                        <div class="admin-mobile-card__grid">
                          <div class="admin-mobile-card__field">
                            <span class="admin-mobile-card__label">Date</span>
                            <div class="admin-mobile-card__value admin-mobile-card__value--muted"><?php echo e($at); ?></div>
                          </div>
                          <div class="admin-mobile-card__field">
                            <span class="admin-mobile-card__label">Par</span>
                            <div class="admin-mobile-card__value admin-mobile-card__value--muted"><?php echo e($by !== '' ? $by : '-'); ?></div>
                          </div>
                        </div>
                      </article>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

              <div class="feature-card admin-panel admin-order-panel" aria-label="Notes internes">
                <div class="admin-order-panel__header">
                  <div>
                    <h3 class="admin-order-panel__title">Notes internes</h3>
                    <p class="admin-order-panel__subtitle">Commentaires visibles uniquement par les administrateurs.</p>
                  </div>
                </div>

                <?php if (!$notes_supported): ?>
                  <div class="admin-empty-panel">
                    <p class="admin-empty-panel__text">Pour activer les notes, executez <code>database/patch_orders_pro.sql</code>.</p>
                  </div>
                <?php else: ?>
                  <div class="admin-order-note-form" aria-label="Ajouter une note">
                    <form method="post" action="" class="auth-form">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="action" value="add_note">
                      <label class="auth-label" for="note">Ajouter une note</label>
                      <textarea id="note" name="note" class="auth-input" rows="3" maxlength="2000" placeholder="Visible uniquement par les admins..."></textarea>
                      <div class="auth-actions">
                        <button class="btn btn-primary" type="submit">Ajouter</button>
                      </div>
                    </form>
                  </div>

                  <?php if (!$notes): ?>
                    <div class="admin-empty-panel">
                      <p class="admin-empty-panel__text">Aucune note.</p>
                    </div>
                  <?php else: ?>
                    <div class="admin-table-shell admin-order-table-shell admin-order-detail-desktop">
                      <table class="admin-table">
                        <thead>
                          <tr>
                            <th>Date</th>
                            <th>Admin</th>
                            <th>Note</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($notes as $n): ?>
                            <?php
                              $at = (string) ($n['created_at'] ?? '');
                              $adminEmail = (string) ($n['admin_email'] ?? '');
                              $adminName = (string) ($n['admin_name'] ?? '');
                              $text = (string) ($n['note'] ?? '');
                              $who = $adminName !== '' ? $adminName : ($adminEmail !== '' ? $adminEmail : '-');
                            ?>
                            <tr>
                              <td><?php echo e($at); ?></td>
                              <td><?php echo e($who); ?></td>
                              <td><?php echo nl2br(e($text)); ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <div class="admin-mobile-cards admin-order-mobile-cards admin-order-detail-mobile" aria-label="Notes internes mobile">
                      <?php foreach ($notes as $n): ?>
                        <?php
                          $at = (string) ($n['created_at'] ?? '');
                          $adminEmail = (string) ($n['admin_email'] ?? '');
                          $adminName = (string) ($n['admin_name'] ?? '');
                          $text = (string) ($n['note'] ?? '');
                          $who = $adminName !== '' ? $adminName : ($adminEmail !== '' ? $adminEmail : '-');
                        ?>
                        <article class="admin-mobile-card admin-order-detail-card">
                          <div class="admin-mobile-card__header">
                            <div>
                              <h4 class="admin-mobile-card__title"><?php echo e($who); ?></h4>
                              <div class="admin-mobile-card__meta"><?php echo e($at); ?></div>
                            </div>
                          </div>
                          <div class="admin-order-note-card__text"><?php echo nl2br(e($text)); ?></div>
                        </article>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../_layout_footer.php'; ?>
