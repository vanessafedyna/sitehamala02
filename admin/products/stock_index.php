<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
requireAnyRole(array('owner', 'partner'));
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/ProductModel.php';
require_once __DIR__ . '/../../app/services/InventorySnapshotService.php';

$page_title = 'Admin - Stock';
$page_css = 'pages/admin-products.css';
$page_js = '';
$adminRole = admin_current_role();

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$filter = isset($_GET['filter']) ? trim((string) $_GET['filter']) : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 20;

$db_error = '';
$products = array();
$total = 0;
$lastPage = 1;
try {
  $pdo = db();
  $model = new ProductModel($pdo);
  $inventory = new InventorySnapshotService($pdo);

  $filters = array();
  if ($q !== '') {
    $filters['q'] = $q;
  }

  $allProducts = $inventory->hydrateProductRows($model->adminList($filters));
  if ($filter === 'low_stock') {
    $allProducts = array_values(array_filter($allProducts, static function (array $product): bool {
      return (int) ($product['is_low_stock_effective'] ?? 0) === 1;
    }));
  } elseif ($filter === 'out_of_stock') {
    $allProducts = array_values(array_filter($allProducts, static function (array $product): bool {
      return (int) ($product['is_out_of_stock_effective'] ?? 0) === 1;
    }));
  }

  usort($allProducts, static function (array $a, array $b): int {
    $stockA = (int) ($a['effective_stock'] ?? ($a['stock'] ?? 0));
    $stockB = (int) ($b['effective_stock'] ?? ($b['stock'] ?? 0));
    if ($stockA !== $stockB) {
      return $stockA <=> $stockB;
    }

    return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
  });

  $total = count($allProducts);
  $lastPage = max(1, (int) ceil($total / $perPage));
  $page = min($page, $lastPage);
  $offset = ($page - 1) * $perPage;
  $products = array_slice($allProducts, $offset, $perPage);
} catch (Throwable $e) {
  $db_error = 'Impossible de charger le stock produits.';
  $products = array();
  $total = 0;
  $lastPage = 1;
  $page = 1;
}

require_once __DIR__ . '/../_layout_header.php';
?>

