<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../_flash.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/ProductModel.php';
require_once __DIR__ . '/../../app/services/AdminAuditService.php';
require_once __DIR__ . '/../../app/services/InventorySnapshotService.php';
require_once __DIR__ . '/../../app/services/ProductImageService.php';
require_once __DIR__ . '/../../app/services/ProductVariantService.php';
require_once __DIR__ . '/../../app/services/StockMovementService.php';

/* Publication et modération */
$adminRole = admin_current_role();
$isOwner = ($adminRole === 'owner');

$page_title = 'Admin - Produits';
$page_css = 'pages/admin-products.css';
$page_js = '';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$status = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 20;

$flash = admin_flash_get('products');
$db_error = '';
$products = array();
$total = 0;
$lastPage = 1;
/* Compatibilité avec les schémas qui gèrent les produits vedettes. */
$supports_featured = false;
/* Product publication status is optional on older schemas */
$supports_status = false;
try {
  $pdo = db();
  $model = new ProductModel($pdo);
  $inventory = new InventorySnapshotService($pdo);

  /* Détecte la disponibilité des colonnes de mise en avant. */
  try {
    $fields = function_exists('db_table_columns') ? db_table_columns($pdo, 'products') : array();
    $supports_featured = in_array('is_featured', $fields, true) && in_array('featured_rank', $fields, true);
   
    $supports_status = in_array('status', $fields, true);
  } catch (Throwable $e) {
    $supports_featured = false;
    $supports_status = false;
  }

  if ($isOwner && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
      admin_flash_set('products', 'error', 'Session expirée. Veuillez réessayer.');
      redirect('admin/products/index.php');
    }

    $quickPriceId = isset($_POST['quick_price_submit']) ? (int) $_POST['quick_price_submit'] : 0;
    $quickStockId = isset($_POST['quick_stock_submit']) ? (int) $_POST['quick_stock_submit'] : 0;
    $toggleActiveId = isset($_POST['toggle_active']) ? (int) $_POST['toggle_active'] : 0;

    if ($quickPriceId > 0) {
      $priceValues = isset($_POST['quick_price']) && is_array($_POST['quick_price']) ? $_POST['quick_price'] : array();
      $priceRaw = (string) ($priceValues[$quickPriceId] ?? '');
      if ($priceRaw === '' || !preg_match('/^\d+$/', $priceRaw)) {
        admin_flash_set('products', 'error', 'Prix invalide.');
      } else {
        $model->update($quickPriceId, array('price' => (int) $priceRaw));
        admin_flash_set('products', 'success', 'Prix mis à jour.');
      }
      redirect('admin/products/index.php');
    }

    if ($quickStockId > 0) {
      $stockValues = isset($_POST['quick_stock']) && is_array($_POST['quick_stock']) ? $_POST['quick_stock'] : array();
      $stockRaw = (string) ($stockValues[$quickStockId] ?? '');
      $variantService = new ProductVariantService($pdo);
      if ($variantService->isSupported() && $variantService->hasAnyVariants($quickStockId)) {
        admin_flash_set('products', 'error', 'Ce produit utilise des variantes. Modifiez le stock par taille dans la fiche produit.');
      } elseif ($stockRaw === '' || !preg_match('/^\d+$/', $stockRaw)) {
        admin_flash_set('products', 'error', 'Stock invalide.');
      } else {
        $productBefore = $model->find($quickStockId);
        if (!$productBefore) {
          admin_flash_set('products', 'error', 'Produit introuvable.');
        } else {
          $oldStock = (int) ($productBefore['stock'] ?? 0);
          $newStock = (int) $stockRaw;
          $deltaStock = $newStock - $oldStock;

          if ($deltaStock === 0) {
            admin_flash_set('products', 'success', 'Stock inchangé.');
          } else {
            $pdo->beginTransaction();
            try {
              $model->update($quickStockId, array('stock' => $newStock));
              $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
              StockMovementService::record($pdo, $quickStockId, $deltaStock, 'manual_adjust', null, $adminId, 'Mise à jour rapide depuis la liste produits');
              AdminAuditService::log($pdo, $adminId, 'inventory_adjusted', 'product', $quickStockId, array(
                'actor_role' => $adminRole,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'source' => 'admin_products_quick_stock',
              ));
              $pdo->commit();
              admin_flash_set('products', 'success', 'Stock mis à jour.');
            } catch (Throwable $e) {
              if ($pdo->inTransaction()) {
                $pdo->rollBack();
              }
              admin_flash_set('products', 'error', 'Impossible de mettre à jour le stock.');
            }
          }
        }
      }
      redirect('admin/products/index.php');
    }

    if ($toggleActiveId > 0) {
      $activeTargets = isset($_POST['active_target']) && is_array($_POST['active_target']) ? $_POST['active_target'] : array();
      $target = ((int) ($activeTargets[$toggleActiveId] ?? 0)) ? 1 : 0;
      $model->update($toggleActiveId, array('is_active' => $target));
      admin_flash_set('products', 'success', $target === 1 ? 'Produit activé.' : 'Produit passé en brouillon.');
      redirect('admin/products/index.php');
    }
  }

  if ($isOwner && $supports_status && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') && (string) ($_POST['bulk_action'] ?? '') === 'publish_selected') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
      admin_flash_set('products', 'error', 'Session expirée. Veuillez réessayer.');
      redirect('admin/products/index.php');
    }

    $rawIds = $_POST['bulk_ids'] ?? array();
    $ids = array();
    if (is_array($rawIds)) {
      foreach ($rawIds as $rawId) {
        $pid = (int) $rawId;
        if ($pid > 0) {
          $ids[$pid] = $pid;
        }
      }
    }

    if (!$ids) {
      admin_flash_set('products', 'error', 'Sélection vide. Choisissez au moins un produit en attente.');
      redirect('admin/products/index.php');
    }

    $publishedCount = 0;
    foreach ($ids as $pid) {
      try {
        $p = $model->find((int) $pid);
        if (!$p) continue;
        $st = strtolower(trim((string) ($p['status'] ?? '')));
        if ($st !== 'pending') continue;
        if ($model->update((int) $pid, array('status' => 'published'))) {
          $publishedCount += 1;
        }
      } catch (Throwable $e) {
        // Continuer sans interrompre le lot.
      }
    }

    if ($publishedCount > 0) {
      admin_flash_set('products', 'success', $publishedCount . ' produit(s) publié(s) avec succès.');
    } else {
      admin_flash_set('products', 'error', "Aucun produit éligible n'a pu être publié.");
    }
    redirect('admin/products/index.php');
  }

  $filters = array();
  if ($q !== '') {
    $filters['q'] = $q;
  }
 
  if ($supports_status && in_array($status, array('pending', 'published'), true)) {
    $filters['status'] = $status;
  }

  $total = $model->adminCount($filters);
  $lastPage = max(1, (int) ceil($total / $perPage));
  $page = min($page, $lastPage);

  $offset = ($page - 1) * $perPage;
  $products = $model->adminList(array_merge($filters, array(
    'limit' => $perPage,
    'offset' => $offset,
  )));
  $products = $inventory->hydrateProductRows($products);
} catch (Throwable $e) {
  $db_error = 'Impossible de charger les produits (base de données).';
  $products = array();
  $total = 0;
  $lastPage = 1;
  $page = 1;
}

