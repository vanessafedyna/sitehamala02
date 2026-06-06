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

$adminRole = admin_current_role();
$isOwner = ($adminRole === 'owner');

$page_title = 'Admin - Commandes';
$page_css = 'pages/admin-products.css';
$page_js = '';

function admin_parse_ymd($raw): string
{
  $raw = trim((string) $raw);
  if ($raw === '') return '';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return '';
  return $raw;
}

function admin_status_norm(string $status): string
{
  $status = strtolower(trim($status));
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
  return $map[$status] ?? $status;
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

/**
 * @return string[]
 */
function admin_allowed_next_statuses(string $current): array
{
  $current = admin_status_norm($current);
  $flow = array('nouveau', 'confirme', 'en_preparation', 'en_livraison', 'livre');
  if ($current === 'livre' || $current === 'annulee') {
    return array();
  }
  $i = array_search($current, $flow, true);
  if ($i === false || !isset($flow[$i + 1])) {
    return array();
  }
  return array($flow[$i + 1]);
}

function admin_quick_action_label(string $status): string
{
  $status = admin_status_norm($status);
  if ($status === 'nouveau') return 'Confirmer';
  if ($status === 'confirme') return 'Préparer';
  if ($status === 'en_preparation') return 'En livraison';
  if ($status === 'en_livraison') return 'Marquer livrée';
  if ($status === 'livre') return 'Terminée';
  if ($status === 'annulee') return 'Annulée';
  return 'Mettre à jour';
}

function admin_order_money(int $amount): string
{
  return number_format($amount, 0, ',', ' ') . ' FCFA';
}

/**
 * @return array{ok:bool,error?:string}
 */
function admin_apply_order_status_update(OrderModel $model, int $orderId, string $currentStatus, string $newStatus, bool $logAudit = true): array
{
  try {
    $oldStatus = admin_status_norm($currentStatus);
    $newStatusNorm = admin_status_norm($newStatus);
    $user = current_admin_user();
    $changedBy = $user ? (int) ($user['id'] ?? 0) : 0;

    $model->updateStatus($orderId, $newStatus, $changedBy > 0 ? $changedBy : null, 'Mise a jour (admin)');

    $pdo = db();
    $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    if ($logAudit) {
      AdminAuditService::log($pdo, $adminId, 'order_status_changed', 'order', $orderId, array(
        'actor_role' => admin_current_role(),
        'old_status' => $oldStatus,
        'new_status' => $newStatusNorm,
      ));
    }

    $fresh = $model->findById($orderId);
    if ($fresh && $oldStatus !== $newStatusNorm && admin_status_norm((string) ($fresh['status'] ?? '')) === 'en_livraison') {
      NotificationService::notifyClientShipped($fresh);
    }

    if (class_exists('Logger')) {
      Logger::info('order_status_updated', array(
        'order_id' => $orderId,
        'old' => $oldStatus,
        'new' => $newStatusNorm,
        'by' => $changedBy > 0 ? $changedBy : null,
      ));
    }

    return array('ok' => true);
  } catch (Throwable $e) {
    $raw = strtolower(trim((string) $e->getMessage()));
    if (
      (strpos($raw, 'transition') !== false && strpos($raw, 'statut') !== false)
      || strpos($raw, 'statut invalide') !== false
    ) {
      return array('ok' => false, 'error' => 'Transition de statut non autorisée.');
    }
    return array('ok' => false, 'error' => 'Erreur lors de la mise a jour du statut.');
  }
}

$now = new DateTimeImmutable('today');
$thisMonthFrom = $now->modify('first day of this month')->format('Y-m-d');
$thisMonthTo = $now->modify('last day of this month')->format('Y-m-d');
$prevMonthFrom = $now->modify('first day of last month')->format('Y-m-d');
$prevMonthTo = $now->modify('last day of last month')->format('Y-m-d');

$status = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$preset = isset($_GET['preset']) ? trim((string) $_GET['preset']) : '';

$paymentStatus = isset($_GET['payment_status']) ? strtolower(trim((string) $_GET['payment_status'])) : '';
$allowedPaymentStatuses = array('pending', 'paid', 'failed', 'refunded');
if (!in_array($paymentStatus, $allowedPaymentStatuses, true)) {
  $paymentStatus = '';
}
// Compat: on accepte aussi les anciens parametres from/to.
$dateFromRaw = isset($_GET['date_from']) ? (string) $_GET['date_from'] : (string) ($_GET['from'] ?? '');
$dateToRaw = isset($_GET['date_to']) ? (string) $_GET['date_to'] : (string) ($_GET['to'] ?? '');

$dateFrom = admin_parse_ymd($dateFromRaw);
$dateTo = admin_parse_ymd($dateToRaw);

if ($preset === 'this_month') {
  $dateFrom = $thisMonthFrom;
  $dateTo = $thisMonthTo;
} elseif ($preset === 'prev_month') {
  $dateFrom = $prevMonthFrom;
  $dateTo = $prevMonthTo;
} elseif ($dateFrom === '' && $dateTo === '') {
  // Vue mensuelle par defaut.
  $dateFrom = $thisMonthFrom;
  $dateTo = $thisMonthTo;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 20;

$flash = admin_flash_get('orders');
$db_error = '';
$orders = array();
$total = 0;
$lastPage = 1;

$kpi = array(
  'orders_total' => 0,
  'revenue_total' => 0,
  'avg_basket' => 0,
  'delivered_count' => 0,
  'cancelled_count' => 0,
);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    admin_flash_set('orders', 'error', 'Session expirée. Veuillez réessayer.');
    redirect('admin/orders/index.php');
  }

  $action = trim((string) ($_POST['action'] ?? ''));
  if ($action === 'bulk_status') {
    $newStatus = trim((string) ($_POST['new_status'] ?? ''));
    $rawIds = $_POST['order_ids'] ?? array();
    $ids = array_values(array_unique(array_filter(array_map('intval', is_array($rawIds) ? $rawIds : array()), static fn ($id) => $id > 0)));

    if (!$ids || $newStatus === '') {
      admin_flash_set('orders', 'error', 'Selection vide.');
      redirect('admin/orders/index.php');
    }

    try {
      $pdo = db();
      $model = new OrderModel($pdo);
      $updatedCount = 0;
      $skippedCount = 0;
      $errorCount = 0;
      $updatedOldStatuses = array();
      $newStatusNorm = admin_status_norm($newStatus);

      foreach ($ids as $orderId) {
        $order = $model->findById($orderId);
        $currentStatus = $order ? (string) ($order['status'] ?? '') : '';
        if ($currentStatus === '') {
          $skippedCount += 1;
          continue;
        }
        $allowedNext = admin_allowed_next_statuses($currentStatus);
        if (!$allowedNext || $allowedNext[0] !== admin_status_norm($newStatus)) {
          $skippedCount += 1;
          continue;
        }

        $result = admin_apply_order_status_update($model, $orderId, $currentStatus, $newStatus, false);
        if ($result['ok']) {
          $updatedCount += 1;
          $updatedOldStatuses[] = admin_status_norm($currentStatus);
        } else {
          $errorCount += 1;
        }
      }

      if ($updatedCount > 0) {
        $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
        $oldStatusMeta = $updatedOldStatuses ? array_values(array_unique($updatedOldStatuses)) : array();
        AdminAuditService::log($pdo, $adminId, 'order_bulk_status_changed', 'order', null, array(
          'actor_role' => admin_current_role(),
          'old_status' => count($oldStatusMeta) === 1 ? (string) $oldStatusMeta[0] : 'mixed',
          'new_status' => $newStatusNorm,
          'bulk_count' => $updatedCount,
        ));
      }

      if ($updatedCount > 0) {
        $message = $updatedCount . ' commande(s) mises a jour.';
        if ($skippedCount > 0) $message .= ' ' . $skippedCount . ' ignoree(s).';
        if ($errorCount > 0) $message .= ' ' . $errorCount . ' erreur(s).';
        admin_flash_set('orders', 'success', $message);
      } elseif ($skippedCount > 0 && $errorCount === 0) {
        admin_flash_set('orders', 'error', 'Aucune commande compatible avec cette action.');
      } else {
        admin_flash_set('orders', 'error', 'Aucune commande mise a jour.');
      }
    } catch (Throwable $e) {
      admin_flash_set('orders', 'error', 'Erreur lors de la mise a jour du lot.');
    }

    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    redirect('admin/orders/index.php' . ($qs !== '' ? ('?' . $qs) : ''));
  }
  $orderId = (int) ($_POST['order_id'] ?? 0);
  $currentStatus = (string) ($_POST['current_status'] ?? '');
  $newStatus = trim((string) ($_POST['new_status'] ?? ''));

  if ($action !== 'quick_status' || $orderId <= 0 || $newStatus === '') {
    admin_flash_set('orders', 'error', 'Action rapide invalide.');
    redirect('admin/orders/index.php');
  }

  try {
    $pdo = db();
    $model = new OrderModel($pdo);
    $oldStatus = admin_status_norm($currentStatus);
    $newStatusNorm = admin_status_norm($newStatus);
    $user = current_admin_user();
    $changedBy = $user ? (int) ($user['id'] ?? 0) : 0;

    $model->updateStatus($orderId, $newStatus, $changedBy > 0 ? $changedBy : null, 'Mise a jour (admin)');

    $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    AdminAuditService::log($pdo, $adminId, 'order_status_changed', 'order', $orderId, array(
      'actor_role' => admin_current_role(),
      'old_status' => $oldStatus,
      'new_status' => $newStatusNorm,
    ));

    $fresh = $model->findById($orderId);
    if ($fresh && $oldStatus !== $newStatusNorm && admin_status_norm((string) ($fresh['status'] ?? '')) === 'en_livraison') {
      NotificationService::notifyClientShipped($fresh);
    }

    if (class_exists('Logger')) {
      Logger::info('order_status_updated', array(
        'order_id' => $orderId,
        'old' => $oldStatus,
        'new' => $newStatusNorm,
        'by' => $changedBy > 0 ? $changedBy : null,
      ));
    }

    admin_flash_set('orders', 'success', 'Statut mis a jour.');
  } catch (Throwable $e) {
    $raw = strtolower(trim((string) $e->getMessage()));
    if (
      (strpos($raw, 'transition') !== false && strpos($raw, 'statut') !== false)
      || strpos($raw, 'statut invalide') !== false
    ) {
      admin_flash_set('orders', 'error', 'Transition de statut non autorisée.');
    } else {
      admin_flash_set('orders', 'error', 'Erreur lors de la mise a jour du statut.');
    }
  }

  $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
  redirect('admin/orders/index.php' . ($qs !== '' ? ('?' . $qs) : ''));
}

