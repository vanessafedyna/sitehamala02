<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
requireAnyRole(array('owner'));
require_once __DIR__ . '/../../app/config/database.php';

$page_title = 'Admin - Chiffre d\'affaires';
$page_css = 'pages/admin-products.css';
$page_js = '';

function admin_revenue_timezone(): DateTimeZone
{
  static $timezone = null;
  if ($timezone instanceof DateTimeZone) {
    return $timezone;
  }

  $candidate = '';
  if (function_exists('env')) {
    $candidate = trim((string) env('APP_TIMEZONE', env('TIMEZONE', '')));
  }

  if ($candidate === '') {
    $candidate = 'Africa/Bamako';
  }

  try {
    $timezone = new DateTimeZone($candidate);
  } catch (Throwable $e) {
    $timezone = new DateTimeZone('Africa/Bamako');
  }

  return $timezone;
}

function admin_revenue_today(): DateTimeImmutable
{
  return (new DateTimeImmutable('now', admin_revenue_timezone()))->setTime(0, 0, 0);
}

function admin_revenue_money(int $amount): string
{
  return number_format($amount, 0, ',', ' ') . ' FCFA';
}

function admin_revenue_parse_ymd($raw): string
{
  $raw = trim((string) $raw);
  if ($raw === '') return '';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return '';

  $dt = DateTimeImmutable::createFromFormat('Y-m-d', $raw, admin_revenue_timezone());
  if (!$dt || $dt->format('Y-m-d') !== $raw) return '';

  return $raw;
}

function admin_revenue_status_label(string $status): string
{
  $status = strtolower(trim($status));
  $map = array(
    'nouvelle' => 'Nouvelle',
    'nouveau' => 'Nouveau',
    'confirmee' => 'Confirmée',
    'confirme' => 'Confirmée',
    'preparee' => 'Préparée',
    'en_preparation' => 'En préparation',
    'en_livraison' => 'En livraison',
    'livre' => 'Livrée',
    'livree' => 'Livrée',
    'annulee' => 'Annulée',
    'annulée' => 'Annulée',
    'cancelled' => 'Annulée',
  );

  return $map[$status] ?? $status;
}

function admin_revenue_month_label(string $ym): string
{
  $months = array(
    '01' => 'Jan',
    '02' => 'Fev',
    '03' => 'Mar',
    '04' => 'Avr',
    '05' => 'Mai',
    '06' => 'Jun',
    '07' => 'Jul',
    '08' => 'Aou',
    '09' => 'Sep',
    '10' => 'Oct',
    '11' => 'Nov',
    '12' => 'Dec',
  );

  $year = substr($ym, 0, 4);
  $month = substr($ym, 5, 2);
  return ($months[$month] ?? $month) . ' ' . $year;
}

function admin_revenue_total_expr(array $orderCols, string $alias = ''): string
{
  $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

  if (in_array('total_amount', $orderCols, true)) {
    return 'COALESCE(' . $prefix . 'total_amount, 0)';
  }
  if (in_array('grand_total', $orderCols, true)) {
    return 'COALESCE(' . $prefix . 'grand_total, 0)';
  }
  if (in_array('total_fcfa', $orderCols, true)) {
    return 'COALESCE(' . $prefix . 'total_fcfa, 0)';
  }

  return '0';
}

/**
 * @return array{preset:string,date_from:string,date_to:string,error:string}
 */
function admin_revenue_resolve_period(string $preset, string $dateFromRaw, string $dateToRaw): array
{
  $today = admin_revenue_today();
  $preset = strtolower(trim($preset));
  $allowed = array('today', 'week', 'month', 'year', 'custom');
  if (!in_array($preset, $allowed, true)) {
    $preset = 'month';
  }

  $monthStart = $today->modify('first day of this month')->format('Y-m-d');
  $monthEnd = $today->modify('last day of this month')->format('Y-m-d');

  if ($preset === 'today') {
    $date = $today->format('Y-m-d');
    return array('preset' => 'today', 'date_from' => $date, 'date_to' => $date, 'error' => '');
  }

  if ($preset === 'week') {
    return array(
      'preset' => 'week',
      'date_from' => $today->modify('monday this week')->format('Y-m-d'),
      'date_to' => $today->modify('sunday this week')->format('Y-m-d'),
      'error' => '',
    );
  }

  if ($preset === 'month') {
    return array('preset' => 'month', 'date_from' => $monthStart, 'date_to' => $monthEnd, 'error' => '');
  }

  if ($preset === 'year') {
    return array(
      'preset' => 'year',
      'date_from' => $today->setDate((int) $today->format('Y'), 1, 1)->format('Y-m-d'),
      'date_to' => $today->setDate((int) $today->format('Y'), 12, 31)->format('Y-m-d'),
      'error' => '',
    );
  }

  $dateFrom = admin_revenue_parse_ymd($dateFromRaw);
  $dateTo = admin_revenue_parse_ymd($dateToRaw);
  if ($dateFrom === '' || $dateTo === '') {
    return array(
      'preset' => 'custom',
      'date_from' => trim($dateFromRaw),
      'date_to' => trim($dateToRaw),
      'error' => 'Renseignez une date de debut et une date de fin valides.',
    );
  }
  if ($dateFrom > $dateTo) {
    return array(
      'preset' => 'custom',
      'date_from' => $dateFrom,
      'date_to' => $dateTo,
      'error' => 'La date de debut doit etre inferieure ou egale a la date de fin.',
    );
  }

  return array('preset' => 'custom', 'date_from' => $dateFrom, 'date_to' => $dateTo, 'error' => '');
}