require_once __DIR__ . '/../_layout_header.php';
?>

<link rel="stylesheet" href="<?php echo e(base_url('assets/css/pages/admin-products-index.css')); ?>">

<script src="<?php echo e(base_url('assets/js/pages/admin-products-index.js')); ?>"></script>

<?php
  $showBulkPublish = ($isOwner && $supports_status);
  $hasActiveFilters = ($q !== '' || ($supports_status && $status !== ''));
  $pendingEligibleCount = 0;
  if ($showBulkPublish && $products) {
    foreach ($products as $pp) {
      if (!is_array($pp)) continue;
      $ppStatus = strtolower(trim((string) ($pp['status'] ?? '')));
      if ($ppStatus === 'pending') {
        $pendingEligibleCount++;
      }
    }
  }
?>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-products-page">
        <div class="admin-page-header admin-products-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow">Catalogue</p>
            <h1 class="admin-page-header__title">Produits</h1>
            <p class="admin-page-header__subtitle">Supervisez le catalogue produits depuis une vue admin plus nette, plus dense et plus premium.</p>
            <div class="admin-products-meta" aria-label="Indicateurs catalogue">
              <span class="admin-products-meta__chip"><strong><?php echo e((string) $total); ?></strong> produit(s)</span>
              <?php if ($showBulkPublish): ?>
                <span class="admin-products-meta__chip"><strong><?php echo e((string) $pendingEligibleCount); ?></strong> en attente sur cette page</span>
              <?php endif; ?>
              <span class="admin-products-meta__chip"><strong><?php echo e((string) count($products)); ?></strong> sur cette page</span>
            </div>
          </div>
          <div class="admin-page-header__actions">
            <a class="btn admin-btn admin-btn--primary" href="<?php echo e(base_url('admin/products/create.php')); ?>">
              <i class="fas fa-plus" aria-hidden="true"></i> Ajouter un produit
            </a>
            <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">
              <i class="fas fa-gauge-high" aria-hidden="true"></i> Retour dashboard
            </a>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="admin-alert admin-alert--<?php echo e($flash['type']); ?> admin-panel admin-panel--padded admin-products-reveal is-visible" role="status" aria-live="polite">
            <strong><?php echo e($flash['message']); ?></strong>
          </div>
        <?php endif; ?>

        <?php if ($db_error): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-products-reveal is-visible" role="alert">
            <strong><?php echo e($db_error); ?></strong>
          </div>
        <?php else: ?>
          <?php if (!$supports_featured): ?>
            <div class="admin-alert admin-panel admin-panel--padded admin-products-reveal" role="status" aria-live="polite">
              <strong>Produits vedettes :</strong> exécutez `database/patch_products_featured.sql` pour activer cette section.
            </div>
          <?php endif; ?>

          <div class="admin-panel admin-panel--padded admin-products-toolbar admin-products-reveal" aria-label="Filtres produits">
            <div class="admin-filterbar admin-desktop-only">
              <form method="get" action="" class="admin-filterbar__group admin-filterbar__group--grow" role="search">
                <?php if ($supports_status): ?>
                  <label class="sr-only" for="status">Statut</label>
                  <select id="status" name="status" class="admin-select">
                    <option value="">Tous les statuts</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>En attente</option>
                    <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Publié</option>
                  </select>
                <?php endif; ?>

                <label class="sr-only" for="q">Recherche</label>
                <input class="admin-field" id="q" name="q" type="text" value="<?php echo e($q); ?>" placeholder="Rechercher par nom ou SKU">

                <button class="btn admin-btn admin-btn--primary" type="submit">
                  <i class="fas fa-search" aria-hidden="true"></i> Rechercher
                </button>

                <?php if ($hasActiveFilters): ?>
                  <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/index.php')); ?>">Réinitialiser</a>
                <?php endif; ?>
              </form>

              <div class="admin-help">
                <?php echo e((string) $total); ?> produit(s)
              </div>
            </div>

            <details class="admin-mobile-only admin-mobile-section admin-panel admin-products-reveal" aria-label="Filtres mobile">
              <summary class="admin-mobile-section__summary">
                <span>
                  <strong>Filtres</strong>
                  <span class="admin-help"><?php echo e((string) $total); ?> produit(s)</span>
                </span>
                <span class="admin-mobile-section__chevron" aria-hidden="true">+</span>
              </summary>
              <div class="admin-mobile-section__body">
                <form method="get" action="" class="admin-filterbar__group admin-filterbar__group--grow" role="search">
                  <?php if ($supports_status): ?>
                    <label class="sr-only" for="status_mobile">Statut</label>
                    <select id="status_mobile" name="status" class="admin-select">
                      <option value="">Tous les statuts</option>
                      <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>En attente</option>
                      <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Publié</option>
                    </select>
                  <?php endif; ?>

                  <label class="sr-only" for="q_mobile">Recherche</label>
                  <input class="admin-field" id="q_mobile" name="q" type="text" value="<?php echo e($q); ?>" placeholder="Rechercher par nom ou SKU">

                  <button class="btn admin-btn admin-btn--primary" type="submit">
                    <i class="fas fa-search" aria-hidden="true"></i> Rechercher
                  </button>

                  <?php if ($hasActiveFilters): ?>
                    <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/index.php')); ?>">Réinitialiser</a>
                  <?php endif; ?>
                </form>
              </div>
            </details>
          </div>

          <div class="feature-card admin-panel admin-table-shell admin-table-wrap admin-desktop-only admin-products-table-panel admin-products-table-shell admin-products-reveal is-visible" aria-label="Liste produits">
            <form method="post" action="">
              <?php echo csrf_field(); ?>

              <?php if ($showBulkPublish): ?>
                <div class="admin-products-bulknote">
                  <div class="admin-help">
                    Sélectionnez les produits en attente puis publiez-les en une fois.
                    <?php if ($pendingEligibleCount <= 0): ?>
                      <span>Aucun produit éligible sur cette page.</span>
                    <?php endif; ?>
                  </div>
                  <div class="admin-products-bulknote__actions">
                    <button
                      class="btn admin-btn admin-btn--primary"
                      type="submit"
                      name="bulk_action"
                      value="publish_selected"
                      <?php echo $pendingEligibleCount <= 0 ? 'disabled aria-disabled="true" title="Aucun produit en attente sur cette page."' : ''; ?>
                    >Publier la sélection</button>
                  </div>
                </div>
              <?php endif; ?>

              <table class="admin-table">
                <thead>
                  <tr>
                    <?php if ($showBulkPublish): ?>
                      <th>
                        <input
                          class="admin-products-check"
                          type="checkbox"
                          aria-label="Tout sélectionner"
                          onclick="document.querySelectorAll('.js-bulk-product').forEach(function(el){ el.checked = event.currentTarget.checked; });"
                        >
                      </th>
                    <?php endif; ?>
                    <th>Produit</th>
                    <th>Référence</th>
                    <th class="admin-col-num">Prix</th>
                    <th class="admin-col-num">Stock</th>
                    <th>Catégorie</th>
                    <th>Statut</th>
                    <th>Vedette</th>
                    <th class="admin-col-num">Ordre</th>
                    <th class="admin-col-num">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$products): ?>
                    <tr>
                      <td colspan="<?php echo $showBulkPublish ? '10' : '9'; ?>" class="admin-empty-row">
                        <div class="admin-empty-panel admin-products-empty">
                          <?php if ($hasActiveFilters): ?>
                            <p class="admin-empty-panel__title">Aucun produit ne correspond aux filtres appliqués.</p>
                            <p class="admin-empty-panel__text">Ajustez la recherche ou réinitialisez les filtres pour retrouver le catalogue.</p>
                            <div class="admin-empty-panel__actions">
                              <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/index.php')); ?>">Réinitialiser</a>
                            </div>
                          <?php else: ?>
                            <p class="admin-empty-panel__title">Aucun produit disponible.</p>
                            <p class="admin-empty-panel__text">Ajoutez un premier produit pour commencer à structurer le catalogue.</p>
                            <div class="admin-empty-panel__actions">
                              <a class="btn admin-btn admin-btn--primary" href="<?php echo e(base_url('admin/products/create.php')); ?>">Ajouter un produit</a>
                            </div>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>

                  <?php foreach ($products as $p): ?>
                    <?php
                      $id = (int) ($p['id'] ?? 0);
                      $name = (string) ($p['name'] ?? '');
                      $sku = (string) ($p['sku'] ?? '');
                      $price = (int) ($p['price'] ?? 0);
                      $stock = (int) ($p['stock'] ?? 0);
                      $category = trim((string) ($p['category'] ?? ''));
                      $isActive = (int) ($p['is_active'] ?? 1);
                      $pubStatus = $supports_status ? strtolower(trim((string) ($p['status'] ?? ''))) : '';
                      if ($pubStatus !== 'pending' && $pubStatus !== 'published') $pubStatus = '';
                      $isFeatured = (int) ($p['is_featured'] ?? 0);
                      $featuredRank = isset($p['featured_rank']) ? (int) $p['featured_rank'] : 0;
                      $formId = 'featuredForm' . (string) $id;
                      $img = (string) ($p['image1'] ?? ($p['image_path'] ?? ($p['image_main'] ?? ($p['image'] ?? ''))));
                      $imgUrl = $img !== '' ? ProductImageService::toUrl($img) : base_url('assets/images/placeholders/product-placeholder.svg');
                      $lowThreshold = (int) ($p['effective_low_stock_threshold'] ?? ($p['low_stock_threshold'] ?? 10));
                      $hasVariants = (int) ($p['uses_variants'] ?? 0) === 1;
                      $sizeSummary = $hasVariants ? implode(', ', (array) ($p['variant_sizes'] ?? array())) : '';
                      $displayStock = (int) ($p['effective_stock'] ?? $stock);
                      $stockLabel = 'En stock';
                      $stockPillClass = 'admin-status-pill admin-status-pill--success';
                      if ($displayStock <= 0) {
                        $stockLabel = 'Rupture';
                        $stockPillClass = 'admin-status-pill admin-status-pill--danger';
                      } elseif ($lowThreshold > 0 && $displayStock <= $lowThreshold) {
                        $stockLabel = 'Stock faible';
                        $stockPillClass = 'admin-status-pill admin-status-pill--warning';
                      }
                    ?>
                    <tr>
                      <?php if ($showBulkPublish): ?>
                        <td>
                          <?php if ($pubStatus === 'pending'): ?>
                            <input class="admin-products-check js-bulk-product" type="checkbox" name="bulk_ids[]" value="<?php echo (int) $id; ?>" aria-label="Sélectionner ce produit">
                          <?php else: ?>
                            <span class="admin-help">-</span>
                          <?php endif; ?>
                        </td>
                      <?php endif; ?>
                      <td>
                        <div class="admin-products-item">
                          <img class="admin-products-thumb" src="<?php echo e($imgUrl); ?>" alt="">
                          <div class="admin-products-name">
                            <strong><?php echo e($name); ?></strong>
                            <div class="admin-products-name__meta">ID #<?php echo e((string) $id); ?></div>
                            <?php if ($hasVariants): ?>
                              <div class="admin-products-name__meta">Tailles : <?php echo e($sizeSummary !== '' ? $sizeSummary : '-'); ?></div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td class="admin-products-sku">
                        <?php if ($sku !== ''): ?>
                          <code><?php echo e($sku); ?></code>
                        <?php else: ?>
                          <span class="admin-help">Sans SKU</span>
                        <?php endif; ?>
                      </td>
                      <td class="admin-col-num">
                        <?php if ($isOwner): ?>
                          <div class="admin-products-inline-edit" data-inline-edit>
                            <div class="admin-products-inline-edit__display">
                              <span class="admin-products-price"><?php echo e((string) $price); ?> FCFA</span>
                              <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="button" data-inline-edit-open>Modifier</button>
                            </div>
                            <div class="admin-products-inline-edit__form">
                              <input class="admin-field admin-products-inline-edit__input" type="number" min="0" step="1" name="quick_price[<?php echo (int) $id; ?>]" value="<?php echo e((string) $price); ?>">
                              <button class="btn admin-btn admin-btn--primary admin-btn--sm" type="submit" name="quick_price_submit" value="<?php echo (int) $id; ?>">Valider</button>
                              <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="button" data-inline-edit-cancel>Annuler</button>
                            </div>
                          </div>
                        <?php else: ?>
                          <span class="admin-products-price"><?php echo e((string) $price); ?> FCFA</span>
                        <?php endif; ?>
                      </td>
                      <td class="admin-col-num">
                        <?php if ($isOwner && !$hasVariants): ?>
                          <div class="admin-products-inline-edit" data-inline-edit>
                            <div class="admin-products-inline-edit__display">
                              <span class="admin-products-price"><?php echo e((string) $displayStock); ?></span>
                              <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="button" data-inline-edit-open>Modifier</button>
                            </div>
                            <div class="admin-products-inline-edit__form">
                              <input class="admin-field admin-products-inline-edit__input" type="number" min="0" step="1" name="quick_stock[<?php echo (int) $id; ?>]" value="<?php echo e((string) $displayStock); ?>">
                              <button class="btn admin-btn admin-btn--primary admin-btn--sm" type="submit" name="quick_stock_submit" value="<?php echo (int) $id; ?>">Valider</button>
                              <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="button" data-inline-edit-cancel>Annuler</button>
                            </div>
                          </div>
                        <?php elseif ($hasVariants): ?>
                          <span class="admin-products-price"><?php echo e((string) $displayStock); ?></span>
                          <div class="admin-help">Stock total calcule depuis les variantes actives.</div>
                          <div class="admin-help"><?php echo e($sizeSummary !== '' ? ('Tailles : ' . $sizeSummary) : 'Tailles : -'); ?></div>
                        <?php else: ?>
                          <span class="admin-products-price"><?php echo e((string) $displayStock); ?></span>
                        <?php endif; ?>
                        <div class="admin-products-status" style="margin-top:8px;">
                          <span class="<?php echo e($stockPillClass); ?>"><?php echo e($stockLabel); ?></span>
                        </div>
                      </td>
                      <td>
                        <?php if ($category !== ''): ?>
                          <span class="admin-products-category"><?php echo e($category); ?></span>
                        <?php else: ?>
                          <span class="admin-help">Non classé</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="admin-products-status__stack">
                          <div class="admin-products-status">
                            <span class="admin-status-pill <?php echo $isActive ? 'admin-status-pill--success' : 'admin-status-pill--neutral'; ?>">
                              <?php echo $isActive ? 'Actif' : 'Brouillon'; ?>
                            </span>
                            <?php if ($supports_status): ?>
                              <span class="admin-status-pill <?php echo $pubStatus === 'published' ? 'admin-status-pill--success' : 'admin-status-pill--warning'; ?>">
                                <?php echo $pubStatus === 'published' ? 'Publié' : 'En attente'; ?>
                              </span>
                            <?php endif; ?>
                          </div>
                          <?php if ($isOwner): ?>
                            <input type="hidden" name="active_target[<?php echo (int) $id; ?>]" value="<?php echo $isActive ? '0' : '1'; ?>">
                            <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="submit" name="toggle_active" value="<?php echo (int) $id; ?>">
                              <?php echo $isActive ? 'Brouillon' : 'Actif'; ?>
                            </button>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <?php if ($supports_featured && $isOwner): ?>
                          <form id="<?php echo e($formId); ?>" class="featured-form js-featured-form" data-endpoint="<?php echo e(base_url('public/api/admin/products_featured_update.php')); ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="product_id" value="<?php echo (int) $id; ?>">
                            <div class="admin-products-featured">
                              <label class="featured-toggle">
                                <input type="checkbox" name="is_featured" value="1" <?php echo $isFeatured ? 'checked' : ''; ?>>
                                <span class="sr-only">Produit vedette</span>
                              </label>
                              <span class="admin-status-pill <?php echo $isFeatured ? 'admin-status-pill--info' : 'admin-status-pill--neutral'; ?>">
                                <?php echo $isFeatured ? 'Mis en avant' : 'Standard'; ?>
                              </span>
                            </div>
                          </form>
                        <?php elseif ($supports_featured): ?>
                          <span class="admin-status-pill <?php echo $isFeatured ? 'admin-status-pill--info' : 'admin-status-pill--neutral'; ?>">
                            <?php echo $isFeatured ? 'Mis en avant' : 'Standard'; ?>
                          </span>
                        <?php else: ?>
                          <span class="admin-help">-</span>
                        <?php endif; ?>
                      </td>
                      <td class="admin-col-num">
                        <?php if ($supports_featured && $isOwner): ?>
                          <input
                            class="admin-field admin-products-featured-rank"
                            type="number"
                            name="featured_rank"
                            min="1"
                            placeholder="-"
                            value="<?php echo $featuredRank > 0 ? (int) $featuredRank : ''; ?>"
                            form="<?php echo e($formId); ?>"
                          >
                        <?php elseif ($supports_featured && $featuredRank > 0): ?>
                          <span class="admin-help"><?php echo e((string) $featuredRank); ?></span>
                        <?php else: ?>
                          <span class="admin-help">-</span>
                        <?php endif; ?>
                      </td>
                      <td class="admin-col-num">
                        <div class="admin-products-actions">
                          <?php if ($supports_featured && $isOwner): ?>
                            <button class="btn admin-btn admin-btn--secondary admin-btn--sm js-featured-save" type="button" form="<?php echo e($formId); ?>" data-form-id="<?php echo e($formId); ?>">Enregistrer</button>
                          <?php endif; ?>
                          <?php if ($isOwner): ?>
                            <?php if ($supports_status && $pubStatus === 'pending'): ?>
                              <form method="post" action="<?php echo e(base_url('admin/products/publish.php?id=' . $id)); ?>">
                                <?php echo csrf_field(); ?>
                                <button class="btn admin-btn admin-btn--primary admin-btn--sm" type="submit">Publier</button>
                              </form>
                            <?php endif; ?>
                            <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/products/stock.php?id=' . $id)); ?>">Stock</a>
                            <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/products/edit.php?id=' . $id)); ?>">Modifier</a>
                            <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/products/delete.php?id=' . $id)); ?>">Supprimer</a>
                          <?php else: ?>
                            <a class="btn admin-btn admin-btn--primary admin-btn--sm" href="<?php echo e(base_url('admin/products/stock.php?id=' . $id)); ?>"><?php echo $hasVariants ? 'Voir stock' : 'Ajuster stock'; ?></a>
                          <?php endif; ?>
                        </div>
                        <?php if ($supports_featured && $isOwner): ?>
                          <div class="admin-help admin-products-featured-msg featured-msg js-featured-msg" data-form-id="<?php echo e($formId); ?>"></div>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </form>
          </div>

          <div class="admin-mobile-only admin-products-reveal is-visible" aria-label="Liste produits mobile">
            <?php if ($showBulkPublish && $products): ?>
              <form method="post" action="">
                <?php echo csrf_field(); ?>
                <div class="admin-mobile-card admin-products-mobile-card" style="margin-bottom:14px;">
                  <div class="admin-products-mobile-card__header">
                    <div class="admin-products-mobile-card__identity">
                      <h2 class="admin-products-mobile-card__title">Publication en lot</h2>
                      <div class="admin-products-mobile-card__meta">
                        Sélectionnez les cartes en attente puis publiez-les en une fois.
                        <?php if ($pendingEligibleCount <= 0): ?>
                          Aucun produit éligible sur cette page.
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  <div class="admin-products-mobile-card__actions">
                    <button
                      class="btn admin-btn admin-btn--primary"
                      type="submit"
                      name="bulk_action"
                      value="publish_selected"
                      <?php echo $pendingEligibleCount <= 0 ? 'disabled aria-disabled="true" title="Aucun produit en attente sur cette page."' : ''; ?>
                    >Publier la sélection</button>
                  </div>
                </div>
            <?php endif; ?>

            <?php if (!$products): ?>
              <div class="admin-products-mobile-cards">
                <div class="admin-mobile-card admin-products-mobile-card">
                  <div class="admin-empty-panel admin-products-empty">
                    <?php if ($hasActiveFilters): ?>
                      <p class="admin-empty-panel__title">Aucun produit ne correspond aux filtres appliqués.</p>
                      <p class="admin-empty-panel__text">Ajustez la recherche ou réinitialisez les filtres pour recharger le catalogue.</p>
                      <div class="admin-empty-panel__actions">
                        <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/index.php')); ?>">Réinitialiser</a>
                      </div>
                    <?php else: ?>
                      <p class="admin-empty-panel__title">Aucun produit disponible.</p>
                      <p class="admin-empty-panel__text">Ajoutez un premier produit pour commencer à structurer le catalogue.</p>
                      <div class="admin-empty-panel__actions">
                        <a class="btn admin-btn admin-btn--primary" href="<?php echo e(base_url('admin/products/create.php')); ?>">Ajouter un produit</a>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <div class="admin-products-mobile-cards">
                <?php foreach ($products as $p): ?>
                  <?php
                    $id = (int) ($p['id'] ?? 0);
                    $name = (string) ($p['name'] ?? '');
                    $sku = (string) ($p['sku'] ?? '');
                    $price = (int) ($p['price'] ?? 0);
                    $stock = (int) ($p['stock'] ?? 0);
                    $category = trim((string) ($p['category'] ?? ''));
                    $isActive = (int) ($p['is_active'] ?? 1);
                    $pubStatus = $supports_status ? strtolower(trim((string) ($p['status'] ?? ''))) : '';
                    if ($pubStatus !== 'pending' && $pubStatus !== 'published') $pubStatus = '';
                    $img = (string) ($p['image1'] ?? ($p['image_path'] ?? ($p['image_main'] ?? ($p['image'] ?? ''))));
                    $imgUrl = $img !== '' ? ProductImageService::toUrl($img) : base_url('assets/images/placeholders/product-placeholder.svg');
                    $lowThreshold = (int) ($p['effective_low_stock_threshold'] ?? ($p['low_stock_threshold'] ?? 10));
                    $hasVariants = (int) ($p['uses_variants'] ?? 0) === 1;
                    $sizeSummary = $hasVariants ? implode(', ', (array) ($p['variant_sizes'] ?? array())) : '';
                    $displayStock = (int) ($p['effective_stock'] ?? $stock);
                    $stockBadgeLabel = 'OK stock';
                    $stockBadgeClass = 'admin-status-pill admin-status-pill--success';
                    if ($displayStock <= 0) {
                      $stockBadgeLabel = 'Rupture';
                      $stockBadgeClass = 'admin-status-pill admin-status-pill--danger';
                    } elseif ($lowThreshold > 0 && $displayStock <= $lowThreshold) {
                      $stockBadgeLabel = 'Stock faible';
                      $stockBadgeClass = 'admin-status-pill admin-status-pill--warning';
                    }
                  ?>
                  <article class="admin-mobile-card admin-products-mobile-card">
                    <div class="admin-products-mobile-card__header">
                      <img class="admin-products-mobile-card__thumb" src="<?php echo e($imgUrl); ?>" alt="">
                      <div class="admin-products-mobile-card__identity">
                        <div class="admin-products-mobile-card__topline">
                          <span class="<?php echo e($stockBadgeClass); ?>"><?php echo e($stockBadgeLabel); ?></span>
                          <span class="admin-status-pill <?php echo $isActive ? 'admin-status-pill--success' : 'admin-status-pill--neutral'; ?>">
                            <?php echo $isActive ? 'Actif' : 'Brouillon'; ?>
                          </span>
                          <?php if ($supports_status): ?>
                            <span class="admin-status-pill <?php echo $pubStatus === 'published' ? 'admin-status-pill--success' : 'admin-status-pill--warning'; ?>">
                              <?php echo $pubStatus === 'published' ? 'Publié' : 'En attente'; ?>
                            </span>
                          <?php endif; ?>
                        </div>
                        <h2 class="admin-products-mobile-card__title"><?php echo e($name); ?></h2>
                        <div class="admin-products-mobile-card__meta"><?php echo $sku !== '' ? e($sku) : ('ID #' . e((string) $id)); ?></div>
                        <?php if ($hasVariants): ?>
                          <div class="admin-products-mobile-card__meta">Tailles : <?php echo e($sizeSummary !== '' ? $sizeSummary : '-'); ?></div>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div class="admin-products-mobile-card__grid">
                      <div class="admin-products-mobile-card__field">
                        <span class="admin-products-mobile-card__label">Prix</span>
                        <div class="admin-products-mobile-card__value"><?php echo e((string) $price); ?> FCFA</div>
                      </div>
                      <div class="admin-products-mobile-card__field">
                        <span class="admin-products-mobile-card__label">Stock</span>
                        <div class="admin-products-mobile-card__value"><?php echo e((string) $displayStock); ?></div>
                      </div>
                      <div class="admin-products-mobile-card__field">
                        <span class="admin-products-mobile-card__label">Catégorie</span>
                        <div class="admin-products-mobile-card__value admin-products-mobile-card__value--muted"><?php echo $category !== '' ? e($category) : 'Non classé'; ?></div>
                      </div>
                      <div class="admin-products-mobile-card__field">
                        <span class="admin-products-mobile-card__label">Produit</span>
                        <div class="admin-products-mobile-card__value admin-products-mobile-card__value--muted">ID #<?php echo e((string) $id); ?></div>
                      </div>
                    </div>

                    <div class="admin-products-mobile-card__actions">
                      <?php if ($showBulkPublish && $pubStatus === 'pending'): ?>
                        <label class="admin-mobile-check">
                          <input class="js-bulk-product" type="checkbox" name="bulk_ids[]" value="<?php echo (int) $id; ?>">
                          <span>Sélectionner pour publier</span>
                        </label>
                      <?php endif; ?>

                      <div class="admin-products-mobile-card__actions-secondary">
                        <?php if ($isOwner): ?>
                          <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/stock.php?id=' . $id)); ?>">Stock</a>
                          <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/edit.php?id=' . $id)); ?>">Modifier</a>
                        <?php else: ?>
                          <a class="btn admin-btn admin-btn--primary" href="<?php echo e(base_url('admin/products/stock.php?id=' . $id)); ?>"><?php echo $hasVariants ? 'Voir stock' : 'Ajuster stock'; ?></a>
                        <?php endif; ?>
                      </div>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if ($showBulkPublish && $products): ?>
              </form>
            <?php endif; ?>
          </div>

          <?php if ($lastPage > 1): ?>
            <nav class="admin-pagination" aria-label="Pagination produits">
              <?php
                $qsBase = array();
                if ($q !== '') $qsBase['q'] = $q;
                if ($supports_status && $status !== '') $qsBase['status'] = $status;
              ?>

              <?php if ($page > 1): ?>
                <?php $qs = http_build_query(array_merge($qsBase, array('page' => $page - 1))); ?>
                <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/products/index.php?' . $qs)); ?>">Précédent</a>
              <?php endif; ?>

              <span class="admin-help">Page <?php echo e((string) $page); ?> / <?php echo e((string) $lastPage); ?></span>

              <?php if ($page < $lastPage): ?>
                <?php $qs = http_build_query(array_merge($qsBase, array('page' => $page + 1))); ?>
                <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/products/index.php?' . $qs)); ?>">Suivant</a>
              <?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../_layout_footer.php'; ?>