try {
  $pdo = db();
  $model = new OrderModel($pdo);

 $filters = array();
if ($status !== '') $filters['status'] = $status;
if ($paymentStatus !== '') $filters['payment_status'] = $paymentStatus;
if ($q !== '') $filters['q'] = $q;
if ($dateFrom !== '') $filters['from'] = $dateFrom;
if ($dateTo !== '') $filters['to'] = $dateTo;

  $total = $model->countAll($filters);
  $lastPage = max(1, (int) ceil($total / $perPage));
  $page = min($page, $lastPage);

  $offset = ($page - 1) * $perPage;
  $orders = $model->all(array_merge($filters, array(
    'limit' => $perPage,
    'offset' => $offset,
  )));

  if ($isOwner) {
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

    $sqlKpi = 'SELECT
        COUNT(*) AS orders_total,
        COALESCE(SUM(CASE WHEN o.status IN (:delivered_status_ca_new, :delivered_status_ca_legacy) THEN COALESCE(o.total_amount, 0) ELSE 0 END), 0) AS revenue_total,
        COALESCE(SUM(CASE WHEN o.status IN (:delivered_status_new, :delivered_status_legacy) THEN 1 ELSE 0 END), 0) AS delivered_count,
        COALESCE(SUM(CASE WHEN o.status = :cancel_status_count THEN 1 ELSE 0 END), 0) AS cancelled_count,
        COALESCE(SUM(CASE WHEN o.status IN (:delivered_status_avg_new, :delivered_status_avg_legacy) THEN 1 ELSE 0 END), 0) AS valid_orders
      FROM orders o'
      . $whereSql;

    $stmtKpi = $pdo->prepare($sqlKpi);
    foreach ($params as $key => $value) {
      $stmtKpi->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $stmtKpi->bindValue(':cancel_status_count', 'annulee', PDO::PARAM_STR);
    $stmtKpi->bindValue(':delivered_status_ca_new', 'livre', PDO::PARAM_STR);
    $stmtKpi->bindValue(':delivered_status_ca_legacy', 'livree', PDO::PARAM_STR);
    $stmtKpi->bindValue(':delivered_status_new', 'livre', PDO::PARAM_STR);
    $stmtKpi->bindValue(':delivered_status_legacy', 'livree', PDO::PARAM_STR);
    $stmtKpi->bindValue(':delivered_status_avg_new', 'livre', PDO::PARAM_STR);
    $stmtKpi->bindValue(':delivered_status_avg_legacy', 'livree', PDO::PARAM_STR);
    $stmtKpi->execute();
    $rowKpi = $stmtKpi->fetch(PDO::FETCH_ASSOC) ?: array();

    $ordersTotal = (int) ($rowKpi['orders_total'] ?? 0);
    $revenueTotal = (int) ($rowKpi['revenue_total'] ?? 0);
    $deliveredCount = (int) ($rowKpi['delivered_count'] ?? 0);
    $cancelledCount = (int) ($rowKpi['cancelled_count'] ?? 0);
    $validOrders = (int) ($rowKpi['valid_orders'] ?? 0);

    $kpi['orders_total'] = $ordersTotal;
    $kpi['revenue_total'] = $revenueTotal;
    $kpi['avg_basket'] = $validOrders > 0 ? (int) round($revenueTotal / $validOrders) : 0;
    $kpi['delivered_count'] = $deliveredCount;
    $kpi['cancelled_count'] = $cancelledCount;
  }
} catch (Throwable $e) {
  $db_error = 'Impossible de charger les commandes (base de données).';
  $orders = array();
  $total = 0;
  $lastPage = 1;
  $page = 1;
}

require_once __DIR__ . '/../_layout_header.php';
?>

<link rel="stylesheet" href="<?php echo e(base_url('assets/css/pages/admin-orders.css')); ?>">

<script src="<?php echo e(base_url('assets/js/pages/admin-orders.js?v=' . (string) @filemtime(__DIR__ . '/../../assets/js/pages/admin-orders.js'))); ?>"></script>

<main id="main" class="admin-orders-page">
  <section>
    <div class="container">
      <div class="admin-page-header admin-orders-reveal is-visible">
        <div class="admin-page-header__content">
          <p class="admin-page-header__eyebrow">Ventes</p>
          <h1 class="admin-page-header__title">Commandes</h1>
          <p class="admin-page-header__subtitle">Pilotez les commandes, les changements de statut et les actions prioritaires depuis une vue admin plus claire et plus premium.</p>
        </div>
        <div class="admin-page-header__actions">
          <a class="admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">
            <i class="fas fa-gauge-high" aria-hidden="true"></i> Retour au tableau de bord
          </a>
        </div>
      </div>

      <div class="admin-orders-stack">

      <?php if ($flash): ?>
        <div class="admin-alert admin-alert--<?php echo e($flash['type']); ?> admin-panel admin-panel--padded admin-orders-reveal is-visible" role="status" aria-live="polite">
          <strong><?php echo e($flash['message']); ?></strong>
        </div>
      <?php endif; ?>

      <?php if ($db_error): ?>
        <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-orders-reveal is-visible" role="alert">
          <strong><?php echo e($db_error); ?></strong>
        </div>
      <?php else: ?>
        <?php
          $isViewConfirm = ($status === 'nouveau');
          $isViewPayment = ($status === 'confirme');
          $isViewAll = ($status === '');
          $hasExplicitFilters = ($status !== '' || $q !== '' || $preset !== '' || admin_parse_ymd($dateFromRaw) !== '' || admin_parse_ymd($dateToRaw) !== '');
          $activeViewLabel = 'Toutes les commandes';
          if ($isViewConfirm) {
            $activeViewLabel = 'À confirmer';
          } elseif ($isViewPayment) {
            $activeViewLabel = 'En attente paiement';
          }
        ?>
        <div class="admin-panel admin-panel--padded admin-orders-reveal" aria-label="Vues prioritaires commandes">
          <div class="admin-orders-viewbar">
            <div class="admin-orders-viewbar__intro">
              <strong>Vues prioritaires</strong>
              <div class="admin-help">Commandes à traiter</div>
            </div>
            <div class="admin-orders-viewbar__actions">
              <a class="admin-btn <?php echo $isViewConfirm ? 'admin-btn--primary' : 'admin-btn--secondary'; ?>" href="<?php echo e(base_url('admin/orders/index.php?status=nouveau')); ?>">À confirmer</a>
              <a class="admin-btn <?php echo $isViewPayment ? 'admin-btn--primary' : 'admin-btn--secondary'; ?>" href="<?php echo e(base_url('admin/orders/index.php?status=confirme')); ?>">En attente paiement</a>
              <a class="admin-btn <?php echo $isViewAll ? 'admin-btn--primary' : 'admin-btn--secondary'; ?>" href="<?php echo e(base_url('admin/orders/index.php')); ?>">Toutes les commandes</a>
            </div>
          </div>
        </div>

        <?php if ($isOwner): ?>
          <div class="admin-desktop-only" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:12px 0 16px;">
            <div class="feature-card"><div class="admin-help">Total commandes</div><strong><?php echo e((string) $kpi['orders_total']); ?></strong></div>
            <div class="feature-card"><div class="admin-help">CA reel (livrees)</div><strong><?php echo e(admin_order_money((int) $kpi['revenue_total'])); ?></strong></div>
            <div class="feature-card"><div class="admin-help">Panier moyen</div><strong><?php echo e(admin_order_money((int) $kpi['avg_basket'])); ?></strong></div>
            <div class="feature-card"><div class="admin-help">Livrées</div><strong><?php echo e((string) $kpi['delivered_count']); ?></strong></div>
            <div class="feature-card"><div class="admin-help">Annulées</div><strong><?php echo e((string) $kpi['cancelled_count']); ?></strong></div>
          </div>

          <details class="admin-mobile-only admin-mobile-section admin-panel admin-orders-reveal" aria-label="Statistiques">
            <summary class="admin-mobile-section__summary">
              <span>
                <strong>Statistiques</strong>
                <span class="admin-help">Vue rapide</span>
              </span>
              <span class="admin-mobile-section__chevron" aria-hidden="true">+</span>
            </summary>
            <div class="admin-mobile-section__body">
              <div class="admin-mobile-kpis-row">
                <div class="admin-mobile-kpi-chip">
                  <span class="admin-help">Commandes</span>
                  <strong><?php echo e((string) $kpi['orders_total']); ?></strong>
                </div>
                <div class="admin-mobile-kpi-chip">
                  <span class="admin-help">CA reel</span>
                  <strong><?php echo e(admin_order_money((int) $kpi['revenue_total'])); ?></strong>
                </div>
                <div class="admin-mobile-kpi-chip">
                  <span class="admin-help">Panier</span>
                  <strong><?php echo e(admin_order_money((int) $kpi['avg_basket'])); ?></strong>
                </div>
                <div class="admin-mobile-kpi-chip">
                  <span class="admin-help">Livrées</span>
                  <strong><?php echo e((string) $kpi['delivered_count']); ?></strong>
                </div>
                <div class="admin-mobile-kpi-chip">
                  <span class="admin-help">Annulées</span>
                  <strong><?php echo e((string) $kpi['cancelled_count']); ?></strong>
                </div>
              </div>
            </div>
          </details>
        <?php endif; ?>

        <div class="admin-toolbar admin-filterbar admin-orders-reveal admin-desktop-only" aria-label="Filtres commandes">
          <form method="get" action="" class="admin-toolbar__search admin-filterbar__group admin-filterbar__group--grow">
            <label class="sr-only" for="status">Statut</label>
            <select id="status" name="status" class="admin-input" style="min-width:220px;">
              <option value="">Tous les statuts</option>
              <?php foreach (OrderModel::STATUSES as $s): ?>
                <option value="<?php echo e($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>>
                  <?php echo e(admin_order_status_label($s)); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <label class="sr-only" for="date_from">Date début</label>
            <input id="date_from" name="date_from" type="date" class="admin-input" value="<?php echo e($dateFrom); ?>">

            <label class="sr-only" for="date_to">Date fin</label>
            <input id="date_to" name="date_to" type="date" class="admin-input" value="<?php echo e($dateTo); ?>">

            <label class="sr-only" for="q">Recherche</label>
            <input
              class="admin-input"
              id="q"
              name="q"
              type="text"
              value="<?php echo e($q); ?>"
              placeholder="Numéro ou téléphone"
            >

            <button class="btn admin-btn admin-btn--secondary" type="submit">
              <i class="fas fa-search" aria-hidden="true"></i> Filtrer
            </button>

            <button class="btn admin-btn admin-btn--secondary" type="submit" name="preset" value="this_month">Ce mois</button>
            <button class="btn admin-btn admin-btn--secondary" type="submit" name="preset" value="prev_month">Mois précédent</button>

            <?php if ($status !== '' || $q !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
              <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/orders/index.php')); ?>">Réinitialiser</a>
            <?php endif; ?>

            <?php if ($isOwner): ?>
              <?php
                $exportQs = array();
                if ($dateFrom !== '') $exportQs['date_from'] = $dateFrom;
                if ($dateTo !== '') $exportQs['date_to'] = $dateTo;
                $exportHref = base_url('admin/exports/orders_export.php' . ($exportQs ? ('?' . http_build_query($exportQs)) : ''));
              ?>
              <a class="btn admin-btn admin-btn--primary" href="<?php echo e($exportHref); ?>">
                <i class="fas fa-file-csv" aria-hidden="true"></i> Exporter CSV (période)
              </a>
            <?php endif; ?>
          </form>

          <div class="admin-help"><?php echo e((string) $total); ?> commande(s)</div>
        </div>

        <details class="admin-mobile-only admin-mobile-section admin-panel admin-orders-reveal" aria-label="Filtres">
          <summary class="admin-mobile-section__summary">
            <span>
              <strong>Filtres</strong>
              <span class="admin-help"><?php echo e((string) $total); ?> commande(s)</span>
            </span>
            <span class="admin-mobile-section__chevron" aria-hidden="true">+</span>
          </summary>
          <div class="admin-mobile-section__body">
            <form method="get" action="" class="admin-toolbar__search admin-filterbar__group admin-filterbar__group--grow">
              <label class="sr-only" for="status_mobile">Statut</label>
              <select id="status_mobile" name="status" class="admin-input">
                <option value="">Tous les statuts</option>
                <?php foreach (OrderModel::STATUSES as $s): ?>
                  <option value="<?php echo e($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>>
                    <?php echo e(admin_order_status_label($s)); ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <label class="sr-only" for="date_from_mobile">Date début</label>
              <input id="date_from_mobile" name="date_from" type="date" class="admin-input" value="<?php echo e($dateFrom); ?>">

              <label class="sr-only" for="date_to_mobile">Date fin</label>
              <input id="date_to_mobile" name="date_to" type="date" class="admin-input" value="<?php echo e($dateTo); ?>">

              <label class="sr-only" for="q_mobile">Recherche</label>
              <input
                class="admin-input"
                id="q_mobile"
                name="q"
                type="text"
                value="<?php echo e($q); ?>"
                placeholder="Numéro ou téléphone"
              >

              <button class="btn admin-btn admin-btn--secondary" type="submit">
                <i class="fas fa-search" aria-hidden="true"></i> Filtrer
              </button>
              <button class="btn admin-btn admin-btn--secondary" type="submit" name="preset" value="this_month">Ce mois</button>
              <button class="btn admin-btn admin-btn--secondary" type="submit" name="preset" value="prev_month">Mois précédent</button>

              <?php if ($status !== '' || $q !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/orders/index.php')); ?>">Réinitialiser</a>
              <?php endif; ?>

              <?php if ($isOwner): ?>
                <?php
                  $exportQs = array();
                  if ($dateFrom !== '') $exportQs['date_from'] = $dateFrom;
                  if ($dateTo !== '') $exportQs['date_to'] = $dateTo;
                  $exportHref = base_url('admin/exports/orders_export.php' . ($exportQs ? ('?' . http_build_query($exportQs)) : ''));
                ?>
                <a class="btn admin-btn admin-btn--primary" href="<?php echo e($exportHref); ?>">
                  <i class="fas fa-file-csv" aria-hidden="true"></i> Exporter CSV (période)
                </a>
              <?php endif; ?>
            </form>
          </div>
        </details>

        <form id="bulkOrdersForm" method="post" action="">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="bulk_status">

          <div class="admin-orders-bulkbar admin-orders-reveal" aria-label="Actions en lot">
            <div class="admin-orders-bulkbar__meta">
              <label class="admin-orders-toggle">
                <input class="admin-orders-check" type="checkbox" data-bulk-toggle aria-label="Sélectionner tout">
                <span>Sélectionner tout</span>
              </label>
              <span class="admin-help" data-bulk-count>0 sélectionnée(s)</span>
            </div>
            <div class="admin-orders-bulkbar__actions">
              <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="submit" name="new_status" value="confirme">Confirmer la sélection</button>
              <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="submit" name="new_status" value="en_preparation">Mettre en préparation</button>
              <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="submit" name="new_status" value="en_livraison">Marquer en livraison</button>
            </div>
          </div>

        </form>

        <div class="feature-card admin-panel admin-table-shell admin-orders-table-shell admin-table-wrap admin-desktop-only admin-orders-reveal" aria-label="Liste commandes">
          <table class="admin-table">
            <thead>
              <tr>
                <th>
                  <input class="admin-orders-check" type="checkbox" data-bulk-toggle aria-label="Selectionner tout">
                </th>
                <th>Commande</th>
                <th>Client</th>
                <th>Téléphone</th>
                <th class="admin-col-num">Total</th>
                <th>Statut</th>
                <th>Créée</th>
                <th>Action rapide</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$orders): ?>
                <tr>
                  <td colspan="8" class="admin-empty-row">
                    <div class="admin-empty-state admin-empty-panel">
                      <?php if ($isViewConfirm || $isViewPayment): ?>
                        <p class="admin-empty-state__title admin-empty-panel__title">Aucune commande dans la vue "<?php echo e($activeViewLabel); ?>".</p>
                        <p class="admin-empty-state__text admin-empty-panel__text">Cette file est vide pour le moment. Vous pouvez consulter l'ensemble des commandes.</p>
                      <?php elseif ($hasExplicitFilters): ?>
                        <p class="admin-empty-state__title admin-empty-panel__title">Aucune commande ne correspond aux filtres appliqués.</p>
                        <p class="admin-empty-state__text admin-empty-panel__text">Ajustez la recherche, la période ou le statut pour afficher des résultats.</p>
                      <?php else: ?>
                        <p class="admin-empty-state__title admin-empty-panel__title">Aucune commande à afficher pour le moment.</p>
                        <p class="admin-empty-state__text admin-empty-panel__text">Les nouvelles commandes apparaîtront ici dès leur création.</p>
                      <?php endif; ?>
                      <div class="admin-empty-state__actions admin-empty-panel__actions">
                        <?php if ($isViewConfirm || $isViewPayment || $hasExplicitFilters): ?>
                          <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/orders/index.php')); ?>">Voir toutes les commandes</a>
                        <?php endif; ?>
                        <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">Retour au tableau de bord</a>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>

              <?php foreach ($orders as $o): ?>
                <?php
                  $id = (int) ($o['id'] ?? 0);
                  $num = (string) ($o['order_number'] ?? '');
                  $name = (string) ($o['customer_name'] ?? '');
                  $phone = (string) ($o['customer_phone'] ?? '');
                  $couponCode = strtoupper(trim((string) ($o['coupon_code'] ?? '')));
                  $discountAmount = (int) ($o['discount_amount'] ?? 0);
                  $hasPromo = ($discountAmount > 0 || $couponCode !== '');
                  $totalAmount = (int) ($o['total_amount'] ?? 0);
                  $st = admin_status_norm((string) ($o['status'] ?? ''));
                  $createdAt = (string) ($o['created_at'] ?? '');
                  $nextStatuses = admin_allowed_next_statuses($st);
                  $quickStatus = $nextStatuses[0] ?? '';
                  $quickLabel = admin_quick_action_label($st);
                ?>
                <tr>
                  <td>
                    <input class="admin-orders-check" type="checkbox" name="order_ids[]" value="<?php echo (int) $id; ?>" data-bulk-item form="bulkOrdersForm">
                    <input type="hidden" name="current_statuses[<?php echo (int) $id; ?>]" value="<?php echo e($st); ?>" form="bulkOrdersForm">
                  </td>
                  <td class="admin-order-number">
                    <strong><?php echo e($num); ?></strong>
                    <div class="admin-help admin-order-meta">ID #<?php echo e((string) $id); ?></div>
                    <?php if ($hasPromo): ?>
                      <div class="admin-help admin-order-meta">Promo<?php echo $couponCode !== '' ? (': ' . e($couponCode)) : ''; ?></div>
                    <?php endif; ?>
                  </td>
                  <td><strong><?php echo e($name); ?></strong></td>
                  <td><?php echo e($phone); ?></td>
                  <td class="admin-col-num"><?php echo e((string) $totalAmount); ?> FCFA</td>
                  <td><div class="admin-order-status"><span class="<?php echo e(admin_order_badge_class($st)); ?>"><?php echo e(admin_order_status_label($st)); ?></span></div></td>
                  <td class="admin-order-date"><?php echo e($createdAt); ?></td>
                  <td>
                    <div class="admin-order-quick">
                      <div class="admin-order-quick__label">Action rapide</div>
                      <div class="admin-order-quick__actions">
                        <?php if ($quickStatus !== ''): ?>
                          <form method="post" action="">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="quick_status">
                            <input type="hidden" name="order_id" value="<?php echo (int) $id; ?>">
                            <input type="hidden" name="current_status" value="<?php echo e($st); ?>">
                            <input type="hidden" name="new_status" value="<?php echo e($quickStatus); ?>">
                            <button class="btn admin-btn admin-btn--primary admin-btn--sm admin-order-quick__btn" type="submit"><?php echo e($quickLabel); ?></button>
                          </form>
                        <?php else: ?>
                          <span class="admin-help"><?php echo e($quickLabel); ?></span>
                        <?php endif; ?>
                        <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/orders/show.php?id=' . $id)); ?>">Voir</a>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="admin-mobile-only admin-orders-reveal admin-orders-mobile-list" aria-label="Liste commandes mobile">
          <?php if (!$orders): ?>
            <div class="admin-mobile-cards">
              <div class="admin-mobile-card admin-orders-mobile-card">
                <div class="admin-empty-state admin-empty-panel">
                  <?php if ($isViewConfirm || $isViewPayment): ?>
                    <p class="admin-empty-state__title admin-empty-panel__title">Aucune commande dans la vue "<?php echo e($activeViewLabel); ?>".</p>
                    <p class="admin-empty-state__text admin-empty-panel__text">Cette file est vide pour le moment. Vous pouvez consulter l'ensemble des commandes.</p>
                  <?php elseif ($hasExplicitFilters): ?>
                    <p class="admin-empty-state__title admin-empty-panel__title">Aucune commande ne correspond aux filtres appliqués.</p>
                    <p class="admin-empty-state__text admin-empty-panel__text">Ajustez la recherche, la période ou le statut pour afficher des résultats.</p>
                  <?php else: ?>
                    <p class="admin-empty-state__title admin-empty-panel__title">Aucune commande à afficher pour le moment.</p>
                    <p class="admin-empty-state__text admin-empty-panel__text">Les nouvelles commandes apparaîtront ici dès leur création.</p>
                  <?php endif; ?>
                  <div class="admin-empty-state__actions admin-empty-panel__actions">
                    <?php if ($isViewConfirm || $isViewPayment || $hasExplicitFilters): ?>
                      <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/orders/index.php')); ?>">Voir toutes les commandes</a>
                    <?php endif; ?>
                    <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">Retour au tableau de bord</a>
                  </div>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="admin-mobile-cards admin-orders-mobile-cards">
              <?php foreach ($orders as $o): ?>
                <?php
                  $id = (int) ($o['id'] ?? 0);
                  $num = (string) ($o['order_number'] ?? '');
                  $name = (string) ($o['customer_name'] ?? '');
                  $phone = (string) ($o['customer_phone'] ?? '');
                  $couponCode = strtoupper(trim((string) ($o['coupon_code'] ?? '')));
                  $discountAmount = (int) ($o['discount_amount'] ?? 0);
                  $hasPromo = ($discountAmount > 0 || $couponCode !== '');
                  $totalAmount = (int) ($o['total_amount'] ?? 0);
                  $st = admin_status_norm((string) ($o['status'] ?? ''));
                  $createdAt = (string) ($o['created_at'] ?? '');
                  $nextStatuses = admin_allowed_next_statuses($st);
                  $quickStatus = $nextStatuses[0] ?? '';
                  $quickLabel = admin_quick_action_label($st);
                ?>
                <article class="admin-mobile-card admin-orders-mobile-card">
                  <div class="admin-mobile-card__header">
                    <div>
                      <h2 class="admin-mobile-card__title"><?php echo e($num); ?></h2>
                      <div class="admin-mobile-card__meta">ID #<?php echo e((string) $id); ?></div>
                      <?php if ($hasPromo): ?>
                        <div class="admin-mobile-card__meta">Promo<?php echo $couponCode !== '' ? (': ' . e($couponCode)) : ''; ?></div>
                      <?php endif; ?>
                    </div>
                    <span class="<?php echo e(admin_order_badge_class($st)); ?>"><?php echo e(admin_order_status_label($st)); ?></span>
                  </div>

                  <div class="admin-mobile-card__grid">
                    <div class="admin-mobile-card__field">
                      <span class="admin-mobile-card__label">Client</span>
                      <div class="admin-mobile-card__value"><?php echo e($name); ?></div>
                    </div>
                    <div class="admin-mobile-card__field">
                      <span class="admin-mobile-card__label">Montant</span>
                      <div class="admin-mobile-card__value"><?php echo e(admin_order_money($totalAmount)); ?></div>
                    </div>
                    <div class="admin-mobile-card__field">
                      <span class="admin-mobile-card__label">Téléphone</span>
                      <div class="admin-mobile-card__value"><?php echo e($phone); ?></div>
                    </div>
                    <div class="admin-mobile-card__field">
                      <span class="admin-mobile-card__label">Date</span>
                      <div class="admin-mobile-card__value admin-mobile-card__value--muted"><?php echo e($createdAt); ?></div>
                    </div>
                  </div>

                  <div class="admin-mobile-card__actions">
                    <label class="admin-mobile-check">
                      <input class="admin-orders-check" type="checkbox" name="order_ids[]" value="<?php echo (int) $id; ?>" data-bulk-item form="bulkOrdersForm">
                      <input type="hidden" name="current_statuses[<?php echo (int) $id; ?>]" value="<?php echo e($st); ?>" form="bulkOrdersForm">
                      <span>Sélectionner pour le lot</span>
                    </label>
                    <div class="admin-order-quick__actions">
                      <?php if ($quickStatus !== ''): ?>
                        <form method="post" action="">
                          <?php echo csrf_field(); ?>
                          <input type="hidden" name="action" value="quick_status">
                          <input type="hidden" name="order_id" value="<?php echo (int) $id; ?>">
                          <input type="hidden" name="current_status" value="<?php echo e($st); ?>">
                          <input type="hidden" name="new_status" value="<?php echo e($quickStatus); ?>">
                          <button class="btn admin-btn admin-btn--primary admin-order-quick__btn" type="submit"><?php echo e($quickLabel); ?></button>
                        </form>
                      <?php else: ?>
                        <span class="admin-help"><?php echo e($quickLabel); ?></span>
                      <?php endif; ?>
                      <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/orders/show.php?id=' . $id)); ?>">Voir</a>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($lastPage > 1): ?>
          <nav class="admin-pagination" aria-label="Pagination commandes">
            <?php
              $qsBase = array();
              if ($status !== '') $qsBase['status'] = $status;
              if ($q !== '') $qsBase['q'] = $q;
              if ($dateFrom !== '') $qsBase['date_from'] = $dateFrom;
              if ($dateTo !== '') $qsBase['date_to'] = $dateTo;
            ?>

            <?php if ($page > 1): ?>
              <?php $qs = http_build_query(array_merge($qsBase, array('page' => $page - 1))); ?>
              <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/orders/index.php?' . $qs)); ?>">Précédent</a>
            <?php endif; ?>

            <span class="admin-help">Page <?php echo e((string) $page); ?> / <?php echo e((string) $lastPage); ?></span>

            <?php if ($page < $lastPage): ?>
              <?php $qs = http_build_query(array_merge($qsBase, array('page' => $page + 1))); ?>
              <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/orders/index.php?' . $qs)); ?>">Suivant</a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../_layout_footer.php'; ?>
