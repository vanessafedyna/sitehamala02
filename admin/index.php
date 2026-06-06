<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_once __DIR__ . '/_auth.php';
$user = current_admin_user();
$adminRole = function_exists('admin_current_role') ? admin_current_role() : '';
$isOwner = ($adminRole === 'owner');

$page_title = 'Admin - Overview';
$page_css = 'pages/admin-products.css';
$page_js = '';
require_once __DIR__ . '/_layout_header.php';

require_once __DIR__ . '/AdminStats.php';
require_once __DIR__ . '/../app/services/InventorySnapshotService.php';

$kpi = array();
$db_error = '';
$todo_orders = array();
$todo_low_stock = array();
$todo_pending_reviews = array();
$todo_pending_product_reviews = array();
$todo_pending_products = array();
$partner_recent_products = array();
$priority_counts = array(
  'orders_confirm' => 0,
  'orders_payment_pending' => 0,
  'products_draft' => 0,
  'products_low_stock' => 0,
);

try {
  $pdo = db();
  $inventory = new InventorySnapshotService($pdo);

  if ($isOwner) {
    $orderCols = function_exists('db_table_columns') ? db_table_columns($pdo, 'orders') : array();
    $productCols = function_exists('db_table_columns') ? db_table_columns($pdo, 'products') : array();
    $stockSelect = in_array('low_stock_threshold', $productCols, true)
      ? 'SELECT id, name, stock, low_stock_threshold FROM products ORDER BY id DESC'
      : 'SELECT id, name, stock FROM products ORDER BY id DESC';

    if (in_array('status', $orderCols, true)) {
      $priority_counts['orders_confirm'] = (int) (($pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'nouveau'")->fetchColumn()) ?: 0);
    }
    if (in_array('payment_status', $orderCols, true)) {
      $priority_counts['orders_payment_pending'] = (int) (($pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'pending'")->fetchColumn()) ?: 0);
    }
    if (in_array('status', $productCols, true)) {
      $priority_counts['products_draft'] = (int) (($pdo->query("SELECT COUNT(*) FROM products WHERE status = 'draft'")->fetchColumn()) ?: 0);
    }
    if (in_array('stock', $productCols, true)) {
      $stockProducts = $inventory->hydrateProductRows($pdo->query($stockSelect)->fetchAll(PDO::FETCH_ASSOC) ?: array());
      foreach ($stockProducts as $stockProduct) {
        if ((int) ($stockProduct['is_low_stock_effective'] ?? 0) === 1 || (int) ($stockProduct['is_out_of_stock_effective'] ?? 0) === 1) {
          $priority_counts['products_low_stock']++;
        }
      }
    }

    $kpi = AdminStats::overviewMonthly($pdo);

    $statuses = array('nouveau', 'confirme', 'en_preparation', 'en_livraison');
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare(
      "SELECT id, order_number, customer_name, total_amount, status, created_at
       FROM orders
       WHERE status IN ($in)
       ORDER BY created_at DESC
       LIMIT 5"
    );
    $stmt->execute($statuses);
    $todo_orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

    $todoLowStockRows = $inventory->hydrateProductRows($pdo->query($stockSelect)->fetchAll(PDO::FETCH_ASSOC) ?: array());
    $todoLowStockRows = array_values(array_filter($todoLowStockRows, static function (array $row): bool {
      return (int) ($row['is_low_stock_effective'] ?? 0) === 1 || (int) ($row['is_out_of_stock_effective'] ?? 0) === 1;
    }));
    usort($todoLowStockRows, static function (array $a, array $b): int {
      $stockA = (int) ($a['effective_stock'] ?? ($a['stock'] ?? 0));
      $stockB = (int) ($b['effective_stock'] ?? ($b['stock'] ?? 0));
      if ($stockA !== $stockB) {
        return $stockA <=> $stockB;
      }

      return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
    });
    $todo_low_stock = array_slice($todoLowStockRows, 0, 5);

    try {
      $hasApproved = function_exists('db_has_column')
        ? db_has_column($pdo, 'reviews', 'is_approved')
        : false;
      if ($hasApproved) {
        $stmt = $pdo->prepare(
          'SELECT id, name, rating, created_at FROM reviews WHERE is_approved = 0 ORDER BY created_at DESC LIMIT 5'
        );
        $stmt->execute();
        $todo_pending_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
      }
    } catch (Throwable $e) {
      $todo_pending_reviews = array();
    }

    try {
      $hasApproved = function_exists('db_has_column')
        ? db_has_column($pdo, 'product_reviews', 'is_approved')
        : false;
      if ($hasApproved) {
        $stmt = $pdo->prepare(
          'SELECT pr.id, pr.customer_name, pr.rating, pr.product_id, COALESCE(p.name, CONCAT("#", pr.product_id)) AS product_name
           FROM product_reviews pr
           LEFT JOIN products p ON p.id = pr.product_id
           WHERE pr.is_approved = 0
           ORDER BY pr.created_at DESC
           LIMIT 5'
        );
        $stmt->execute();
        $todo_pending_product_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
      }
    } catch (Throwable $e) {
      $todo_pending_product_reviews = array();
    }

    try {
      $hasStatus = function_exists('db_has_column')
        ? db_has_column($pdo, 'products', 'status')
        : false;
      if ($hasStatus) {
        $stmt = $pdo->prepare(
          "SELECT id, name, created_at FROM products WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5"
        );
        $stmt->execute();
        $todo_pending_products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
      }
    } catch (Throwable $e) {
      $todo_pending_products = array();
    }
  } else {
    $statuses = array('nouveau', 'confirme', 'en_preparation', 'en_livraison');
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare(
      "SELECT id, order_number, customer_name, total_amount, status, created_at
       FROM orders
       WHERE status IN ($in)
       ORDER BY created_at DESC
       LIMIT 5"
    );
    $stmt->execute($statuses);
    $todo_orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

    $stmt = $pdo->prepare('SELECT id, name, created_at FROM products ORDER BY id DESC LIMIT 5');
    $stmt->execute();
    $partner_recent_products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
  }
} catch (Throwable $e) {
  $db_error = 'Impossible de charger les donnees (base de donnees).';
}

$todo_card_state = static function (int $count): array {
  if ($count <= 0) {
    return array('class' => 'todo-card--quiet', 'note' => 'Aucune action immediate', 'badge' => '');
  }
  if ($count >= 5) {
    return array('class' => 'todo-card--high', 'note' => 'Action recommandee', 'badge' => 'Urgent');
  }
  return array('class' => 'todo-card--watch', 'note' => 'A surveiller', 'badge' => 'Attention');
};

$todo_is_empty = (
  ((int) ($priority_counts['orders_confirm'] ?? 0) === 0)
  && ((int) ($priority_counts['orders_payment_pending'] ?? 0) === 0)
  && ((int) ($priority_counts['products_low_stock'] ?? 0) === 0)
  && ((int) ($priority_counts['products_draft'] ?? 0) === 0)
);

$displayName = trim((string) ($user['name'] ?? ''));
$greetingName = $displayName !== '' ? $displayName : 'Admin';
$ownerRevenue = (int) ($kpi['ca_month'] ?? 0);
$ownerBasket = (int) ($kpi['avg_basket_month'] ?? 0);
$ownerReviewsPending = (int) ($kpi['reviews_pending'] ?? 0);
$ownerConfirmCount = (int) ($priority_counts['orders_confirm'] ?? 0);
$ownerPaymentCount = (int) ($priority_counts['orders_payment_pending'] ?? 0);
$ownerLowStockCount = (int) ($priority_counts['products_low_stock'] ?? 0);
$ownerDraftCount = (int) ($priority_counts['products_draft'] ?? 0);
?>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var revealNodes = document.querySelectorAll('.admin-home-reveal');
    if (revealNodes.length) {
      if ('IntersectionObserver' in window) {
        var revealObserver = new IntersectionObserver(function (entries, observer) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              entry.target.classList.add('is-visible');
              observer.unobserve(entry.target);
            }
          });
        }, { threshold: 0.12 });

        revealNodes.forEach(function (node) {
          revealObserver.observe(node);
        });
      } else {
        revealNodes.forEach(function (node) {
          node.classList.add('is-visible');
        });
      }
    }

    var statNodes = document.querySelectorAll('[data-countup]');
    statNodes.forEach(function (node) {
      var finalValue = Number(node.getAttribute('data-countup') || '0');
      if (!Number.isFinite(finalValue)) return;
      var duration = 700;
      var start = null;

      function step(timestamp) {
        if (start === null) start = timestamp;
        var progress = Math.min((timestamp - start) / duration, 1);
        var current = Math.round(finalValue * progress);
        node.textContent = current.toLocaleString('fr-FR');
        if (progress < 1) {
          window.requestAnimationFrame(step);
        }
      }

      window.requestAnimationFrame(step);
    });
  });