$presetRaw = isset($_GET['preset']) ? (string) $_GET['preset'] : 'month';
$dateFromRaw = isset($_GET['date_from']) ? (string) $_GET['date_from'] : '';
$dateToRaw = isset($_GET['date_to']) ? (string) $_GET['date_to'] : '';

$period = admin_revenue_resolve_period($presetRaw, $dateFromRaw, $dateToRaw);
$preset = $period['preset'];
$dateFrom = $period['date_from'];
$dateTo = $period['date_to'];
$filterError = $period['error'];

$kpi = array(
  'revenue_today' => 0,
  'revenue_week' => 0,
  'revenue_month' => 0,
  'revenue_year' => 0,
  'delivered_count' => 0,
  'cancelled_count' => 0,
  'delivery_rate' => 0,
  'cancellation_rate' => 0,
  'avg_basket' => 0,
  'selected_revenue' => 0,
);
$orders = array();
$topProducts = array();
$monthlyTrend = array();
$db_error = '';

if ($filterError === '') {
try {
  $pdo = db();
  $today = admin_revenue_today();
  $orderItemCols = function_exists('db_table_columns') ? db_table_columns($pdo, 'order_items') : array();
  $orderCols = function_exists('db_table_columns') ? db_table_columns($pdo, 'orders') : array();
  $orderRevenueTotalExpr = admin_revenue_total_expr($orderCols);
  $orderRevenueDateExpr = 'created_at';
  $orderRevenueDateExprOrder = 'o.created_at';
  if (in_array('delivered_at', $orderCols, true) && in_array('status_updated_at', $orderCols, true)) {
    $orderRevenueDateExpr = 'COALESCE(delivered_at, status_updated_at, created_at)';
    $orderRevenueDateExprOrder = 'COALESCE(o.delivered_at, o.status_updated_at, o.created_at)';
  } elseif (in_array('delivered_at', $orderCols, true)) {
    $orderRevenueDateExpr = 'COALESCE(delivered_at, created_at)';
    $orderRevenueDateExprOrder = 'COALESCE(o.delivered_at, o.created_at)';
  } elseif (in_array('status_updated_at', $orderCols, true)) {
    $orderRevenueDateExpr = 'COALESCE(status_updated_at, created_at)';
    $orderRevenueDateExprOrder = 'COALESCE(o.status_updated_at, o.created_at)';
  }

  $itemNameCol = in_array('product_name_snapshot', $orderItemCols, true) ? 'oi.product_name_snapshot'
    : (in_array('product_name', $orderItemCols, true) ? 'oi.product_name' : "''");
  $itemQtyCol = in_array('qty', $orderItemCols, true) ? 'oi.qty'
    : (in_array('quantity', $orderItemCols, true) ? 'oi.quantity' : '0');
  $itemUnitCol = in_array('unit_price_snapshot', $orderItemCols, true) ? 'oi.unit_price_snapshot'
    : (in_array('price_fcfa', $orderItemCols, true) ? 'oi.price_fcfa' : (in_array('unit_price', $orderItemCols, true) ? 'oi.unit_price' : (in_array('price', $orderItemCols, true) ? 'oi.price' : '0')));
  $itemLineCol = in_array('line_total', $orderItemCols, true) ? 'oi.line_total'
    : (in_array('subtotal_fcfa', $orderItemCols, true) ? 'oi.subtotal_fcfa' : (in_array('subtotal', $orderItemCols, true) ? 'oi.subtotal' : '0'));

  $ranges = array(
    'today' => array(
      'from' => $today->format('Y-m-d 00:00:00'),
      'to' => $today->format('Y-m-d 23:59:59'),
    ),
    'week' => array(
      'from' => $today->modify('monday this week')->format('Y-m-d 00:00:00'),
      'to' => $today->modify('sunday this week')->format('Y-m-d 23:59:59'),
    ),
    'month' => array(
      'from' => $today->modify('first day of this month')->format('Y-m-d 00:00:00'),
      'to' => $today->modify('last day of this month')->format('Y-m-d 23:59:59'),
    ),
    'year' => array(
      'from' => $today->setDate((int) $today->format('Y'), 1, 1)->format('Y-m-d 00:00:00'),
      'to' => $today->setDate((int) $today->format('Y'), 12, 31)->format('Y-m-d 23:59:59'),
    ),
  );

  $stmtCards = $pdo->prepare(
    'SELECT
      COALESCE(SUM(CASE WHEN status IN (\'livre\', \'livree\') AND ' . $orderRevenueDateExpr . ' BETWEEN :today_from AND :today_to THEN ' . $orderRevenueTotalExpr . ' ELSE 0 END), 0) AS revenue_today,
      COALESCE(SUM(CASE WHEN status IN (\'livre\', \'livree\') AND ' . $orderRevenueDateExpr . ' BETWEEN :week_from AND :week_to THEN ' . $orderRevenueTotalExpr . ' ELSE 0 END), 0) AS revenue_week,
      COALESCE(SUM(CASE WHEN status IN (\'livre\', \'livree\') AND ' . $orderRevenueDateExpr . ' BETWEEN :month_from AND :month_to THEN ' . $orderRevenueTotalExpr . ' ELSE 0 END), 0) AS revenue_month,
      COALESCE(SUM(CASE WHEN status IN (\'livre\', \'livree\') AND ' . $orderRevenueDateExpr . ' BETWEEN :year_from AND :year_to THEN ' . $orderRevenueTotalExpr . ' ELSE 0 END), 0) AS revenue_year
    FROM orders'
  );
  $stmtCards->execute(array(
    'today_from' => $ranges['today']['from'],
    'today_to' => $ranges['today']['to'],
    'week_from' => $ranges['week']['from'],
    'week_to' => $ranges['week']['to'],
    'month_from' => $ranges['month']['from'],
    'month_to' => $ranges['month']['to'],
    'year_from' => $ranges['year']['from'],
    'year_to' => $ranges['year']['to'],
  ));
  $cardRow = $stmtCards->fetch(PDO::FETCH_ASSOC) ?: array();

  $stmtSelected = $pdo->prepare(
    'SELECT
      COALESCE(SUM(CASE WHEN status IN (:delivered_st1_a, :delivered_st2_a) THEN 1 ELSE 0 END), 0) AS delivered_count,
      COALESCE(SUM(CASE WHEN status IN (:cancelled_st1, :cancelled_st2, :cancelled_st3) THEN 1 ELSE 0 END), 0) AS cancelled_count,
      COALESCE(SUM(CASE WHEN status IN (:delivered_st1_b, :delivered_st2_b) THEN ' . $orderRevenueTotalExpr . ' ELSE 0 END), 0) AS selected_revenue
    FROM orders
    WHERE ' . $orderRevenueDateExpr . ' BETWEEN :from_dt AND :to_dt'
  );
  $stmtSelected->execute(array(
    'delivered_st1_a' => 'livre',
    'delivered_st2_a' => 'livree',
    'delivered_st1_b' => 'livre',
    'delivered_st2_b' => 'livree',
    'cancelled_st1' => 'annulee',
    'cancelled_st2' => 'annulée',
    'cancelled_st3' => 'cancelled',
    'from_dt' => $dateFrom . ' 00:00:00',
    'to_dt' => $dateTo . ' 23:59:59',
  ));
  $selectedRow = $stmtSelected->fetch(PDO::FETCH_ASSOC) ?: array();

  $selectedRevenue = (int) ($selectedRow['selected_revenue'] ?? 0);
  $deliveredCount = (int) ($selectedRow['delivered_count'] ?? 0);
  $cancelledCount = (int) ($selectedRow['cancelled_count'] ?? 0);
  $periodOrderBase = $deliveredCount + $cancelledCount;

  $stmtOrders = $pdo->prepare(
    'SELECT id, order_number, customer_name, customer_phone, status, ' . $orderRevenueTotalExpr . ' AS total_amount, ' . $orderRevenueDateExpr . ' AS created_at
    FROM orders
    WHERE status IN (:st1, :st2)
      AND ' . $orderRevenueDateExpr . ' BETWEEN :from_dt AND :to_dt
    ORDER BY ' . $orderRevenueDateExpr . ' DESC, id DESC'
  );
  $stmtOrders->execute(array(
    'st1' => 'livre',
    'st2' => 'livree',
    'from_dt' => $dateFrom . ' 00:00:00',
    'to_dt' => $dateTo . ' 23:59:59',
  ));
  $orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC) ?: array();

  $stmtTopProducts = $pdo->prepare(
    'SELECT
      COALESCE(NULLIF(TRIM(' . $itemNameCol . '), \'\'), p.name, CONCAT(\'#\', oi.product_id)) AS product_name,
      COALESCE(SUM(' . $itemQtyCol . '), 0) AS qty_sold,
      COALESCE(SUM(
        CASE
          WHEN ' . $itemLineCol . ' > 0 THEN ' . $itemLineCol . '
          WHEN ' . $itemUnitCol . ' > 0 AND ' . $itemQtyCol . ' > 0 THEN ' . $itemUnitCol . ' * ' . $itemQtyCol . '
          ELSE 0
        END
      ), 0) AS revenue_generated
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE o.status IN (:top_st1, :top_st2)
      AND ' . $orderRevenueDateExprOrder . ' BETWEEN :top_from_dt AND :top_to_dt
    GROUP BY COALESCE(NULLIF(TRIM(' . $itemNameCol . '), \'\'), p.name, CONCAT(\'#\', oi.product_id))
    ORDER BY qty_sold DESC, revenue_generated DESC, product_name ASC
    LIMIT 5'
  );
  $stmtTopProducts->execute(array(
    'top_st1' => 'livre',
    'top_st2' => 'livree',
    'top_from_dt' => $dateFrom . ' 00:00:00',
    'top_to_dt' => $dateTo . ' 23:59:59',
  ));
  $topProducts = $stmtTopProducts->fetchAll(PDO::FETCH_ASSOC) ?: array();

  $trendStart = $today->modify('first day of -11 months')->format('Y-m-01 00:00:00');
  $trendEnd = $today->modify('last day of this month')->format('Y-m-d 23:59:59');
  $stmtTrend = $pdo->prepare(
    'SELECT
      DATE_FORMAT(' . $orderRevenueDateExpr . ', :trend_fmt_a) AS ym,
      COALESCE(SUM(' . $orderRevenueTotalExpr . '), 0) AS revenue_total,
      COUNT(*) AS orders_count
    FROM orders
    WHERE status IN (:trend_st1, :trend_st2)
      AND ' . $orderRevenueDateExpr . ' BETWEEN :trend_from_dt AND :trend_to_dt
    GROUP BY DATE_FORMAT(' . $orderRevenueDateExpr . ', :trend_fmt_b)
    ORDER BY ym ASC'
  );
  $stmtTrend->execute(array(
    'trend_fmt_a' => '%Y-%m',
    'trend_st1' => 'livre',
    'trend_st2' => 'livree',
    'trend_from_dt' => $trendStart,
    'trend_to_dt' => $trendEnd,
    'trend_fmt_b' => '%Y-%m',
  ));
  $trendRows = $stmtTrend->fetchAll(PDO::FETCH_ASSOC) ?: array();

  $trendMap = array();
  foreach ($trendRows as $trendRow) {
    $ym = (string) ($trendRow['ym'] ?? '');
    if ($ym === '') continue;
    $trendMap[$ym] = array(
      'ym' => $ym,
      'label' => admin_revenue_month_label($ym),
      'revenue_total' => (int) ($trendRow['revenue_total'] ?? 0),
      'orders_count' => (int) ($trendRow['orders_count'] ?? 0),
    );
  }

  for ($i = 11; $i >= 0; $i--) {
    $dt = $today->modify('first day of -' . $i . ' months');
    $ym = $dt->format('Y-m');
    $monthlyTrend[] = $trendMap[$ym] ?? array(
      'ym' => $ym,
      'label' => admin_revenue_month_label($ym),
      'revenue_total' => 0,
      'orders_count' => 0,
    );
  }

  $kpi['revenue_today'] = (int) ($cardRow['revenue_today'] ?? 0);
  $kpi['revenue_week'] = (int) ($cardRow['revenue_week'] ?? 0);
  $kpi['revenue_month'] = (int) ($cardRow['revenue_month'] ?? 0);
  $kpi['revenue_year'] = (int) ($cardRow['revenue_year'] ?? 0);
  $kpi['delivered_count'] = $deliveredCount;
  $kpi['cancelled_count'] = $cancelledCount;
  $kpi['delivery_rate'] = $periodOrderBase > 0 ? (int) round(($deliveredCount / $periodOrderBase) * 100) : 0;
  $kpi['cancellation_rate'] = $periodOrderBase > 0 ? (int) round(($cancelledCount / $periodOrderBase) * 100) : 0;
  $kpi['avg_basket'] = $deliveredCount > 0 ? (int) round($selectedRevenue / $deliveredCount) : 0;
  $kpi['selected_revenue'] = $selectedRevenue;
} catch (Throwable $e) {
  $db_error = 'Impossible de charger le chiffre d\'affaires (base de données).';
}

}

require_once __DIR__ . '/../_layout_header.php';
?>

<link rel="stylesheet" href="<?php echo e(base_url('assets/css/pages/admin-revenue.css')); ?>">

<main id="main">
  <section>
    <div class="container">
      <div class="admin-revenue-page">
        <?php if ($db_error): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded" role="alert">
            <strong><?php echo e($db_error); ?></strong>
          </div>
        <?php else: ?>
          <div class="admin-revenue-shell">
            <?php if ($filterError !== ''): ?>
              <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-revenue-reveal" role="alert">
                <strong><?php echo e($filterError); ?></strong>
              </div>
            <?php endif; ?>
            <div class="admin-page-header admin-revenue-header admin-revenue-reveal">
              <div class="admin-page-header__content">
                <p class="admin-page-header__eyebrow">Chiffre d'affaires</p>
                <h1 class="admin-page-header__title">Chiffre d'affaires</h1>
                <p class="admin-page-header__subtitle">Vue admin compacte pour piloter les revenus, les commandes livrées et la performance produit sans quitter le tableau de bord.</p>
                <p class="admin-revenue-mobile-subtitle">Suivi des revenus, commandes et produits sur une seule vue.</p>
                <div class="admin-revenue-header__meta">
                  <span class="admin-status-pill admin-status-pill--neutral">
                    <i class="fas fa-calendar-range" aria-hidden="true"></i>
                    <span>Période active <strong><?php echo e($dateFrom); ?> au <?php echo e($dateTo); ?></strong></span>
                  </span>
                  <span class="admin-status-pill admin-status-pill--success">
                    <i class="fas fa-box-open" aria-hidden="true"></i>
                    <span><strong><?php echo e((string) count($orders)); ?></strong> commande(s) livrée(s) retenue(s)</span>
                  </span>
                </div>
              </div>

              <div class="admin-page-header__actions admin-revenue-header__actions">
                <a class="admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">
                  <i class="fas fa-gauge-high" aria-hidden="true"></i> Tableau de bord
                </a>
                <a class="admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/orders/index.php')); ?>">
                  <i class="fas fa-receipt" aria-hidden="true"></i> Commandes
                </a>
              </div>
            </div>

            <div class="admin-toolbar admin-revenue-toolbar admin-revenue-reveal admin-panel admin-panel--padded" aria-label="Filtres chiffre d'affaires">
              <form method="get" action="" class="admin-toolbar__search" role="search">
                <div class="admin-revenue-filter__field">
                  <label class="admin-revenue-filter__label" for="preset">Période rapide</label>
                  <select id="preset" name="preset" class="admin-select">
                    <option value="today" <?php echo $preset === 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                    <option value="week" <?php echo $preset === 'week' ? 'selected' : ''; ?>>Cette semaine</option>
                    <option value="month" <?php echo $preset === 'month' ? 'selected' : ''; ?>>Ce mois</option>
                    <option value="year" <?php echo $preset === 'year' ? 'selected' : ''; ?>>Cette année</option>
                    <option value="custom" <?php echo $preset === 'custom' ? 'selected' : ''; ?>>Personnalisée</option>
                  </select>
                </div>

                <div class="admin-revenue-filter__field">
                  <label class="admin-revenue-filter__label" for="date_from">Date début</label>
                  <input id="date_from" name="date_from" type="date" class="admin-field" value="<?php echo e($dateFrom); ?>" max="<?php echo e($dateTo); ?>">
                </div>

                <div class="admin-revenue-filter__field">
                  <label class="admin-revenue-filter__label" for="date_to">Date fin</label>
                  <input id="date_to" name="date_to" type="date" class="admin-field" value="<?php echo e($dateTo); ?>" min="<?php echo e($dateFrom); ?>">
                </div>

                <div class="admin-revenue-toolbar__actions">
                  <button class="admin-btn admin-btn--primary" type="submit">
                    <i class="fas fa-search" aria-hidden="true"></i> Filtrer
                  </button>
                  <a class="admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/revenue/index.php')); ?>">Réinitialiser</a>
                </div>
              </form>

              <div class="admin-revenue-toolbar__meta">
                <span class="admin-revenue-toolbar__pill">
                  <i class="fas fa-filter" aria-hidden="true"></i>
                  Filtres actifs et application immédiate
                </span>
                <span class="admin-revenue-toolbar__pill">
                  <i class="fas fa-circle-check" aria-hidden="true"></i>
                  <?php echo e((string) count($orders)); ?> commande(s) livrée(s) retenue(s)
                </span>
              </div>
            </div>

            <div class="admin-revenue-kpis" aria-label="Indicateurs chiffre d'affaires">
              <div class="admin-panel admin-revenue-kpi admin-revenue-reveal">
                <div class="admin-revenue-kpi__head">
                  <div>
                    <p class="admin-revenue-kpi__title">Chiffre d'affaires aujourd'hui</p>
                    <strong data-countup-value="<?php echo e((string) ((int) $kpi['revenue_today'])); ?>" data-countup-type="money"><?php echo e(admin_revenue_money((int) $kpi['revenue_today'])); ?></strong>
                  </div>
                  <span class="admin-revenue-kpi__icon" aria-hidden="true"><i class="fas fa-sun"></i></span>
                </div>
                <div class="admin-revenue-kpi__foot">
                  <div class="admin-help">Performance de la journee en cours</div>
                  <span class="admin-revenue-kpi__trend">Temps reel</span>
                </div>
              </div>
              <div class="admin-panel admin-revenue-kpi admin-revenue-reveal">
                <div class="admin-revenue-kpi__head">
                  <div>
                    <p class="admin-revenue-kpi__title">Chiffre d'affaires cette semaine</p>
                    <strong data-countup-value="<?php echo e((string) ((int) $kpi['revenue_week'])); ?>" data-countup-type="money"><?php echo e(admin_revenue_money((int) $kpi['revenue_week'])); ?></strong>
                  </div>
                  <span class="admin-revenue-kpi__icon" aria-hidden="true"><i class="fas fa-calendar-week"></i></span>
                </div>
                <div class="admin-revenue-kpi__foot">
                  <div class="admin-help">Vue sur la semaine en cours</div>
                  <span class="admin-revenue-kpi__trend">Hebdo</span>
                </div>
              </div>
              <div class="admin-panel admin-revenue-kpi admin-revenue-reveal">
                <div class="admin-revenue-kpi__head">
                  <div>
                    <p class="admin-revenue-kpi__title">Chiffre d'affaires ce mois</p>
                    <strong data-countup-value="<?php echo e((string) ((int) $kpi['revenue_month'])); ?>" data-countup-type="money"><?php echo e(admin_revenue_money((int) $kpi['revenue_month'])); ?></strong>
                  </div>
                  <span class="admin-revenue-kpi__icon" aria-hidden="true"><i class="fas fa-calendar-days"></i></span>
                </div>
                <div class="admin-revenue-kpi__foot">
                  <div class="admin-help">Vision compacte de la periode mensuelle</div>
                  <span class="admin-revenue-kpi__trend">Mensuel</span>
                </div>
              </div>
              <div class="admin-panel admin-revenue-kpi admin-revenue-reveal">
                <div class="admin-revenue-kpi__head">
                  <div>
                    <p class="admin-revenue-kpi__title">Chiffre d'affaires cette année</p>
                    <strong data-countup-value="<?php echo e((string) ((int) $kpi['revenue_year'])); ?>" data-countup-type="money"><?php echo e(admin_revenue_money((int) $kpi['revenue_year'])); ?></strong>
                  </div>
                  <span class="admin-revenue-kpi__icon admin-revenue-kpi__icon--accent" aria-hidden="true"><i class="fas fa-trophy"></i></span>
                </div>
                <div class="admin-revenue-kpi__foot">
                  <div class="admin-help">Cumul annuel des ventes livrees</div>
                  <span class="admin-revenue-kpi__trend">YTD</span>
                </div>
              </div>
              <div class="admin-panel admin-revenue-kpi admin-revenue-reveal">
                <div class="admin-revenue-kpi__head">
                  <div>
                    <p class="admin-revenue-kpi__title">Commandes livrees sur la periode</p>
                    <strong data-countup-value="<?php echo e((string) ((int) $kpi['delivered_count'])); ?>" data-countup-type="number"><?php echo e((string) ((int) $kpi['delivered_count'])); ?></strong>
                  </div>
                  <span class="admin-revenue-kpi__icon" aria-hidden="true"><i class="fas fa-truck-fast"></i></span>
                </div>
                <div class="admin-revenue-kpi__foot">
                  <div class="admin-help">Filtre du <?php echo e($dateFrom); ?> au <?php echo e($dateTo); ?></div>
                  <span class="admin-revenue-kpi__trend"><?php echo e((string) ((int) $kpi['delivery_rate'])); ?>% livrées</span>
                </div>
              </div>
              <div class="admin-panel admin-revenue-kpi admin-revenue-reveal">
                <div class="admin-revenue-kpi__head">
                  <div>
                    <p class="admin-revenue-kpi__title">Panier moyen</p>
                    <strong data-countup-value="<?php echo e((string) ((int) $kpi['avg_basket'])); ?>" data-countup-type="money"><?php echo e(admin_revenue_money((int) $kpi['avg_basket'])); ?></strong>
                  </div>
                  <span class="admin-revenue-kpi__icon admin-revenue-kpi__icon--accent" aria-hidden="true"><i class="fas fa-basket-shopping"></i></span>
                </div>
                <div class="admin-revenue-kpi__foot">
                  <div class="admin-help">Base : <?php echo e((string) ((int) $kpi['delivered_count'])); ?> commande(s) livrée(s)</div>
                  <span class="admin-revenue-kpi__trend">Moyenne</span>
                </div>
              </div>
            </div>

            <div class="admin-panel admin-revenue-summary admin-revenue-reveal" aria-label="Résumé de la période">
              <div class="admin-revenue-summary__value">
                <span class="admin-revenue-summary__icon" aria-hidden="true"><i class="fas fa-wallet"></i></span>
                <div>
                  <div class="admin-help">CA filtré sur la période sélectionnée</div>
                  <strong data-countup-value="<?php echo e((string) ((int) $kpi['selected_revenue'])); ?>" data-countup-type="money"><?php echo e(admin_revenue_money((int) $kpi['selected_revenue'])); ?></strong>
                </div>
              </div>
              <div class="admin-revenue-summary__period">
                <strong>Résumé</strong>
                <div class="admin-help admin-revenue-summary__help">Période sélectionnée : du <?php echo e($dateFrom); ?> au <?php echo e($dateTo); ?></div>
              </div>
            </div>

            <section class="admin-revenue-section admin-revenue-reveal" aria-label="Évolution du chiffre d'affaires">
            <h2>Évolution du chiffre d'affaires (12 derniers mois)</h2>
            <p class="admin-revenue-section__intro">Vue glissante des ventes livrées pour suivre la dynamique mensuelle.</p>
            <?php
              $hasTrendData = false;
              $trendChartLabels = array();
              $trendChartRevenue = array();
              $trendChartOrders = array();
              foreach ($monthlyTrend as $trendItem) {
                $trendRevenue = (int) ($trendItem['revenue_total'] ?? 0);
                $trendChartLabels[] = (string) ($trendItem['label'] ?? '');
                $trendChartRevenue[] = $trendRevenue;
                $trendChartOrders[] = (int) ($trendItem['orders_count'] ?? 0);
                if ($trendRevenue > 0 || (int) ($trendItem['orders_count'] ?? 0) > 0) {
                  $hasTrendData = true;
                }
              }
            ?>
            <?php if (!$hasTrendData): ?>
              <div class="admin-panel admin-revenue-panel admin-revenue-empty-panel admin-empty-state">
                <p class="admin-empty-state__title">Aucune donnée sur 12 mois</p>
                <p class="admin-empty-state__text">Aucune commande livrée n'a été trouvée sur les 12 derniers mois.</p>
              </div>
            <?php else: ?>
              <div class="admin-panel admin-revenue-panel admin-revenue-trend">
                <div class="admin-revenue-chart-card">
                  <canvas id="revenueChart" aria-label="Graphique d'évolution du chiffre d'affaires sur 12 mois" role="img"></canvas>
                </div>

                <details class="admin-revenue-details">
                  <summary>Voir le détail mensuel</summary>
                  <div class="admin-revenue-details__body admin-table-wrap admin-revenue-table-wrap" aria-label="Résumé mensuel du chiffre d'affaires">
                    <div class="admin-table-shell admin-revenue-table-shell">
                    <table class="admin-table">
                      <thead>
                        <tr>
                          <th>Mois</th>
                          <th>CA</th>
                          <th>Nb commandes</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($monthlyTrend as $trendItem): ?>
                          <?php
                            $trendLabel = (string) ($trendItem['label'] ?? '');
                            $trendRevenue = (int) ($trendItem['revenue_total'] ?? 0);
                            $trendOrdersCount = (int) ($trendItem['orders_count'] ?? 0);
                          ?>
                          <tr>
                            <td data-label="Mois"><strong><?php echo e($trendLabel); ?></strong></td>
                            <td data-label="CA" class="admin-revenue-table__total"><?php echo e(admin_revenue_money($trendRevenue)); ?></td>
                            <td data-label="Nb commandes"><?php echo e((string) $trendOrdersCount); ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                    </div>
                  </div>
                </details>
              </div>
            <?php endif; ?>
            </section>

            <section class="admin-revenue-section admin-revenue-reveal" aria-label="Analyse des ventes">
            <h2>Analyse des ventes</h2>
            <p class="admin-revenue-section__intro">Lecture rapide des produits qui performent et de la qualité du flux de commandes.</p>

            <div class="admin-revenue-analysis">
              <div class="admin-panel admin-revenue-panel" aria-label="Top produits vendus">
                <h2>Top produits vendus</h2>
                <p class="admin-help">Bloc basé sur les montants bruts des lignes produits avant réduction de commande.</p>
                <?php if (!$topProducts): ?>
                  <div class="admin-empty-state admin-revenue-empty-panel">
                    <p class="admin-empty-state__title">Aucune donnée pour cette période.</p>
                    <p class="admin-empty-state__text">Aucun produit vendu n'a été trouvé sur les commandes livrées filtrées.</p>
                  </div>
                <?php else: ?>
                  <div class="admin-table-shell admin-revenue-table-shell admin-revenue-table-shell--mobile-cards">
                  <table class="admin-table admin-revenue-products">
                    <thead>
                      <tr>
                        <th>Produit</th>
                        <th>Quantité vendue</th>
                        <th>CA généré</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($topProducts as $product): ?>
                        <?php
                          $productName = (string) ($product['product_name'] ?? '');
                          $qtySold = (int) ($product['qty_sold'] ?? 0);
                          $revenueGenerated = (int) ($product['revenue_generated'] ?? 0);
                        ?>
                        <tr>
                          <td data-label="Produit"><strong><?php echo e($productName !== '' ? $productName : 'Produit'); ?></strong></td>
                          <td data-label="Quantité"><?php echo e((string) $qtySold); ?></td>
                          <td data-label="CA généré" class="admin-revenue-table__total"><?php echo e(admin_revenue_money($revenueGenerated)); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                  </div>
                  <div class="admin-help">Le montant "CA généré" additionne les lignes produits brutes et ne déduit pas la réduction appliquée au niveau de la commande.</div>
                <?php endif; ?>
              </div>

              <div class="admin-revenue-stack">
                <div class="admin-panel admin-revenue-panel">
                  <h2>Commandes livrées vs annulées</h2>
                  <div class="admin-revenue-mini-grid" aria-label="Commandes livrées vs annulées">
                    <div class="admin-revenue-mini-card">
                      <div class="admin-help">Commandes livrées</div>
                      <strong><?php echo e((string) ((int) $kpi['delivered_count'])); ?></strong>
                    </div>
                    <div class="admin-revenue-mini-card">
                      <div class="admin-help">Commandes annulées</div>
                      <strong><?php echo e((string) ((int) $kpi['cancelled_count'])); ?></strong>
                    </div>
                    <div class="admin-revenue-mini-card">
                      <div class="admin-help">Taux de livraison</div>
                      <strong data-countup-value="<?php echo e((string) ((int) $kpi['delivery_rate'])); ?>" data-countup-type="percent"><?php echo e((string) ((int) $kpi['delivery_rate'])); ?>%</strong>
                      <div class="admin-help">Base : livrées + annulées</div>
                    </div>
                    <div class="admin-revenue-mini-card">
                      <div class="admin-help">Taux d'annulation</div>
                      <strong data-countup-value="<?php echo e((string) ((int) $kpi['cancellation_rate'])); ?>" data-countup-type="percent"><?php echo e((string) ((int) $kpi['cancellation_rate'])); ?>%</strong>
                      <div class="admin-help">Base : livrées + annulées</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            </section>

            <section class="admin-revenue-section admin-revenue-reveal" aria-label="Commandes prises en compte">
            <h2>Commandes prises en compte</h2>
            <p class="admin-revenue-muted">Ces commandes sont les commandes livrées utilisées pour calculer le chiffre d'affaires de la période sélectionnée.</p>
            <div class="admin-panel admin-revenue-panel">
              <div class="admin-table-shell admin-revenue-table-shell admin-revenue-table-shell--mobile-cards admin-table-wrap admin-revenue-table-wrap">
              <table class="admin-table admin-revenue-orders-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Numéro de commande</th>
                    <th>Client</th>
                    <th>Téléphone</th>
                    <th>Statut</th>
                    <th>Total</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$orders): ?>
                    <tr>
                      <td colspan="7" class="admin-empty-row">
                        <div class="admin-empty-state admin-revenue-empty-panel">
                          <p class="admin-empty-state__title">Aucune donnée pour cette période.</p>
                          <p class="admin-empty-state__text">Ajustez les dates ou utilisez un autre preset pour afficher des ventes.</p>
                          <div class="admin-empty-state__actions">
                            <a class="admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/revenue/index.php')); ?>">Réinitialiser les filtres</a>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>

                  <?php foreach ($orders as $order): ?>
                    <?php
                      $id = (int) ($order['id'] ?? 0);
                      $createdAt = (string) ($order['created_at'] ?? '');
                      $orderNumber = (string) ($order['order_number'] ?? '');
                      $customerName = (string) ($order['customer_name'] ?? '');
                      $customerPhone = (string) ($order['customer_phone'] ?? '');
                      $status = (string) ($order['status'] ?? '');
                      $totalAmount = (int) ($order['total_amount'] ?? 0);
                    ?>
                    <tr>
                      <td data-label="Date" class="admin-revenue-table__date"><?php echo e($createdAt); ?></td>
                      <td data-label="Numéro"><strong><?php echo e($orderNumber); ?></strong></td>
                      <td data-label="Client"><?php echo e($customerName); ?></td>
                      <td data-label="Téléphone"><?php echo e($customerPhone); ?></td>
                      <td data-label="Statut"><span class="admin-status-pill admin-status-pill--success admin-revenue-status-chip"><?php echo e(admin_revenue_status_label($status)); ?></span></td>
                      <td data-label="Total" class="admin-revenue-table__total"><?php echo e(admin_revenue_money($totalAmount)); ?></td>
                      <td data-label="Action">
                        <a class="admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/orders/show.php?id=' . $id)); ?>">Voir</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              </div>
            </div>
            </section>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php if (!$db_error && $hasTrendData): ?>
  <script src="<?php echo e(base_url('assets/js/chart.umd.min.js')); ?>"></script>
  <script>
    window.adminRevenueConfig = {
      chart: {
        labels: <?php echo json_encode($trendChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        revenue: <?php echo json_encode($trendChartRevenue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        orders: <?php echo json_encode($trendChartOrders, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
      }
    };
  </script>
<?php else: ?>
  <script>
    window.adminRevenueConfig = { chart: null };
  </script>
<?php endif; ?>
<script src="<?php echo e(base_url('assets/js/pages/admin-revenue.js')); ?>"></script>

<?php require_once __DIR__ . '/../_layout_footer.php'; ?>
