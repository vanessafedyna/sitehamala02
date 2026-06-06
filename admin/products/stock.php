<?php
declare(strict_types=1);

/* Ajustement manuel du stock */

require_once __DIR__ . '/../_auth.php';
requireAnyRole(array('owner', 'partner'));
require_once __DIR__ . '/../_flash.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/ProductModel.php';
require_once __DIR__ . '/../../app/services/AdminAuditService.php';
require_once __DIR__ . '/../../app/services/InventorySnapshotService.php';
require_once __DIR__ . '/../../app/services/ProductVariantService.php';
require_once __DIR__ . '/../../app/services/StockMovementService.php';

$page_title = 'Admin - Ajuster stock';
$page_css = 'pages/admin-products.css';
$page_js = '';
$adminRole = admin_current_role();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$errors = array();
$flash = admin_flash_get('product_stock');
$productVariants = array();
$productUsesVariants = false;
$thresholdInput = '10';
$variantDeltaInput = array();
$variantReasonInput = array();
$variantNoteInput = array();

try {
  $pdo = db();
  $model = new ProductModel($pdo);
  $inventory = new InventorySnapshotService($pdo);
  $variantService = new ProductVariantService($pdo);
  $product = $model->find($id);

  if (!$product) {
    http_response_code(404);
  } else {
    $fields = function_exists('db_table_columns') ? db_table_columns($pdo, 'products') : array();
    $supports_threshold = in_array('low_stock_threshold', $fields, true);
    if ($variantService->isSupported()) {
      $productVariants = $variantService->listByProduct($id);
      $productUsesVariants = count($productVariants) > 0;
      if ($productUsesVariants) {
        $product['stock'] = $variantService->calculateActiveStock($id);
      }
    }
    $product = $inventory->hydrateProductRow($product);
    $thresholdInput = (string) ((int) ($product['effective_low_stock_threshold'] ?? ($product['low_stock_threshold'] ?? 10)));

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
      if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expirée. Veuillez réessayer.';
      } elseif ($productUsesVariants) {
        $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
        $thresholdSubmit = isset($_POST['update_threshold_submit']);
        $variantAdjustId = isset($_POST['variant_adjust_submit']) ? (int) $_POST['variant_adjust_submit'] : 0;

        if ($thresholdSubmit && !$errors) {
          $thresholdRaw = trim((string) ($_POST['low_stock_threshold'] ?? ''));
          if ($thresholdRaw !== '') {
            $thresholdInput = $thresholdRaw;
          }
          $thr = $thresholdRaw !== '' ? (int) preg_replace('/[^0-9]/', '', $thresholdRaw) : (int) $thresholdInput;
          if ($supports_threshold && $thr <= 0) {
            $errors[] = 'Le seuil de stock faible doit etre superieur a 0.';
          }

          $oldThreshold = (int) ($product['effective_low_stock_threshold'] ?? ($product['low_stock_threshold'] ?? 10));
          if (!$errors && $oldThreshold !== $thr) {
            $pdo->beginTransaction();
            try {
              $stmt2 = $pdo->prepare('UPDATE products SET low_stock_threshold = :t WHERE id = :id LIMIT 1');
              $stmt2->execute(array('t' => $thr, 'id' => $id));
              AdminAuditService::log($pdo, $adminId, 'inventory_adjusted', 'product', (int) $id, array(
                'actor_role' => $adminRole,
                'old_threshold' => $oldThreshold,
                'new_threshold' => $thr,
                'source' => 'admin_product_variant_threshold',
              ));
              $pdo->commit();
            } catch (Throwable $e) {
              if ($pdo->inTransaction()) $pdo->rollBack();
              $errors[] = 'Erreur lors de la mise a jour du seuil.';
            }
          }

          if (!$errors) {
            admin_flash_set('product_stock', 'success', 'Seuil de stock mis a jour.');
            redirect('admin/products/stock.php?id=' . $id);
          }
        } elseif ($variantAdjustId > 0) {
          $variant = $variantService->findForProduct($id, $variantAdjustId, false);
          $delta = (int) preg_replace('/[^0-9-]/', '', (string) ($_POST['variant_delta'][$variantAdjustId] ?? '0'));
          $reason = trim((string) ($_POST['variant_reason'][$variantAdjustId] ?? 'manual_adjust'));
          $note = trim((string) ($_POST['variant_note'][$variantAdjustId] ?? ''));
          $variantDeltaInput[$variantAdjustId] = (string) $delta;
          $variantReasonInput[$variantAdjustId] = $reason;
          $variantNoteInput[$variantAdjustId] = $note;

          if (!$variant) {
            $errors[] = 'Variante introuvable.';
          }
          if ($delta === 0) {
            $errors[] = 'Quantite invalide (differente de 0).';
          }
          if (!in_array($reason, array('manual_adjust', 'restock', 'correction'), true)) {
            $reason = 'manual_adjust';
          }

          $oldVariantStock = (int) ($variant['stock'] ?? 0);
          $newVariantStock = $oldVariantStock + $delta;
          if (!$errors && $newVariantStock < 0) {
            $errors[] = 'Le stock de la variante ne peut pas devenir negatif.';
          }

          if (!$errors) {
            $oldTotalStock = (int) ($product['effective_stock'] ?? ($product['stock'] ?? 0));
            $variantLabel = trim((string) ($variant['size'] ?? ''));
            $variantColor = trim((string) ($variant['color'] ?? ''));
            if ($variantColor !== '') {
              $variantLabel .= ' / ' . $variantColor;
            }

            $pdo->beginTransaction();
            try {
              $stmt = $pdo->prepare('UPDATE product_variants SET stock = :stock WHERE id = :id AND product_id = :product_id LIMIT 1');
              $stmt->execute(array(
                'stock' => $newVariantStock,
                'id' => $variantAdjustId,
                'product_id' => $id,
              ));

              $newTotalStock = $variantService->calculateActiveStock($id);
              $stmtSync = $pdo->prepare('UPDATE products SET stock = :stock WHERE id = :id LIMIT 1');
              $stmtSync->execute(array('stock' => $newTotalStock, 'id' => $id));

              StockMovementService::record(
                $pdo,
                $id,
                $delta,
                $reason,
                null,
                $adminId,
                trim('Variante ' . $variantLabel . ($note !== '' ? ' | ' . $note : '')),
                array('variant_id' => $variantAdjustId)
              );
              AdminAuditService::log($pdo, $adminId, 'inventory_adjusted', 'product', (int) $id, array(
                'actor_role' => $adminRole,
                'source' => 'admin_product_variant_adjust',
                'variant_id' => $variantAdjustId,
                'variant_label' => $variantLabel,
                'old_variant_stock' => $oldVariantStock,
                'new_variant_stock' => $newVariantStock,
                'old_stock' => $oldTotalStock,
                'new_stock' => $newTotalStock,
              ));

              $pdo->commit();
              admin_flash_set('product_stock', 'success', 'Stock variante mis a jour.');
              redirect('admin/products/stock.php?id=' . $id);
            } catch (Throwable $e) {
              if ($pdo->inTransaction()) $pdo->rollBack();
              $errors[] = 'Erreur lors de la mise a jour de la variante.';
            }
          }
        } else {
          $errors[] = 'Action stock variante invalide.';
        }
      } else {
        $delta = (int) preg_replace('/[^0-9-]/', '', (string) ($_POST['delta'] ?? '0'));
        $reason = trim((string) ($_POST['reason'] ?? 'manual_adjust'));
        $note = trim((string) ($_POST['note'] ?? ''));
        $thresholdRaw = trim((string) ($_POST['low_stock_threshold'] ?? ''));
        if ($thresholdRaw !== '') {
          $thresholdInput = $thresholdRaw;
        }
        $thr = $thresholdRaw !== '' ? (int) preg_replace('/[^0-9]/', '', $thresholdRaw) : (int) $thresholdInput;

        if ($delta === 0) {
          $errors[] = 'Quantité invalide (différent de 0).';
        }
        if (!in_array($reason, array('manual_adjust', 'restock', 'correction'), true)) {
          $reason = 'manual_adjust';
        }
        if ($supports_threshold && $thr <= 0) {
          $errors[] = 'Le seuil de stock faible doit etre superieur a 0.';
        }

        if (!$errors) {
          $oldStock = (int) ($product['stock'] ?? 0);
          $newStock = $oldStock + $delta;
          if ($newStock < 0) {
            $errors[] = 'Le stock ne peut pas devenir négatif.';
          }
        }

        if (!$errors) {
          $pdo->beginTransaction();
          try {
            $stmt = $pdo->prepare('UPDATE products SET stock = stock + :d WHERE id = :id LIMIT 1');
            $stmt->execute(array('d' => $delta, 'id' => $id));

            if ($supports_threshold) {
              $stmt2 = $pdo->prepare('UPDATE products SET low_stock_threshold = :t WHERE id = :id LIMIT 1');
              $stmt2->execute(array('t' => $thr, 'id' => $id));
            }

            $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
            StockMovementService::record($pdo, $id, $delta, $reason, null, $adminId, $note);
            AdminAuditService::log($pdo, $adminId, 'inventory_adjusted', 'product', (int) $id, array(
              'actor_role' => $adminRole,
              'old_stock' => $oldStock,
              'new_stock' => $newStock,
            ));

            $pdo->commit();
            admin_flash_set('product_stock', 'success', 'Stock mis à jour.');
            redirect('admin/products/stock.php?id=' . $id);
          } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Erreur lors de la mise à jour.';
          }
        }
      }
    }

    // Reload
    $product = $model->find($id);
    if ($productUsesVariants && $variantService->isSupported()) {
      $product['stock'] = $variantService->calculateActiveStock($id);
      $productVariants = $variantService->listByProduct($id);
    }
    if ($product) {
      $product = $inventory->hydrateProductRow($product);
      $thresholdInput = (string) ((int) ($product['effective_low_stock_threshold'] ?? ($product['low_stock_threshold'] ?? 10)));
    }
  }
} catch (Throwable $e) {
  $product = null;
  $supports_threshold = false;
  $errors[] = 'Impossible de charger le produit.';
}