<style>
  .admin-stock-page {
    display: grid;
    gap: 16px;
    overflow-x: clip;
  }
  .admin-stock-reveal {
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 220ms ease, transform 220ms ease;
  }
  .admin-stock-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
  }
  .admin-stock-page .admin-page-header {
    align-items: flex-start;
    gap: 18px;
  }
  .admin-stock-page .admin-page-header__actions {
    justify-content: flex-end;
  }
  .admin-stock-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
  }
  .admin-stock-meta__chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.78);
    color: var(--admin-text-muted);
    font-size: 0.84rem;
  }
  .admin-stock-meta__chip strong {
    color: var(--admin-ink);
  }
  .admin-stock-toolbar {
    display: grid;
    gap: 14px;
  }
  .admin-stock-toolbar .admin-filterbar__group {
    align-items: stretch;
  }
  .admin-stock-toolbar .admin-filterbar__group--grow {
    flex: 1 1 520px;
  }
  .admin-stock-toolbar .admin-field,
  .admin-stock-toolbar .admin-select {
    min-width: min(220px, 100%);
  }
  .admin-stock-toolbar .admin-help {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }
  .admin-stock-toolbar__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }
  .admin-stock-toolbar__actions .admin-btn {
    white-space: nowrap;
  }
  .admin-stock-table-panel {
    overflow: hidden;
  }
  .admin-stock-table-shell {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
  }
  .admin-stock-table-shell .admin-table {
    min-width: 940px;
  }
  .admin-stock-table-shell thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f7faf8;
  }
  .admin-stock-table-shell td,
  .admin-stock-table-shell th {
    padding-top: 14px;
    padding-bottom: 14px;
    vertical-align: top;
  }
  .admin-stock-table-shell tbody tr {
    transition: background-color 140ms ease, box-shadow 140ms ease;
  }
  .admin-stock-table-shell tbody tr:hover {
    background: rgba(31, 122, 79, 0.035);
  }
  .admin-stock-product {
    display: grid;
    gap: 4px;
    min-width: 0;
  }
  .admin-stock-product strong,
  .admin-stock-product code {
    overflow-wrap: anywhere;
  }
  .admin-stock-value {
    color: var(--admin-ink);
    font-weight: 700;
  }
  .admin-stock-status {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
  }
  .admin-stock-actions {
    display: flex;
    justify-content: flex-end;
  }
  .admin-stock-actions .admin-btn {
    white-space: nowrap;
  }
  .admin-stock-empty {
    padding: 10px 4px;
  }
  .admin-stock-empty .admin-empty-panel__actions {
    margin-top: 16px;
  }
  .admin-stock-mobile-cards {
    display: grid;
    gap: 14px;
  }
  .admin-stock-mobile-card {
    display: grid;
    gap: 14px;
    border: 1px solid var(--admin-border);
    border-radius: 18px;
    background: var(--admin-surface);
    box-shadow: var(--admin-shadow-sm);
    transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease;
  }
  .admin-stock-mobile-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 18px 36px rgba(18, 52, 36, 0.08);
    border-color: rgba(31, 122, 79, 0.16);
  }
  .admin-stock-mobile-card__header {
    display: grid;
    gap: 6px;
    min-width: 0;
  }
  .admin-stock-mobile-card__title {
    margin: 0;
    color: var(--admin-ink);
    font-size: 1rem;
    line-height: 1.35;
  }
  .admin-stock-mobile-card__meta {
    color: var(--admin-text-muted);
    font-size: 0.84rem;
    overflow-wrap: anywhere;
  }
  .admin-stock-mobile-card__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }
  .admin-stock-mobile-card__field {
    min-width: 0;
    padding: 12px;
    border: 1px solid rgba(31, 122, 79, 0.08);
    border-radius: 14px;
    background: #fbfcfb;
  }
  .admin-stock-mobile-card__label {
    display: block;
    margin-bottom: 6px;
    color: var(--admin-text-muted);
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
  }
  .admin-stock-mobile-card__value {
    color: var(--admin-ink);
    font-weight: 700;
    overflow-wrap: anywhere;
  }
  .admin-stock-mobile-card__status,
  .admin-stock-mobile-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }
  .admin-stock-mobile-card__actions .admin-btn {
    width: 100%;
  }
  .admin-stock-page .admin-btn--primary,
  .admin-stock-page .admin-btn--secondary {
    background-image: none;
  }
  .admin-stock-page .admin-btn:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(31, 122, 79, 0.16);
  }
  @media (max-width: 1024px) {
    .admin-stock-toolbar .admin-field,
    .admin-stock-toolbar .admin-select {
      min-width: min(180px, 100%);
    }
  }
  @media (max-width: 820px) {
    .admin-stock-page .admin-page-header {
      padding: 16px;
    }
  }
  @media (max-width: 768px) {
    .admin-stock-mobile-card__grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
  @media (max-width: 430px) {
    .admin-stock-meta {
      gap: 8px;
    }
    .admin-stock-toolbar__actions {
      width: 100%;
    }
    .admin-stock-toolbar__actions .admin-btn {
      flex: 1 1 100%;
      justify-content: center;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var revealNodes = Array.prototype.slice.call(document.querySelectorAll('.admin-stock-reveal'));
    if (!revealNodes.length) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || window.innerWidth <= 768) {
      revealNodes.forEach(function (node) {
        node.classList.add('is-visible');
        node.style.transitionDelay = '0ms';
      });
      return;
    }

    var revealObserver = new IntersectionObserver(function (entries, observer) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.14 });

    revealNodes.forEach(function (node, index) {
      node.style.transitionDelay = Math.min(index * 45, 220) + 'ms';
      revealObserver.observe(node);
    });
  });
</script>

<?php
  $hasActiveFilters = ($q !== '' || $filter !== '');
  $lowStockCount = 0;
  $outOfStockCount = 0;
  foreach ($products as $productMeta) {
    if (!is_array($productMeta)) continue;
    if ((int) ($productMeta['is_out_of_stock_effective'] ?? 0) === 1) {
      $outOfStockCount++;
    } elseif ((int) ($productMeta['is_low_stock_effective'] ?? 0) === 1) {
      $lowStockCount++;
    }
  }