</script>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-home-page">
        <div class="admin-page-header admin-panel admin-panel--padded admin-home-hero admin-home-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow"><?php echo e($isOwner ? 'Pilotage global' : 'Espace partenaire'); ?></p>
            <h1 class="admin-page-header__title">Bonjour <?php echo e($greetingName); ?></h1>
            <p class="admin-page-header__subtitle">
              <?php if ($isOwner): ?>
                Vue d'ensemble des priorites, indicateurs et files de travail du back-office dans une presentation plus claire et plus premium.
              <?php else: ?>
                Retrouvez vos commandes a traiter et les derniers produits depuis une vue plus nette, plus simple et plus cohérente avec tout l'admin.
              <?php endif; ?>
            </p>
            <div class="admin-home-meta" aria-label="Resume dashboard">
              <?php if ($isOwner): ?>
                <span class="admin-home-meta__chip"><strong><?php echo e((string) $ownerConfirmCount); ?></strong> commandes a traiter</span>
                <span class="admin-home-meta__chip"><strong><?php echo e((string) $ownerLowStockCount); ?></strong> produits en stock faible</span>
                <span class="admin-home-meta__chip"><strong><?php echo e((string) $ownerReviewsPending); ?></strong> avis en attente</span>
              <?php else: ?>
                <span class="admin-home-meta__chip"><strong><?php echo e((string) count($todo_orders)); ?></strong> commandes dans la liste</span>
                <span class="admin-home-meta__chip"><strong><?php echo e((string) count($partner_recent_products)); ?></strong> produits recents</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="admin-page-header__actions">
            <?php if ($isOwner): ?>
              <a class="btn admin-btn admin-btn--primary" href="<?php echo e(base_url('admin/orders/index.php')); ?>">
                <i class="fas fa-list-check" aria-hidden="true"></i> Ouvrir les commandes
              </a>
            <?php else: ?>
              <a class="btn admin-btn admin-btn--primary" href="<?php echo e(base_url('admin/products/index.php')); ?>">
                <i class="fas fa-box-open" aria-hidden="true"></i> Ouvrir le catalogue
              </a>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($db_error): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-home-reveal is-visible" role="alert">
            <strong><?php echo e($db_error); ?></strong>
          </div>
        <?php endif; ?>

        <?php if ($isOwner): ?>
          <div class="admin-home-kpis admin-home-reveal" aria-label="Indicateurs principaux">
            <div class="admin-home-kpi">
              <div class="admin-home-kpi__label">CA du mois</div>
              <div class="admin-home-kpi__value"><?php echo e(number_format($ownerRevenue, 0, ',', ' ')); ?> FCFA</div>
              <div class="admin-home-kpi__hint">Base sur les commandes livrees</div>
            </div>
            <div class="admin-home-kpi">
              <div class="admin-home-kpi__label">Panier moyen</div>
              <div class="admin-home-kpi__value"><?php echo e(number_format($ownerBasket, 0, ',', ' ')); ?> FCFA</div>
              <div class="admin-home-kpi__hint">CA du mois rapporte au volume de commandes</div>
            </div>
            <div class="admin-home-kpi">
              <div class="admin-home-kpi__label">Avis en attente</div>
              <div class="admin-home-kpi__value" data-countup="<?php echo e((string) $ownerReviewsPending); ?>"><?php echo e((string) $ownerReviewsPending); ?></div>
              <div class="admin-home-kpi__hint">Moderation en cours sur les retours clients</div>
            </div>
          </div>

          <?php if ($todo_is_empty): ?>
            <div class="admin-panel admin-panel--padded admin-home-empty admin-home-reveal" aria-label="Etat global">
              <h2 class="admin-home-empty__title">Tout est a jour</h2>
              <p class="admin-home-empty__text">Aucune action urgente n'a ete detectee pour le moment.</p>
            </div>
          <?php endif; ?>

          <div class="admin-home-priority-grid admin-home-reveal" aria-label="Priorites du jour">
            <?php $stateOrdersConfirm = $todo_card_state($ownerConfirmCount); ?>
            <div class="admin-home-priority-card <?php echo e((string) ($stateOrdersConfirm['class'] ?? '')); ?>">
              <div class="admin-home-priority-card__top">
                <div>
                  <p class="admin-home-priority-card__eyebrow">Priorite</p>
                  <h2 class="admin-home-priority-card__title">Commandes a traiter</h2>
                </div>
                <?php if (($stateOrdersConfirm['badge'] ?? '') !== ''): ?>
                  <span class="admin-status-pill admin-status-pill--danger admin-home-priority-card__badge"><?php echo e((string) ($stateOrdersConfirm['badge'] ?? '')); ?></span>
                <?php endif; ?>
              </div>
              <div class="admin-home-priority-card__value" data-countup="<?php echo e((string) $ownerConfirmCount); ?>"><?php echo e((string) $ownerConfirmCount); ?></div>
              <p class="admin-home-priority-card__note"><?php echo e((string) ($stateOrdersConfirm['note'] ?? '')); ?></p>
              <a class="btn admin-btn admin-btn--primary admin-home-priority-card__action" href="<?php echo e(base_url('admin/orders/index.php?status=nouveau')); ?>">Voir la file</a>
            </div>

            <?php $stateOrdersPayment = $todo_card_state($ownerPaymentCount); ?>
            <div class="admin-home-priority-card <?php echo e((string) ($stateOrdersPayment['class'] ?? '')); ?>">
              <div class="admin-home-priority-card__top">
                <div>
                  <p class="admin-home-priority-card__eyebrow">Paiements</p>
                  <h2 class="admin-home-priority-card__title">Commandes a encaisser</h2>
                </div>
                <?php if (($stateOrdersPayment['badge'] ?? '') !== ''): ?>
                  <span class="admin-status-pill admin-status-pill--warning admin-home-priority-card__badge"><?php echo e((string) ($stateOrdersPayment['badge'] ?? '')); ?></span>
                <?php endif; ?>
              </div>
              <div class="admin-home-priority-card__value" data-countup="<?php echo e((string) $ownerPaymentCount); ?>"><?php echo e((string) $ownerPaymentCount); ?></div>
              <p class="admin-home-priority-card__note"><?php echo e((string) ($stateOrdersPayment['note'] ?? '')); ?></p>
              <a class="btn admin-btn admin-btn--primary admin-home-priority-card__action" href="<?php echo e(base_url('admin/orders/index.php?payment_status=pending')); ?>">Voir la liste</a>
            </div>

            <?php $stateLowStock = $todo_card_state($ownerLowStockCount); ?>
            <div class="admin-home-priority-card <?php echo e((string) ($stateLowStock['class'] ?? '')); ?>">
              <div class="admin-home-priority-card__top">
                <div>
                  <p class="admin-home-priority-card__eyebrow">Catalogue</p>
                  <h2 class="admin-home-priority-card__title">Stock faible</h2>
                </div>
                <?php if (($stateLowStock['badge'] ?? '') !== ''): ?>
                  <span class="admin-status-pill admin-status-pill--warning admin-home-priority-card__badge"><?php echo e((string) ($stateLowStock['badge'] ?? '')); ?></span>
                <?php endif; ?>
              </div>
              <div class="admin-home-priority-card__value" data-countup="<?php echo e((string) $ownerLowStockCount); ?>"><?php echo e((string) $ownerLowStockCount); ?></div>
              <p class="admin-home-priority-card__note"><?php echo e((string) ($stateLowStock['note'] ?? '')); ?></p>
              <a class="btn admin-btn admin-btn--primary admin-home-priority-card__action" href="<?php echo e(base_url('admin/products/stock_index.php?filter=low_stock')); ?>">Voir le stock</a>
            </div>

            <?php $stateDraft = $todo_card_state($ownerDraftCount); ?>
            <div class="admin-home-priority-card <?php echo e((string) ($stateDraft['class'] ?? '')); ?>">
              <div class="admin-home-priority-card__top">
                <div>
                  <p class="admin-home-priority-card__eyebrow">Edition</p>
                  <h2 class="admin-home-priority-card__title">Brouillons</h2>
                </div>
                <?php if (($stateDraft['badge'] ?? '') !== ''): ?>
                  <span class="admin-status-pill admin-status-pill--info admin-home-priority-card__badge"><?php echo e((string) ($stateDraft['badge'] ?? '')); ?></span>
                <?php endif; ?>
              </div>
              <div class="admin-home-priority-card__value" data-countup="<?php echo e((string) $ownerDraftCount); ?>"><?php echo e((string) $ownerDraftCount); ?></div>
              <p class="admin-home-priority-card__note"><?php echo e((string) ($stateDraft['note'] ?? '')); ?></p>
              <a class="btn admin-btn admin-btn--primary admin-home-priority-card__action" href="<?php echo e(base_url('admin/products/index.php?status=draft')); ?>">Voir les brouillons</a>
            </div>
          </div>

          <div class="admin-home-sections admin-home-reveal">
            <div class="admin-home-stack">
              <div class="admin-panel admin-panel--padded admin-home-panel" aria-label="Raccourcis dashboard">
                <div class="admin-home-panel__header">
                  <div>
                    <p class="admin-home-panel__eyebrow">Raccourcis</p>
                    <h2 class="admin-home-panel__title">Acces rapides existants</h2>
                    <p class="admin-home-panel__text">Retrouvez les files prioritaires et les sections les plus actives du back-office.</p>
                  </div>
                </div>
                <div class="admin-home-actions">
                  <a class="admin-home-action-card" href="<?php echo e(base_url('admin/orders/index.php?status=nouveau')); ?>">
                    <h3 class="admin-home-action-card__title">Commandes a confirmer</h3>
                    <p class="admin-home-action-card__text"><?php echo e((string) $ownerConfirmCount); ?> element(s) dans la file prioritaire.</p>
                  </a>
                  <a class="admin-home-action-card" href="<?php echo e(base_url('admin/orders/index.php?payment_status=pending')); ?>">
                    <h3 class="admin-home-action-card__title">Commandes a encaisser</h3>
                    <p class="admin-home-action-card__text"><?php echo e((string) $ownerPaymentCount); ?> dossier(s) a verifier.</p>
                  </a>
                  <a class="admin-home-action-card" href="<?php echo e(base_url('admin/products/stock_index.php?filter=low_stock')); ?>">
                    <h3 class="admin-home-action-card__title">Surveillance stock</h3>
                    <p class="admin-home-action-card__text"><?php echo e((string) $ownerLowStockCount); ?> produit(s) sous le seuil actuel.</p>
                  </a>
                  <a class="admin-home-action-card" href="<?php echo e(base_url('admin/products/index.php?status=draft')); ?>">
                    <h3 class="admin-home-action-card__title">Produits brouillons</h3>
                    <p class="admin-home-action-card__text"><?php echo e((string) $ownerDraftCount); ?> element(s) en attente d'edition.</p>
                  </a>
                </div>
              </div>

              <div class="admin-panel admin-panel--padded admin-home-panel" aria-label="Commandes a traiter">
                <div class="admin-home-panel__header">
                  <div>
                    <p class="admin-home-panel__eyebrow">Suivi</p>
                    <h2 class="admin-home-panel__title">Commandes a traiter</h2>
                    <p class="admin-home-panel__text">Vue rapide sur les commandes les plus recentes encore en cours de traitement.</p>
                  </div>
                  <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/orders/index.php')); ?>">Tout voir</a>
                </div>

                <?php if (!$todo_orders): ?>
                  <div class="admin-empty-panel">
                    <p class="admin-empty-panel__title">Rien a traiter.</p>
                    <p class="admin-empty-panel__text">Les nouvelles commandes a suivre apparaitront ici automatiquement.</p>
                  </div>
                <?php else: ?>
                  <div class="admin-home-list">
                    <?php foreach ($todo_orders as $o): ?>
                      <?php
                        $id = (int) ($o['id'] ?? 0);
                        $num = (string) ($o['order_number'] ?? '');
                        $name = (string) ($o['customer_name'] ?? '');
                        $total = (int) ($o['total_amount'] ?? 0);
                        $status = strtolower(trim((string) ($o['status'] ?? '')));
                        $statusClass = 'admin-status-pill admin-status-pill--neutral';
                        if ($status === 'nouveau') $statusClass = 'admin-status-pill admin-status-pill--info';
                        if ($status === 'confirme') $statusClass = 'admin-status-pill admin-status-pill--warning';
                        if ($status === 'en_preparation' || $status === 'en_livraison') $statusClass = 'admin-status-pill admin-status-pill--success';
                      ?>
                      <div class="admin-home-list__item">
                        <div class="admin-home-list__content">
                          <strong><?php echo e($num); ?></strong>
                          <div class="admin-home-list__meta"><?php echo e($name); ?></div>
                          <div class="admin-home-list__pills">
                            <span class="<?php echo e($statusClass); ?>"><?php echo e($status !== '' ? $status : 'commande'); ?></span>
                          </div>
                        </div>
                        <div class="admin-home-list__aside">
                          <span class="admin-home-list__metric"><?php echo e(number_format($total, 0, ',', ' ')); ?> FCFA</span>
                          <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/orders/show.php?id=' . $id)); ?>">Voir</a>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="admin-home-stack">
              <div class="admin-panel admin-panel--padded admin-home-panel" aria-label="Stock faible">
                <div class="admin-home-panel__header">
                  <div>
                    <p class="admin-home-panel__eyebrow">Inventaire</p>
                    <h2 class="admin-home-panel__title">Stock faible</h2>
                    <p class="admin-home-panel__text">Produits a surveiller en priorite dans l'inventaire existant.</p>
                  </div>
                  <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/products/stock_index.php?filter=low_stock')); ?>">Gerer</a>
                </div>

                <?php if (!$todo_low_stock): ?>
                  <div class="admin-empty-panel">
                    <p class="admin-empty-panel__title">Pas d'alerte stock.</p>
                    <p class="admin-empty-panel__text">Aucun produit n'est actuellement sous le seuil configure dans cette vue.</p>
                  </div>
                <?php else: ?>
                  <div class="admin-home-list">
                    <?php foreach ($todo_low_stock as $p): ?>
                      <?php
                        $id = (int) ($p['id'] ?? 0);
                        $name = (string) ($p['name'] ?? '');
                        $stock = (int) ($p['effective_stock'] ?? ($p['stock'] ?? 0));
                        $thr = (int) ($p['effective_low_stock_threshold'] ?? 10);
                      ?>
                      <div class="admin-home-list__item">
                        <div class="admin-home-list__content">
                          <strong><?php echo e($name); ?></strong>
                          <div class="admin-home-list__meta">Stock : <?php echo e((string) $stock); ?> | Seuil <?php echo e((string) $thr); ?></div>
                        </div>
                        <div class="admin-home-list__aside">
                          <span class="<?php echo $stock <= 0 ? 'admin-status-pill admin-status-pill--danger' : 'admin-status-pill admin-status-pill--warning'; ?>">
                            <?php echo $stock <= 0 ? 'Rupture' : ('Stock ' . e((string) $stock)); ?>
                          </span>
                          <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/products/stock.php?id=' . $id)); ?>">Ajuster</a>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

              <div class="admin-panel admin-panel--padded admin-home-panel" aria-label="Moderation en attente">
                <div class="admin-home-panel__header">
                  <div>
                    <p class="admin-home-panel__eyebrow">Moderation</p>
                    <h2 class="admin-home-panel__title">Files en attente</h2>
                    <p class="admin-home-panel__text">Avis et produits en attente presentes dans les workflows deja existants.</p>
                  </div>
                </div>

                <div class="admin-home-list">
                  <?php if ($todo_pending_reviews): ?>
                    <?php foreach ($todo_pending_reviews as $r): ?>
                      <?php
                        $id = (int) ($r['id'] ?? 0);
                        $name = (string) ($r['name'] ?? '');
                        $rating = (int) ($r['rating'] ?? 0);
                      ?>
                      <div class="admin-home-list__item">
                        <div class="admin-home-list__content">
                          <strong>#<?php echo e((string) $id); ?></strong>
                          <div class="admin-home-list__meta"><?php echo e($name); ?> | <?php echo e((string) $rating); ?>/5</div>
                        </div>
                        <div class="admin-home-list__aside">
                          <span class="admin-status-pill admin-status-pill--warning">Avis</span>
                          <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/reviews/index.php')); ?>">Gerer</a>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>

                  <?php if ($todo_pending_product_reviews): ?>
                    <?php foreach ($todo_pending_product_reviews as $r): ?>
                      <?php
                        $id = (int) ($r['id'] ?? 0);
                        $name = (string) ($r['customer_name'] ?? '');
                        $rating = (int) ($r['rating'] ?? 0);
                        $productName = (string) ($r['product_name'] ?? '');
                      ?>
                      <div class="admin-home-list__item">
                        <div class="admin-home-list__content">
                          <strong>#<?php echo e((string) $id); ?></strong>
                          <div class="admin-home-list__meta"><?php echo e($productName); ?> | <?php echo e($name); ?> | <?php echo e((string) $rating); ?>/5</div>
                        </div>
                        <div class="admin-home-list__aside">
                          <span class="admin-status-pill admin-status-pill--warning">Avis produit</span>
                          <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/product_reviews/index.php')); ?>">Gerer</a>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>

                  <?php if ($todo_pending_products): ?>
                    <?php foreach ($todo_pending_products as $p): ?>
                      <?php
                        $id = (int) ($p['id'] ?? 0);
                        $name = (string) ($p['name'] ?? '');
                      ?>
                      <div class="admin-home-list__item">
                        <div class="admin-home-list__content">
                          <strong><?php echo e($name); ?></strong>
                          <div class="admin-home-list__meta">En attente de publication</div>
                        </div>
                        <div class="admin-home-list__aside">
                          <span class="admin-status-pill admin-status-pill--info">Produit</span>
                          <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/products/edit.php?id=' . $id)); ?>">Voir</a>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>

                  <?php if (!$todo_pending_reviews && !$todo_pending_product_reviews && !$todo_pending_products): ?>
                    <div class="admin-empty-panel">
                      <p class="admin-empty-panel__title">Aucune file en attente.</p>
                      <p class="admin-empty-panel__text">Les demandes de moderation apparaitront ici lorsqu'elles seront disponibles.</p>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="admin-panel admin-panel--padded admin-home-empty admin-home-reveal" aria-label="Espace partenaire">
            <h2 class="admin-home-empty__title">Espace partenaire</h2>
            <p class="admin-home-empty__text">Vous pouvez ajouter des produits et suivre les commandes. Les autres modules restent reserves au proprietaire.</p>
          </div>

          <div class="admin-home-partner-grid admin-home-reveal" aria-label="Modules partenaire">
            <div class="admin-panel admin-panel--padded admin-home-panel">
              <div class="admin-home-panel__header">
                <div>
                  <p class="admin-home-panel__eyebrow">Suivi</p>
                  <h2 class="admin-home-panel__title">Commandes a traiter</h2>
                  <p class="admin-home-panel__text">Vue compacte sur les commandes en cours necessitant une attention.</p>
                </div>
              </div>

              <?php if (!$todo_orders): ?>
                <div class="admin-empty-panel">
                  <p class="admin-empty-panel__title">Aucune commande a traiter.</p>
                  <p class="admin-empty-panel__text">Les nouvelles commandes apparaitront ici automatiquement.</p>
                </div>
              <?php else: ?>
                <div class="admin-home-list">
                  <?php foreach ($todo_orders as $o): ?>
                    <?php
                      $id = (int) ($o['id'] ?? 0);
                      $num = (string) ($o['order_number'] ?? '');
                      $name = (string) ($o['customer_name'] ?? '');
                      $total = (int) ($o['total_amount'] ?? 0);
                    ?>
                    <div class="admin-home-list__item">
                      <div class="admin-home-list__content">
                        <strong><?php echo e($num); ?></strong>
                        <div class="admin-home-list__meta"><?php echo e($name); ?> | <?php echo e(number_format($total, 0, ',', ' ')); ?> FCFA</div>
                      </div>
                      <div class="admin-home-list__aside">
                        <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/orders/show.php?id=' . $id)); ?>">Voir</a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="admin-home-list__footer">
                  <a class="btn admin-btn admin-btn--primary" href="<?php echo e(base_url('admin/orders/index.php')); ?>">Voir toutes les commandes</a>
                </div>
              <?php endif; ?>
            </div>

            <div class="admin-panel admin-panel--padded admin-home-panel">
              <div class="admin-home-panel__header">
                <div>
                  <p class="admin-home-panel__eyebrow">Catalogue</p>
                  <h2 class="admin-home-panel__title">Derniers produits</h2>
                  <p class="admin-home-panel__text">Accedez rapidement aux derniers produits disponibles dans votre espace.</p>
                </div>
              </div>

              <?php if (!$partner_recent_products): ?>
                <div class="admin-empty-panel">
                  <p class="admin-empty-panel__title">Aucun produit pour le moment.</p>
                  <p class="admin-empty-panel__text">Les produits les plus recents apparaitront ici des qu'ils seront disponibles.</p>
                </div>
              <?php else: ?>
                <div class="admin-home-list">
                  <?php foreach ($partner_recent_products as $p): ?>
                    <?php
                      $id = (int) ($p['id'] ?? 0);
                      $name = (string) ($p['name'] ?? '');
                    ?>
                    <div class="admin-home-list__item">
                      <div class="admin-home-list__content">
                        <strong><?php echo e($name); ?></strong>
                        <div class="admin-home-list__meta">#<?php echo e((string) $id); ?></div>
                      </div>
                      <div class="admin-home-list__aside">
                        <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/products/index.php')); ?>">Ouvrir</a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