require_once __DIR__ . '/../_layout_header.php';
?>

<style>
  .admin-stock-adjust-page {
    display: grid;
    gap: 16px;
    overflow-x: clip;
  }
  .admin-stock-adjust-reveal {
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 220ms ease, transform 220ms ease;
  }
  .admin-stock-adjust-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
  }
  .admin-stock-adjust-page .admin-page-header {
    align-items: flex-start;
    gap: 18px;
  }
  .admin-stock-adjust-page .admin-page-header__actions {
    justify-content: flex-end;
  }
  .admin-stock-adjust-page .admin-alert {
    margin-bottom: 0;
  }
  .admin-stock-adjust-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
  }
  .admin-stock-adjust-meta__chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    max-width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.78);
    color: var(--admin-text-muted);
    font-size: 0.84rem;
    overflow-wrap: anywhere;
  }
  .admin-stock-adjust-meta__chip strong {
    color: var(--admin-ink);
  }
  .admin-stock-adjust-grid {
    display: grid;
    gap: 16px;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    align-items: start;
  }
  .admin-stock-adjust-section {
    display: grid;
    gap: 18px;
  }
  .admin-stock-adjust-section__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
  }
  .admin-stock-adjust-kicker {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    padding: 7px 12px;
    border-radius: 999px;
    background: rgba(31, 122, 79, 0.08);
    color: #28513d;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }
  .admin-stock-adjust-section__title {
    margin: 0;
    color: var(--admin-ink);
    font-size: 1.05rem;
    font-weight: 700;
  }
  .admin-stock-adjust-section__text {
    margin: 6px 0 0;
    color: var(--admin-text-muted);
    line-height: 1.55;
  }
  .admin-stock-adjust-summary {
    display: grid;
    gap: 12px;
  }
  .admin-stock-adjust-summary__item {
    min-width: 0;
    padding: 14px;
    border: 1px solid rgba(31, 122, 79, 0.08);
    border-radius: 16px;
    background: #fbfcfb;
  }
  .admin-stock-adjust-summary__item--current {
    border-color: rgba(31, 122, 79, 0.2);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(246, 250, 247, 0.98));
  }
  .admin-stock-adjust-summary__label {
    display: block;
    margin-bottom: 8px;
    color: var(--admin-text-muted);
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
  }
  .admin-stock-adjust-summary__value {
    color: var(--admin-ink);
    font-size: 1rem;
    font-weight: 700;
    overflow-wrap: anywhere;
  }
  .admin-stock-adjust-summary__value--hero {
    font-size: clamp(2rem, 6vw, 3.1rem);
    line-height: 1;
  }
  .admin-stock-adjust-form {
    display: grid;
    gap: 16px;
  }
  .admin-stock-adjust-fields {
    display: grid;
    gap: 16px;
  }
  .admin-stock-adjust-field {
    min-width: 0;
    display: grid;
    gap: 8px;
  }
  .admin-stock-adjust-field .admin-field,
  .admin-stock-adjust-field .admin-select,
  .admin-stock-adjust-field .admin-textarea {
    width: 100%;
    min-width: 0;
    background-image: none;
  }
  .admin-stock-adjust-field .admin-field:focus,
  .admin-stock-adjust-field .admin-select:focus,
  .admin-stock-adjust-field .admin-textarea:focus,
  .admin-stock-adjust-field .admin-field:focus-visible,
  .admin-stock-adjust-field .admin-select:focus-visible,
  .admin-stock-adjust-field .admin-textarea:focus-visible {
    outline: 0;
    border-color: rgba(31, 122, 79, 0.45);
    box-shadow: 0 0 0 4px rgba(31, 122, 79, 0.12);
  }
  .admin-stock-adjust-quick {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .admin-stock-adjust-quick .admin-btn {
    min-width: 64px;
    justify-content: center;
    background-image: none;
  }
  .admin-stock-adjust-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: flex-start;
    padding-top: 2px;
  }
  .admin-stock-adjust-actions .admin-btn,
  .admin-page-header__actions .admin-btn,
  .admin-stock-adjust-page .admin-btn--primary,
  .admin-stock-adjust-page .admin-btn--secondary {
    background-image: none;
  }
  .admin-stock-adjust-actions .admin-btn:focus-visible,
  .admin-page-header__actions .admin-btn:focus-visible,
  .admin-stock-adjust-quick .admin-btn:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(31, 122, 79, 0.16);
  }
  .admin-stock-adjust-empty {
    padding: 10px 4px;
  }
  @media (max-width: 900px) {
    .admin-stock-adjust-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
  @media (max-width: 768px) {
    .admin-stock-adjust-page .admin-page-header,
    .admin-stock-adjust-page .admin-panel--padded {
      padding: 16px;
    }
  }
  @media (max-width: 430px) {
    .admin-stock-adjust-page .admin-page-header__actions,
    .admin-stock-adjust-actions {
      width: 100%;
    }
    .admin-stock-adjust-page .admin-page-header__actions .admin-btn,
    .admin-stock-adjust-actions .admin-btn {
      width: 100%;
      justify-content: center;
    }
    .admin-stock-adjust-quick .admin-btn {
      flex: 1 1 calc(50% - 8px);
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var revealNodes = Array.prototype.slice.call(document.querySelectorAll('.admin-stock-adjust-reveal'));
    if (revealNodes.length) {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || window.innerWidth <= 768) {
        revealNodes.forEach(function (node) {
          node.classList.add('is-visible');
          node.style.transitionDelay = '0ms';
        });
      } else {
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
      }
    }

    var deltaInput = document.getElementById('delta');
    var currentStockNode = document.querySelector('[data-current-stock]');
    var nextStockNode = document.querySelector('[data-next-stock]');
    var currentStock = parseInt((currentStockNode && currentStockNode.getAttribute('data-current-stock')) || '0', 10);
    if (!deltaInput) return;

    var syncPreview = function () {
      if (!nextStockNode) return;
      var delta = parseInt(deltaInput.value || '0', 10);
      if (isNaN(delta)) delta = 0;
      nextStockNode.textContent = String(currentStock + delta);
    };

    document.querySelectorAll('[data-stock-delta]').forEach(function (button) {
      button.addEventListener('click', function () {
        var change = parseInt(button.getAttribute('data-stock-delta') || '0', 10);
        var current = parseInt(deltaInput.value || '0', 10);
        if (isNaN(current)) current = 0;
        deltaInput.value = String(current + change);
        syncPreview();
        deltaInput.focus();
      });
    });

    deltaInput.addEventListener('input', syncPreview);
    syncPreview();
  });