?>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-stock-page">
        <div class="admin-page-header admin-stock-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow">Inventaire</p>
            <h1 class="admin-page-header__title">Stock produits</h1>
            <p class="admin-page-header__subtitle">Surveillez les niveaux de stock, les seuils sensibles et les ajustements prioritaires depuis une vue plus nette et plus homogène.</p>
            <div class="admin-stock-meta" aria-label="Indicateurs stock">
              <span class="admin-stock-meta__chip"><strong><?php echo e((string) $total); ?></strong> produit(s)</span>
              <span class="admin-stock-meta__chip"><strong><?php echo e((string) $lowStockCount); ?></strong> stock faible</span>
              <span class="admin-stock-meta__chip"><strong><?php echo e((string) $outOfStockCount); ?></strong> rupture</span>
            </div>
          </div>
          <div class="admin-page-header__actions">
            <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/index.php')); ?>">
              <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour aux produits
            </a>
          </div>
        </div>

        <?php if ($db_error !== ''): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-stock-reveal is-visible" role="alert">
            <strong><?php echo e($db_error); ?></strong>
          </div>
        <?php endif; ?>

        <div class="admin-panel admin-panel--padded admin-stock-toolbar admin-stock-reveal" aria-label="Filtres stock">
          <div class="admin-filterbar">
            <form method="get" action="" class="admin-filterbar__group admin-filterbar__group--grow" role="search" novalidate>
              <label class="sr-only" for="q">Recherche</label>
              <input id="q" name="q" type="search" class="admin-field" value="<?php echo e($q); ?>" placeholder="Nom ou SKU">

              <label class="sr-only" for="filter">Vue</label>
              <select id="filter" name="filter" class="admin-select">
                <option value="" <?php echo $filter === '' ? 'selected' : ''; ?>>Tout le stock</option>
                <option value="low_stock" <?php echo $filter === 'low_stock' ? 'selected' : ''; ?>>Stock faible</option>
                <option value="out_of_stock" <?php echo $filter === 'out_of_stock' ? 'selected' : ''; ?>>Rupture</option>
              </select>

              <div class="admin-stock-toolbar__actions">
                <button class="btn admin-btn admin-btn--primary" type="submit">Filtrer</button>
                <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/stock_index.php')); ?>">Réinitialiser</a>
              </div>
            </form>
          </div>
          <div class="admin-help">
            <span>Classement par stock croissant pour faire remonter les priorités.</span>
          </div>
        </div>

        <div class="feature-card admin-panel admin-table-shell admin-table-wrap admin-desktop-only admin-stock-table-panel admin-stock-table-shell admin-stock-reveal is-visible" aria-label="Liste stock produits">
          <?php if (!$products): ?>
            <div class="admin-empty-panel admin-stock-empty">
              <?php if ($hasActiveFilters): ?>
                <p class="admin-empty-panel__title">Aucun produit ne correspond aux filtres stock.</p>
                <p class="admin-empty-panel__text">Essayez une autre recherche ou réinitialisez les filtres.</p>
                <div class="admin-empty-panel__actions">
                  <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/stock_index.php')); ?>">Réinitialiser</a>
                </div>
              <?php else: ?>
                <p class="admin-empty-panel__title">Aucun produit disponible pour le moment.</p>
                <p class="admin-empty-panel__text">Ajoutez des produits pour commencer à gérer le stock.</p>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Produit</th>
                  <th>Référence</th>
                  <th>Stock</th>
                  <th>Seuil</th>
                  <th>Statut</th>
                  <th style="text-align:right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($products as $product): ?>
                  <?php
                    $id = (int) ($product['id'] ?? 0);
                    $name = (string) ($product['name'] ?? '');
                    $sku = trim((string) ($product['sku'] ?? ''));
                    $stock = (int) ($product['effective_stock'] ?? ($product['stock'] ?? 0));
                    $threshold = (int) ($product['effective_low_stock_threshold'] ?? 10);
                    $usesVariants = (int) ($product['uses_variants'] ?? 0) === 1;
                    $variantSizes = (array) ($product['variant_sizes'] ?? array());
                    $stockBadgeLabel = 'OK stock';
                    $stockBadgeClass = 'admin-status-pill admin-status-pill--success';
                    if ($stock <= 0) {
                      $stockBadgeLabel = 'Rupture';
                      $stockBadgeClass = 'admin-status-pill admin-status-pill--danger';
                    } elseif ($stock <= $threshold) {
                      $stockBadgeLabel = 'Stock faible';
                      $stockBadgeClass = 'admin-status-pill admin-status-pill--warning';
                    }
                  ?>
                  <tr>
                    <td>
                      <div class="admin-stock-product">
                        <strong><?php echo e($name !== '' ? $name : ('Produit #' . $id)); ?></strong>
                        <div class="admin-help">ID #<?php echo e((string) $id); ?></div>
                        <?php if ($usesVariants): ?>
                          <div class="admin-help">Tailles : <?php echo e($variantSizes ? implode(', ', $variantSizes) : '-'); ?></div>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td>
                      <?php if ($sku !== ''): ?>
                        <code><?php echo e($sku); ?></code>
                      <?php else: ?>
                        <span class="admin-help">—</span>
                      <?php endif; ?>
                    </td>
                    <td><span class="admin-stock-value"><?php echo e((string) $stock); ?></span></td>
                    <td><span class="admin-help"><?php echo e((string) $threshold); ?></span></td>
                    <td>
                      <div class="admin-stock-status">
                        <span class="<?php echo e($stockBadgeClass); ?>"><?php echo e($stockBadgeLabel); ?></span>
                      </div>
                    </td>
                    <td style="text-align:right;">
                      <div class="admin-stock-actions">
                        <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/products/stock.php?id=' . $id)); ?>">Ajuster</a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <div class="admin-mobile-only admin-stock-reveal is-visible" aria-label="Cartes stock produits">
          <?php if (!$products): ?>
            <div class="admin-stock-mobile-cards">
              <div class="admin-mobile-card admin-stock-mobile-card">
                <div class="admin-empty-panel admin-stock-empty">
                  <?php if ($hasActiveFilters): ?>
                    <p class="admin-empty-panel__title">Aucun produit ne correspond aux filtres stock.</p>
                    <p class="admin-empty-panel__text">Essayez une autre recherche ou réinitialisez les filtres.</p>
                    <div class="admin-empty-panel__actions">
                      <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/stock_index.php')); ?>">Réinitialiser</a>
                    </div>
                  <?php else: ?>
                    <p class="admin-empty-panel__title">Aucun produit disponible pour le moment.</p>
                    <p class="admin-empty-panel__text">Ajoutez des produits pour commencer à gérer le stock.</p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="admin-stock-mobile-cards">
              <?php foreach ($products as $product): ?>
                <?php
                  $id = (int) ($product['id'] ?? 0);
                  $name = (string) ($product['name'] ?? '');
                  $sku = trim((string) ($product['sku'] ?? ''));
                  $stock = (int) ($product['effective_stock'] ?? ($product['stock'] ?? 0));
                  $threshold = (int) ($product['effective_low_stock_threshold'] ?? 10);
                  $usesVariants = (int) ($product['uses_variants'] ?? 0) === 1;
                  $variantSizes = (array) ($product['variant_sizes'] ?? array());
                  $stockBadgeLabel = 'OK stock';
                  $stockBadgeClass = 'admin-status-pill admin-status-pill--success';
                  if ($stock <= 0) {
                    $stockBadgeLabel = 'Rupture';
                    $stockBadgeClass = 'admin-status-pill admin-status-pill--danger';
                  } elseif ($stock <= $threshold) {
                    $stockBadgeLabel = 'Stock faible';
                    $stockBadgeClass = 'admin-status-pill admin-status-pill--warning';
                  }
                ?>
                <article class="admin-mobile-card admin-stock-mobile-card">
                  <div class="admin-stock-mobile-card__header">
                    <h2 class="admin-stock-mobile-card__title"><?php echo e($name !== '' ? $name : ('Produit #' . $id)); ?></h2>
                    <div class="admin-stock-mobile-card__meta"><?php echo e($sku !== '' ? $sku : ('ID #' . $id)); ?></div>
                    <?php if ($usesVariants): ?>
                      <div class="admin-stock-mobile-card__meta">Tailles : <?php echo e($variantSizes ? implode(', ', $variantSizes) : '-'); ?></div>
                    <?php endif; ?>
                  </div>

                  <div class="admin-stock-mobile-card__grid">
                    <div class="admin-stock-mobile-card__field">
                      <span class="admin-stock-mobile-card__label">Stock actuel</span>
                      <div class="admin-stock-mobile-card__value"><?php echo e((string) $stock); ?></div>
                    </div>
                    <div class="admin-stock-mobile-card__field">
                      <span class="admin-stock-mobile-card__label">Seuil faible</span>
                      <div class="admin-stock-mobile-card__value"><?php echo e((string) $threshold); ?></div>
                    </div>
                  </div>

                  <div class="admin-stock-mobile-card__status">
                    <span class="<?php echo e($stockBadgeClass); ?>"><?php echo e($stockBadgeLabel); ?></span>
                  </div>

                  <div class="admin-stock-mobile-card__actions">
                    <a class="btn admin-btn admin-btn--primary" href="<?php echo e(base_url('admin/products/stock.php?id=' . $id)); ?>"><?php echo ($adminRole !== 'owner' && (int) ($product['uses_variants'] ?? 0) === 1) ? 'Voir stock' : 'Ajuster stock'; ?></a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($lastPage > 1): ?>
          <nav class="admin-pagination admin-stock-reveal is-visible" aria-label="Pagination stock">
            <?php
              $qsBase = array();
              if ($q !== '') $qsBase['q'] = $q;
              if ($filter !== '') $qsBase['filter'] = $filter;
            ?>
            <?php if ($page > 1): ?>
              <?php $prev = $qsBase; $prev['page'] = $page - 1; ?>
              <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/products/stock_index.php?' . http_build_query($prev))); ?>">Précédent</a>
            <?php endif; ?>
            <span class="admin-help">Page <?php echo e((string) $page); ?> / <?php echo e((string) $lastPage); ?></span>
            <?php if ($page < $lastPage): ?>
              <?php $next = $qsBase; $next['page'] = $page + 1; ?>
              <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/products/stock_index.php?' . http_build_query($next))); ?>">Suivant</a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../_layout_footer.php'; ?>