</script>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-stock-adjust-page">
        <?php
          $name = $product ? (string) ($product['name'] ?? '') : '';
          $sku = $product ? (string) ($product['sku'] ?? '') : '';
          $stock = $product ? (int) ($product['stock'] ?? 0) : 0;
          $thrVal = $product ? (int) ($product['effective_low_stock_threshold'] ?? ($product['low_stock_threshold'] ?? 10)) : 10;
          if ($thrVal <= 0) {
            $thrVal = 10;
          }
          $stockBadgeLabel = 'OK stock';
          $stockBadgeClass = 'admin-status-pill admin-status-pill--success';
          if ($product && $stock <= 0) {
            $stockBadgeLabel = 'Rupture';
            $stockBadgeClass = 'admin-status-pill admin-status-pill--danger';
          } elseif ($product && $stock <= $thrVal) {
            $stockBadgeLabel = 'Stock faible';
            $stockBadgeClass = 'admin-status-pill admin-status-pill--warning';
          }
        ?>

        <div class="admin-page-header admin-stock-adjust-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow">Inventaire</p>
            <h1 class="admin-page-header__title">Ajuster stock</h1>
            <p class="admin-page-header__subtitle">
              <?php echo $product ? e($name !== '' ? $name : ('Produit #' . $id)) : 'Produit introuvable.'; ?>
            </p>
            <?php if ($product): ?>
              <div class="admin-stock-adjust-meta" aria-label="Contexte produit">
                <?php if ($sku !== ''): ?>
                  <span class="admin-stock-adjust-meta__chip"><strong>SKU</strong> <?php echo e($sku); ?></span>
                <?php endif; ?>
                <span class="admin-stock-adjust-meta__chip"><strong><?php echo e((string) $stock); ?></strong> en stock</span>
                <span class="admin-stock-adjust-meta__chip"><strong>Seuil</strong> <?php echo e((string) $thrVal); ?></span>
                <span class="<?php echo e($stockBadgeClass); ?>"><?php echo e($stockBadgeLabel); ?></span>
              </div>
            <?php endif; ?>
          </div>
          <div class="admin-page-header__actions">
            <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/index.php')); ?>">
              <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour aux produits
            </a>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="admin-alert admin-alert--<?php echo e($flash['type']); ?> admin-panel admin-panel--padded admin-stock-adjust-reveal is-visible" role="status" aria-live="polite">
            <strong><?php echo e($flash['message']); ?></strong>
          </div>
        <?php endif; ?>

        <?php if ($errors): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-stock-adjust-reveal is-visible" role="alert">
            <strong>Erreur :</strong>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if (!$product): ?>
          <div class="admin-panel admin-panel--padded admin-empty-panel admin-stock-adjust-empty admin-stock-adjust-reveal is-visible" role="alert">
            <p class="admin-empty-panel__title">Produit introuvable.</p>
            <p class="admin-empty-panel__text">Revenez à la liste produits pour sélectionner un article existant.</p>
            <div class="admin-empty-panel__actions">
              <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/index.php')); ?>">Retour aux produits</a>
            </div>
          </div>
        <?php else: ?>
          <div class="admin-stock-adjust-grid">
            <section class="admin-panel admin-panel--padded admin-stock-adjust-section admin-stock-adjust-reveal" aria-labelledby="stockProductTitle">
              <div class="admin-stock-adjust-section__head">
                <div>
                  <span class="admin-stock-adjust-kicker">Produit</span>
                  <h2 id="stockProductTitle" class="admin-stock-adjust-section__title"><?php echo e($name !== '' ? $name : ('Produit #' . $id)); ?></h2>
                  <p class="admin-stock-adjust-section__text">Vue de contrôle avant enregistrement de l'ajustement.</p>
                </div>
                <span class="<?php echo e($stockBadgeClass); ?>"><?php echo e($stockBadgeLabel); ?></span>
              </div>

              <div class="admin-stock-adjust-summary">
                <?php if ($sku !== ''): ?>
                  <div class="admin-stock-adjust-summary__item">
                    <span class="admin-stock-adjust-summary__label">SKU</span>
                    <div class="admin-stock-adjust-summary__value"><?php echo e($sku); ?></div>
                  </div>
                <?php endif; ?>
                <div class="admin-stock-adjust-summary__item admin-stock-adjust-summary__item--current" data-current-stock="<?php echo e((string) $stock); ?>">
                  <span class="admin-stock-adjust-summary__label">Stock actuel</span>
                  <div class="admin-stock-adjust-summary__value admin-stock-adjust-summary__value--hero"><?php echo e((string) $stock); ?></div>
                </div>
                <div class="admin-stock-adjust-summary__item">
                  <span class="admin-stock-adjust-summary__label">Après ajustement</span>
                  <div class="admin-stock-adjust-summary__value admin-stock-adjust-summary__value--hero" data-next-stock><?php echo e((string) $stock); ?></div>
                </div>
              </div>
            </section>

            <section class="admin-panel admin-panel--padded admin-stock-adjust-section admin-stock-adjust-reveal" aria-labelledby="stockFormTitle">
              <div class="admin-stock-adjust-section__head">
                <div>
                  <span class="admin-stock-adjust-kicker">Ajustement</span>
                  <h2 id="stockFormTitle" class="admin-stock-adjust-section__title">Mouvement de stock</h2>
                  <p class="admin-stock-adjust-section__text"><?php echo $productUsesVariants ? 'Ajustez directement chaque variante depuis cette page pour garder un stock coherent sans ouvrir toute la fiche produit.' : 'Renseignez la variation, la raison et la note interne.'; ?></p>
                </div>
              </div>

              <?php if ($productUsesVariants): ?>
                <div class="admin-stock-adjust-summary" style="gap:14px;">
                  <?php if ($supports_threshold): ?>
                    <form method="post" action="" class="admin-stock-adjust-form" novalidate>
                      <?php echo csrf_field(); ?>
                      <div class="admin-stock-adjust-fields" style="grid-template-columns:minmax(0,1fr) auto; align-items:end;">
                        <div class="admin-stock-adjust-field">
                          <label class="admin-field-label" for="low_stock_threshold_variant">Seuil stock faible</label>
                          <input id="low_stock_threshold_variant" name="low_stock_threshold" type="number" class="admin-field" min="1" step="1" value="<?php echo e($thresholdInput); ?>">
                          <div class="admin-help">Alerte produit quand le total des variantes atteint ce seuil ou moins.</div>
                        </div>
                        <div class="admin-stock-adjust-actions">
                          <button class="btn admin-btn admin-btn--secondary" type="submit" name="update_threshold_submit" value="1">Mettre a jour le seuil</button>
                        </div>
                      </div>
                    </form>
                  <?php endif; ?>

                  <?php foreach ($productVariants as $variantRow): ?>
                    <?php
                      $variantId = (int) ($variantRow['id'] ?? 0);
                      $variantStock = (int) ($variantRow['stock'] ?? 0);
                      $variantSize = (string) ($variantRow['size'] ?? '-');
                      $variantColor = trim((string) ($variantRow['color'] ?? ''));
                      $variantDeltaValue = (string) ($variantDeltaInput[$variantId] ?? '');
                      $variantReasonValue = (string) ($variantReasonInput[$variantId] ?? 'manual_adjust');
                      $variantNoteValue = (string) ($variantNoteInput[$variantId] ?? '');
                    ?>
                    <form method="post" action="" class="admin-stock-adjust-form admin-stock-adjust-summary__item" novalidate>
                      <?php echo csrf_field(); ?>
                      <div class="admin-stock-adjust-section__head">
                        <div>
                          <span class="admin-stock-adjust-kicker">Variante</span>
                          <h3 class="admin-stock-adjust-section__title"><?php echo e($variantSize); ?><?php if ($variantColor !== ''): ?> / <?php echo e($variantColor); ?><?php endif; ?></h3>
                          <p class="admin-stock-adjust-section__text">Stock actuel: <strong><?php echo e((string) $variantStock); ?></strong> <?php echo ((int) ($variantRow['is_active'] ?? 0) === 1) ? '(active)' : '(inactive)'; ?></p>
                        </div>
                      </div>
                      <div class="admin-stock-adjust-fields">
                        <div class="admin-stock-adjust-field">
                          <label class="admin-field-label" for="variant_delta_<?php echo e((string) $variantId); ?>">Ajustement (+ / -)</label>
                          <input id="variant_delta_<?php echo e((string) $variantId); ?>" name="variant_delta[<?php echo e((string) $variantId); ?>]" type="number" class="admin-field" step="1" value="<?php echo e($variantDeltaValue); ?>" placeholder="+5 ou -1">
                        </div>
                        <div class="admin-stock-adjust-field">
                          <label class="admin-field-label" for="variant_reason_<?php echo e((string) $variantId); ?>">Raison</label>
                          <select id="variant_reason_<?php echo e((string) $variantId); ?>" name="variant_reason[<?php echo e((string) $variantId); ?>]" class="admin-field admin-select">
                            <option value="manual_adjust" <?php echo $variantReasonValue === 'manual_adjust' ? 'selected' : ''; ?>>Ajustement manuel</option>
                            <option value="restock" <?php echo $variantReasonValue === 'restock' ? 'selected' : ''; ?>>Reassort</option>
                            <option value="correction" <?php echo $variantReasonValue === 'correction' ? 'selected' : ''; ?>>Correction</option>
                          </select>
                        </div>
                        <div class="admin-stock-adjust-field">
                          <label class="admin-field-label" for="variant_note_<?php echo e((string) $variantId); ?>">Note (optionnel)</label>
                          <textarea id="variant_note_<?php echo e((string) $variantId); ?>" name="variant_note[<?php echo e((string) $variantId); ?>]" class="admin-field admin-textarea" rows="2" maxlength="255" placeholder="Detail interne..."><?php echo e($variantNoteValue); ?></textarea>
                        </div>
                      </div>
                      <div class="admin-stock-adjust-actions">
                        <button class="btn admin-btn admin-btn--primary" type="submit" name="variant_adjust_submit" value="<?php echo e((string) $variantId); ?>">Enregistrer la variante</button>
                        <?php if ($adminRole === 'owner'): ?>
                          <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/edit.php?id=' . $id)); ?>">Modifier toutes les variantes</a>
                        <?php endif; ?>
                      </div>
                    </form>
                  <?php endforeach; ?>
                </div>

                <?php /* Bloc legacy variantes retire: conserve seulement le nouveau rendu ci-dessus. */ ?>
                  <?php if (false): ?>
                  <?php if ($productVariants): ?>
                    <div class="admin-help" style="margin-bottom:12px;">
                      <?php foreach ($productVariants as $variantRow): ?>
                        <div>
                          <?php echo e((string) ($variantRow['size'] ?? '-')); ?>
                          <?php if (trim((string) ($variantRow['color'] ?? '')) !== ''): ?> / <?php echo e((string) ($variantRow['color'] ?? '')); ?><?php endif; ?>
                          : <?php echo e((string) ((int) ($variantRow['stock'] ?? 0))); ?>
                          (<?php echo ((int) ($variantRow['is_active'] ?? 0) === 1) ? 'actif' : 'inactif'; ?>)
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <div class="admin-empty-panel__actions">
                    <?php if ($adminRole === 'owner'): ?>
                      <a class="btn admin-btn admin-btn--primary" href="<?php echo e(base_url('admin/products/edit.php?id=' . $id)); ?>">Modifier les variantes</a>
                    <?php else: ?>
                      <div class="admin-help">Les variantes se modifient depuis la fiche produit, actuellement réservée au propriétaire.</div>
                    <?php endif; ?>
                  </div>
                  <?php endif; ?>
              <?php else: ?>
              <form method="post" action="" class="admin-stock-adjust-form" novalidate>
                <?php echo csrf_field(); ?>

                <div class="admin-stock-adjust-fields">
                  <div class="admin-stock-adjust-field">
                    <label class="admin-field-label" for="delta">Ajustement (+ / -) *</label>
                    <div class="admin-stock-adjust-quick" aria-label="Ajustements rapides">
                      <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="button" data-stock-delta="1">+1</button>
                      <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="button" data-stock-delta="5">+5</button>
                      <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="button" data-stock-delta="10">+10</button>
                      <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="button" data-stock-delta="-1">-1</button>
                      <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="button" data-stock-delta="-5">-5</button>
                    </div>
                    <input id="delta" name="delta" type="number" class="admin-field" step="1" required placeholder="+10 ou -2">
                    <div class="admin-help">Exemple: +10 (restock) / -1 (correction).</div>
                  </div>

                  <div class="admin-stock-adjust-field">
                    <label class="admin-field-label" for="reason">Raison</label>
                    <select id="reason" name="reason" class="admin-field admin-select">
                      <option value="manual_adjust">Ajustement manuel</option>
                      <option value="restock">Réassort</option>
                      <option value="correction">Correction</option>
                    </select>
                  </div>

                  <?php if ($supports_threshold): ?>
                  <div class="admin-stock-adjust-field">
                    <label class="admin-field-label" for="low_stock_threshold">Seuil stock faible</label>
                    <input id="low_stock_threshold" name="low_stock_threshold" type="number" class="admin-field" min="1" step="1" value="<?php echo e($thresholdInput); ?>">
                    <div class="admin-help">Le produit passe en alerte quand le stock atteint ce niveau ou moins.</div>
                  </div>
                  <?php endif; ?>
                  <div class="admin-stock-adjust-field">
                    <label class="admin-field-label" for="note">Note (optionnel)</label>
                    <textarea id="note" name="note" class="admin-field admin-textarea" rows="3" maxlength="255" placeholder="Détail interne..."></textarea>
                  </div>
                </div>

                <div class="admin-stock-adjust-actions">
                  <button class="btn admin-btn admin-btn--primary" type="submit">Enregistrer</button>
                </div>
              </form>
              <?php endif; ?>
            </section>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../_layout_footer.php'; ?>
