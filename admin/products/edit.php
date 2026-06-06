<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
requireRole('owner');
require_once __DIR__ . '/../_flash.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/ProductModel.php';
require_once __DIR__ . '/../../app/models/CategoryModel.php';
require_once __DIR__ . '/../../app/services/ProductVariantService.php';
require_once __DIR__ . '/../../app/services/SkuService.php';
require_once __DIR__ . '/../../app/services/ProductImageService.php';
require_once __DIR__ . '/../../app/services/AdminAuditService.php';
require_once __DIR__ . '/../../app/services/StockMovementService.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$supports_variants = false;

$page_title = 'Admin - Éditer un produit';
$page_css = 'pages/admin-products.css';
$page_js = '';

$product_form_error_intro = 'Le formulaire contient des informations a corriger. Verifiez les champs obligatoires et les valeurs saisies.';
$product_save_error_fallback = "La mise a jour du produit a echoue. Verifiez les champs requis puis reessayez.";
$product_update_success_message = 'Le produit a ete mis a jour avec succes.';

function admin_parse_int($value): int
{
  $v = preg_replace('/[^0-9-]/', '', (string) $value);
  return (int) ($v === '' ? 0 : $v);
}

function admin_strlen(string $s): int
{
  return function_exists('mb_strlen') ? (int) mb_strlen($s) : strlen($s);
}

function admin_normalize_variant_size(string $size): string
{
  $value = strtoupper(trim($size));
  return preg_replace('/\s+/', '', $value) ?: '';
}

function admin_is_allowed_variant_size(string $size): bool
{
  $value = admin_normalize_variant_size($size);
  if ($value === '') {
    return false;
  }

  $allowedTextSizes = array('XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL');
  if (in_array($value, $allowedTextSizes, true)) {
    return true;
  }

  $allowedNumericSizes = array('34', '36', '38', '40', '42', '44', '46');
  return in_array($value, $allowedNumericSizes, true);
}

/**
 * @param mixed $rows
 * @return array<int, array{id:string,size:string,color:string,stock:string,is_active:string}>
 */
function admin_normalize_variant_rows($rows): array
{
  $normalized = array();
  if (!is_array($rows)) {
    return array(array('id' => '', 'size' => '', 'color' => '', 'stock' => '', 'is_active' => '1'));
  }

  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $id = trim((string) ($row['id'] ?? ''));
    $size = trim((string) ($row['size'] ?? ''));
    $color = trim((string) ($row['color'] ?? ''));
    $stock = trim((string) ($row['stock'] ?? ''));
    $isActive = ((string) ($row['is_active'] ?? '1') === '0') ? '0' : '1';
    if ($id === '' && $size === '' && $color === '' && $stock === '') {
      continue;
    }
    $normalized[] = array(
      'id' => $id,
      'size' => $size,
      'color' => $color,
      'stock' => $stock,
      'is_active' => $isActive,
    );
  }

  if (!$normalized) {
    $normalized[] = array('id' => '', 'size' => '', 'color' => '', 'stock' => '', 'is_active' => '1');
  }

  return $normalized;
}

/**
 * @param array<int, array{id:string,size:string,color:string,stock:string,is_active:string}> $rows
 * @return array<int, array{id:int,size:string,color:string,stock:int,is_active:int}>
 */
function admin_validate_variant_rows(array $rows, array &$errors): array
{
  $validated = array();
  $seen = array();

  foreach ($rows as $row) {
    $variantId = (int) ($row['id'] ?? 0);
    $size = trim((string) ($row['size'] ?? ''));
    $color = trim((string) ($row['color'] ?? ''));
    $stockRaw = trim((string) ($row['stock'] ?? ''));
    $isActive = ((string) ($row['is_active'] ?? '1') === '0') ? 0 : 1;

    if ($size === '' && $color === '' && $stockRaw === '') {
      continue;
    }
    if ($size === '') {
      $errors[] = 'La taille est obligatoire pour chaque variante renseignée.';
      continue;
    }
    if (preg_match('/[,;\/|]/', $size)) {
      $errors[] = 'Chaque taille doit etre une variante separee. Ne mettez pas plusieurs tailles dans le meme champ.';
      continue;
    }
    if (!admin_is_allowed_variant_size($size)) {
      $errors[] = 'Taille invalide. Utilisez uniquement XS, S, M, L, XL, XXL, XXXL ou 34, 36, 38, 40, 42, 44, 46.';
      continue;
    }
    if ($stockRaw === '' || !preg_match('/^\d+$/', $stockRaw)) {
      $errors[] = 'Le stock de chaque variante doit être un entier supérieur ou égal à 0.';
      continue;
    }

    $size = admin_normalize_variant_size($size);

    $dupKey = strtolower($size) . '|' . strtolower($color);
    if (isset($seen[$dupKey])) {
      $errors[] = 'Chaque combinaison taille / couleur doit être unique.';
      continue;
    }
    $seen[$dupKey] = true;

    $validated[] = array(
      'id' => $variantId,
      'size' => $size,
      'color' => $color,
      'stock' => (int) $stockRaw,
      'is_active' => $isActive,
    );
  }

  return $validated;
}

/**
 * @param array<int, array<string,mixed>> $rows
 * @return array<int, array<string,mixed>>
 */
function admin_variant_audit_snapshot(array $rows): array
{
  $snapshot = array();
  foreach ($rows as $row) {
    $snapshot[] = array(
      'id' => (int) ($row['id'] ?? 0),
      'size' => trim((string) ($row['size'] ?? '')),
      'color' => trim((string) ($row['color'] ?? '')),
      'stock' => (int) ($row['stock'] ?? 0),
      'is_active' => (int) ($row['is_active'] ?? 0),
    );
  }

  return $snapshot;
}

/* Normalise le filtre de catégorie pour les valeurs issues du formulaire. */
function admin_normalize_category(string $value): string
{
  $v = strtolower(trim($value));
  if ($v === '') return '';

  $v = str_replace(array(' ', '_'), '-', $v);
  $v = preg_replace('/[^a-z0-9-]/', '', $v) ?: '';

  $map = array(
    'robe' => 'robes',
    'robes' => 'robes',
    'chemise' => 'chemises',
    'chemises' => 'chemises',
    'pantalon' => 'pantalons',
    'pantalons' => 'pantalons',
    'veste' => 'vestes',
    'vestes' => 'vestes',
    'chandail' => 'chandails',
    'chandails' => 'chandails',
    't-shirt' => 't-shirts',
    't-shirts' => 't-shirts',
    'tshirt' => 't-shirts',
    'tshirts' => 't-shirts',
    'autre' => 'autres',
    'autres' => 'autres',
  );

  return $map[$v] ?? $v;
}

$errors = array();
$product = null;
$values = array();
$supports_multi = false;
/* Détecte la prise en charge des produits vedettes sur le schéma courant. */
$supports_featured = false;

try {
  $pdo = db();
  $model = new ProductModel($pdo);
  $variantService = new ProductVariantService($pdo);
  $supports_variants = $variantService->isSupported();
  $product = $model->find($id);
  if ($product) {
    $values = array(
      'name' => (string) ($product['name'] ?? ''),
      'sku' => (string) ($product['sku'] ?? ''),
      'price' => (string) ((int) ($product['price'] ?? 0)),
      'description' => (string) ($product['description'] ?? ''),
      'material' => (string) ($product['material'] ?? ''),
      'style' => (string) ($product['style'] ?? ''),
      'occasion' => (string) ($product['occasion'] ?? ''),
      'cut' => (string) ($product['cut'] ?? ''),
      'finishes' => (string) ($product['finishes'] ?? ''),
      'inspiration' => (string) ($product['inspiration'] ?? ''),
      'category' => (string) ($product['category'] ?? ''),
      'gender' => (string) ($product['gender'] ?? 'unisex'),
      'stock' => (string) ((int) ($product['stock'] ?? 0)),
      'is_active' => ((int) ($product['is_active'] ?? 1)) ? '1' : '0',
      'variants' => array(
        array('id' => '', 'size' => '', 'color' => '', 'stock' => '', 'is_active' => '1'),
      ),
     
      'category_ids' => array(),
      /* Préremplit les champs de mise en avant quand ils sont disponibles. */
      'is_featured' => ((int) ($product['is_featured'] ?? 0)) ? '1' : '0',
      'featured_rank' => isset($product['featured_rank']) ? (string) ((int) $product['featured_rank']) : '',
    );
  }

  $fields = function_exists('db_table_columns') ? db_table_columns($pdo, 'products') : array();
  $supports_multi = in_array('image1', $fields, true) && in_array('image2', $fields, true) && in_array('image3', $fields, true);
  /* Active les options "produit vedette" seulement si les colonnes existent. */
  $supports_featured = in_array('is_featured', $fields, true) && in_array('featured_rank', $fields, true);
  if ($product && $supports_variants) {
    $variantRows = $variantService->listByProduct((int) $id);
    if ($variantRows) {
      $values['variants'] = array_map(static function (array $row): array {
        return array(
          'id' => (string) ((int) ($row['id'] ?? 0)),
          'size' => (string) ($row['size'] ?? ''),
          'color' => (string) ($row['color'] ?? ''),
          'stock' => (string) ((int) ($row['stock'] ?? 0)),
          'is_active' => ((int) ($row['is_active'] ?? 1) === 1) ? '1' : '0',
        );
      }, $variantRows);
    }
  }
} catch (Throwable $e) {
  $errors[] = 'Impossible de charger le produit.';
}

/* Collections du formulaire */
$categories_for_form = array();
try {
  $pdoCats = db();
  $cm = new CategoryModel($pdoCats);
  if ($cm->exists()) {
    $categories_for_form = $cm->list(array('is_active' => 1));
    if ($product) {
      $values['category_ids'] = array_map('strval', $cm->getProductCategoryIds((int) $id));
    }
  }
} catch (Throwable $e) {
  $categories_for_form = array();
}

if (!$product) {
  http_response_code(404);
}

if ($product && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $errors[] = 'Session expirée. Veuillez réessayer.';
  } else {
    $values['name'] = trim((string) ($_POST['name'] ?? ''));
    $values['sku'] = strtoupper(trim((string) ($_POST['sku'] ?? '')));
    $values['price'] = trim((string) ($_POST['price'] ?? ''));
    $values['description'] = trim((string) ($_POST['description'] ?? ''));
    $values['material'] = trim((string) ($_POST['material'] ?? ''));
    $values['style'] = trim((string) ($_POST['style'] ?? ''));
    $values['occasion'] = trim((string) ($_POST['occasion'] ?? ''));
    $values['cut'] = trim((string) ($_POST['cut'] ?? ''));
    $values['finishes'] = trim((string) ($_POST['finishes'] ?? ''));
    $values['inspiration'] = trim((string) ($_POST['inspiration'] ?? ''));
    $values['category'] = trim((string) ($_POST['category'] ?? ''));
    /* Uniformise la catégorie saisie avant validation. */
    $values['category'] = admin_normalize_category($values['category']);
    $values['gender'] = strtolower(trim((string) ($_POST['gender'] ?? 'unisex')));
    if (!in_array($values['gender'], array('homme', 'femme', 'unisex'), true)) {
      $values['gender'] = 'unisex';
    }
    $values['stock'] = trim((string) ($_POST['stock'] ?? '0'));
    $values['is_active'] = isset($_POST['is_active']) ? '1' : '0';
    $values['variants'] = admin_normalize_variant_rows($_POST['variants'] ?? array());
   
    $values['category_ids'] = isset($_POST['category_ids']) && is_array($_POST['category_ids']) ? $_POST['category_ids'] : array();
    /* Récupère les champs de mise en avant depuis le formulaire. */
    $values['is_featured'] = isset($_POST['is_featured']) ? '1' : '0';
    $values['featured_rank'] = trim((string) ($_POST['featured_rank'] ?? ''));

    if ($values['name'] === '') {
      $errors[] = 'Le nom est obligatoire.';
    } elseif (admin_strlen($values['name']) > 150) {
      $errors[] = 'Le nom est trop long (max 150).';
    }

    if ($values['sku'] === '') {
      $errors[] = 'Le SKU est obligatoire.';
    } elseif (!SkuService::isValid($values['sku'])) {
      $errors[] = 'Le SKU est invalide (ex: ML-ABC123).';
    } elseif ($model->isSkuTaken($values['sku'], $id)) {
      $errors[] = 'Ce SKU est déjà utilisé.';
    }

    $priceInt = admin_parse_int($values['price']);
    if ($priceInt < 0) {
      $errors[] = 'Le prix est invalide.';
    }

    $stockInt = admin_parse_int($values['stock']);
    if ($stockInt < 0) {
      $errors[] = 'Le stock est invalide.';
    }
    $validatedVariants = admin_validate_variant_rows((array) $values['variants'], $errors);
    if ($validatedVariants) {
      $stockInt = 0;
      foreach ($validatedVariants as $variantRow) {
        if ((int) ($variantRow['is_active'] ?? 0) === 1) {
          $stockInt += (int) $variantRow['stock'];
        }
      }
      $values['stock'] = (string) $stockInt;
    }

    foreach (array('material', 'style', 'occasion', 'cut', 'finishes', 'inspiration') as $attrKey) {
      if (admin_strlen((string) ($values[$attrKey] ?? '')) > 255) {
        $errors[] = 'Le champ "' . $attrKey . '" est trop long (max 255).';
      }
    }

    /* Valide le rang seulement si la mise en avant est activée. */
    $featuredRank = null;
    if ($supports_featured && $values['is_featured'] === '1') {
      $rankStr = trim((string) $values['featured_rank']);
      if ($rankStr !== '') {
        $rankInt = admin_parse_int($rankStr);
        if ($rankInt < 1) {
          $errors[] = 'L’ordre vedette doit être >= 1.';
        } else {
          $featuredRank = $rankInt;
        }
      }
    } else {
      // Si pas vedette => rank NULL (règle métier)
      $featuredRank = null;
      $values['featured_rank'] = '';
    }

    $img1 = $_FILES['image1'] ?? null;
    $img2 = $_FILES['image2'] ?? null;
    $img3 = $_FILES['image3'] ?? null;

    $has1 = $img1 && is_array($img1) && ((int) ($img1['error'] ?? UPLOAD_ERR_NO_FILE)) !== UPLOAD_ERR_NO_FILE;
    $has2 = $img2 && is_array($img2) && ((int) ($img2['error'] ?? UPLOAD_ERR_NO_FILE)) !== UPLOAD_ERR_NO_FILE;
    $has3 = $img3 && is_array($img3) && ((int) ($img3['error'] ?? UPLOAD_ERR_NO_FILE)) !== UPLOAD_ERR_NO_FILE;

    $remove2 = $supports_multi && !$has2 && isset($_POST['remove_image2']);
    $remove3 = $supports_multi && !$has3 && isset($_POST['remove_image3']);

    if (!$supports_multi && ($has2 || $has3)) {
      $errors[] = 'Votre base ne supporte pas encore image2/image3. Exécutez le SQL ALTER TABLE (image1,image2,image3).';
    }

    if (!$errors) {
      $old1 = (string) ($product['image1'] ?? ($product['image_path'] ?? ($product['image_main'] ?? ($product['image'] ?? ''))));
      $old2 = (string) ($product['image2'] ?? '');
      $old3 = (string) ($product['image3'] ?? '');

      $new1 = '';
      $new2 = '';
      $new3 = '';

      $saved = array();
      try {
        $pdo->beginTransaction();
        $variantService = new ProductVariantService($pdo);
        $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
        $hadVariants = $variantService->hasAnyVariants((int) $id);
        $beforeVariants = $hadVariants ? $variantService->listByProduct((int) $id) : array();
        $oldStockEffective = $hadVariants
          ? $variantService->calculateActiveStock((int) $id)
          : (int) ($product['stock'] ?? 0);
        if ($has1) {
          $new1 = ProductImageService::saveUploadedSlot($img1, $id, $values['sku'], 1);
          $saved[] = $new1;
        }
        if ($supports_multi && $has2) {
          $new2 = ProductImageService::saveUploadedSlot($img2, $id, $values['sku'], 2);
          $saved[] = $new2;
        }
        if ($supports_multi && $has3) {
          $new3 = ProductImageService::saveUploadedSlot($img3, $id, $values['sku'], 3);
          $saved[] = $new3;
        }

        $payload = array(
          'name' => $values['name'],
          'sku' => $values['sku'],
          'price' => $priceInt,
          'description' => $values['description'],
          'material' => $values['material'],
          'style' => $values['style'],
          'occasion' => $values['occasion'],
          'cut' => $values['cut'],
          'finishes' => $values['finishes'],
          'inspiration' => $values['inspiration'],
          'category' => $values['category'],
          'gender' => $values['gender'],
          'stock' => $stockInt,
          'is_active' => (int) $values['is_active'],
        );
        if ($new1 !== '') $payload['image1'] = $new1;
        if ($new2 !== '') $payload['image2'] = $new2;
        if ($new3 !== '') $payload['image3'] = $new3;
        if ($remove2) $payload['image2'] = '';
        if ($remove3) $payload['image3'] = '';

        $model->update($id, $payload);

        if ($supports_variants && $variantService->isSupported()) {
          $variantService->replaceForProduct((int) $id, $validatedVariants);
          if ($hadVariants || $validatedVariants) {
            $model->update((int) $id, array(
              'stock' => $variantService->calculateActiveStock((int) $id),
            ));
          }
        }

       
        try {
          $catModel = new CategoryModel($pdo);
          if ($catModel->exists()) {
            $catIds = array_values(array_filter(array_map('intval', (array) ($values['category_ids'] ?? array())), fn ($v) => $v > 0));
            $catModel->setProductCategories((int) $id, $catIds);
          }
        } catch (Throwable $e) {
          // non bloquant
        }

        /* Persiste la mise en avant séparément pour rester compatible avec les anciens schémas. */
        if ($supports_featured) {
          $stmtFeat = $pdo->prepare('UPDATE products SET is_featured = :f, featured_rank = :r WHERE id = :id LIMIT 1');
          $stmtFeat->bindValue(':f', $values['is_featured'] === '1' ? 1 : 0, PDO::PARAM_INT);
          if ($featuredRank === null) {
            $stmtFeat->bindValue(':r', null, PDO::PARAM_NULL);
          } else {
            $stmtFeat->bindValue(':r', $featuredRank, PDO::PARAM_INT);
          }
          $stmtFeat->bindValue(':id', $id, PDO::PARAM_INT);
          $stmtFeat->execute();
        }

        $afterUsesVariants = $supports_variants && $variantService->isSupported() && ($hadVariants || (bool) $validatedVariants);
        $newStockEffective = $afterUsesVariants
          ? $variantService->calculateActiveStock((int) $id)
          : $stockInt;
        $deltaStock = $newStockEffective - $oldStockEffective;
        if ($deltaStock !== 0) {
          StockMovementService::record(
            $pdo,
            (int) $id,
            $deltaStock,
            'manual_adjust',
            null,
            $adminId,
            $afterUsesVariants ? 'Mise a jour du stock via edition produit avec variantes' : 'Mise a jour du stock via edition produit'
          );
        }

        $pdo->commit();

        if ($new1 !== '' && $old1 !== '' && $old1 !== $new1) ProductImageService::deleteIfLocal($old1);
        if ($new2 !== '' && $old2 !== '' && $old2 !== $new2) ProductImageService::deleteIfLocal($old2);
        if ($new3 !== '' && $old3 !== '' && $old3 !== $new3) ProductImageService::deleteIfLocal($old3);
        if ($remove2 && $old2 !== '') ProductImageService::deleteIfLocal($old2);
        if ($remove3 && $old3 !== '') ProductImageService::deleteIfLocal($old3);

        admin_flash_set('products', 'success', $product_update_success_message);
       
        AdminAuditService::log($pdo, $adminId, 'product_updated', 'product', (int) $id, array(
          'actor_role' => admin_current_role(),
          'old_stock' => $oldStockEffective,
          'new_stock' => $newStockEffective,
          'uses_variants_before' => $hadVariants ? 1 : 0,
          'uses_variants_after' => $afterUsesVariants ? 1 : 0,
          'variant_rows_before' => admin_variant_audit_snapshot($beforeVariants),
          'variant_rows_after' => $afterUsesVariants ? admin_variant_audit_snapshot($variantService->listByProduct((int) $id)) : array(),
        ));
        redirect('admin/products/edit.php?id=' . $id);
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        foreach ($saved as $rel) {
          ProductImageService::deleteIfLocal($rel);
        }
        error_log('[admin/products/edit] ' . $e->getMessage());
        $errors[] = $e instanceof RuntimeException ? $e->getMessage() : $product_save_error_fallback;
      }
    }
  }
}

require_once __DIR__ . '/../_layout_header.php';
?>

<link rel="stylesheet" href="<?php echo e(base_url('assets/css/pages/admin-products-edit.css')); ?>">

<script src="<?php echo e(base_url('assets/js/pages/admin-products-edit.js')); ?>"></script>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-product-edit-page">
        <?php if (!$product): ?>
          <div class="admin-page-header admin-product-edit-reveal is-visible">
            <div class="admin-page-header__content">
              <p class="admin-page-header__eyebrow">Catalogue</p>
              <h1 class="admin-page-header__title">Produit introuvable</h1>
              <p class="admin-page-header__subtitle">Impossible de charger la fiche demandée depuis le catalogue.</p>
            </div>
            <div class="admin-page-header__actions">
              <a class="btn admin-btn admin-btn--primary" href="<?php echo e(base_url('admin/products/index.php')); ?>">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour aux produits
              </a>
            </div>
          </div>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-product-edit-reveal is-visible" role="alert">
            <strong>Produit introuvable.</strong>
          </div>
        <?php else: ?>
          <?php
            $editProductName = (string) ($values['name'] ?? ($product['name'] ?? 'Produit'));
            $editProductSku = trim((string) ($values['sku'] ?? ($product['sku'] ?? '')));
            $editActive = ((int) ($values['is_active'] ?? ($product['is_active'] ?? 1))) === 1;
            $editStatusRaw = strtolower(trim((string) ($product['status'] ?? '')));
            $editStatusLabel = '';
            $editStatusClass = 'admin-status-pill admin-status-pill--neutral';
            if ($editStatusRaw === 'published') {
              $editStatusLabel = 'Publié';
              $editStatusClass = 'admin-status-pill admin-status-pill--success';
            } elseif ($editStatusRaw === 'pending') {
              $editStatusLabel = 'En attente';
              $editStatusClass = 'admin-status-pill admin-status-pill--warning';
            }
          ?>

          <div class="admin-page-header admin-product-edit-reveal is-visible">
            <div class="admin-page-header__content">
              <p class="admin-page-header__eyebrow">Catalogue</p>
              <h1 class="admin-page-header__title">Modifier le produit</h1>
              <p class="admin-page-header__subtitle"><?php echo e($editProductName !== '' ? $editProductName : ('Produit #' . $id)); ?></p>
              <div class="admin-product-edit-meta" aria-label="Contexte produit">
                <span class="admin-product-edit-meta__chip"><strong>ID</strong> #<?php echo e((string) $id); ?></span>
                <?php if ($editProductSku !== ''): ?>
                  <span class="admin-product-edit-meta__chip"><strong>SKU</strong> <?php echo e($editProductSku); ?></span>
                <?php endif; ?>
                <span class="admin-status-pill <?php echo $editActive ? 'admin-status-pill--success' : 'admin-status-pill--neutral'; ?>">
                  <?php echo $editActive ? 'Actif' : 'Brouillon'; ?>
                </span>
                <?php if ($editStatusLabel !== ''): ?>
                  <span class="<?php echo e($editStatusClass); ?>"><?php echo e($editStatusLabel); ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="admin-page-header__actions">
              <a class="btn admin-btn admin-btn--primary" href="<?php echo e(base_url('admin/products/index.php')); ?>">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour aux produits
              </a>
              <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">
                <i class="fas fa-gauge-high" aria-hidden="true"></i> Tableau de bord
              </a>
            </div>
          </div>

        <?php if ($errors): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-product-edit-reveal is-visible" role="alert">
            <strong><?php echo e($product_form_error_intro); ?></strong>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php
          $img1 = (string) ($product['image1'] ?? ($product['image_path'] ?? ($product['image_main'] ?? ($product['image'] ?? ''))));
          $img2 = (string) ($product['image2'] ?? '');
          $img3 = (string) ($product['image3'] ?? '');
          $img1Url = $img1 !== '' ? ProductImageService::toUrl($img1) : base_url('assets/images/placeholders/product-placeholder.svg');
          $img2Url = $img2 !== '' ? ProductImageService::toUrl($img2) : base_url('assets/images/placeholders/product-placeholder.svg');
          $img3Url = $img3 !== '' ? ProductImageService::toUrl($img3) : base_url('assets/images/placeholders/product-placeholder.svg');
        ?>

        <div class="admin-panel admin-panel--padded admin-product-edit-section admin-product-edit-reveal" aria-label="Aperçu des images">
          <div class="admin-product-edit-section__head">
            <div>
              <span class="admin-product-edit-kicker">Visuels</span>
              <h2 class="admin-product-edit-section__title">Images actuelles</h2>
              <p class="admin-product-edit-section__text">Aperçu des fichiers en place avant remplacement, sans changer la mécanique d'upload existante.</p>
            </div>
            <div class="admin-product-edit-actions">
              <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/delete.php?id=' . $id)); ?>">Supprimer</a>
            </div>
          </div>

          <div class="admin-product-edit-images">
            <div class="admin-product-edit-image-card admin-product-edit-image-card--primary">
              <span class="admin-product-edit-upload-badge">Image principale</span>
              <img class="admin-product-edit-thumb" src="<?php echo e($img1Url); ?>" alt="">
              <?php if ($img1 !== ''): ?><div class="admin-product-edit-image-path"><?php echo e($img1); ?></div><?php endif; ?>
            </div>
            <div class="admin-product-edit-image-card">
              <span class="admin-product-edit-upload-badge">Image 2</span>
              <img class="admin-product-edit-thumb" src="<?php echo e($img2Url); ?>" alt="">
              <?php if ($img2 !== ''): ?><div class="admin-product-edit-image-path"><?php echo e($img2); ?></div><?php endif; ?>
            </div>
            <div class="admin-product-edit-image-card">
              <span class="admin-product-edit-upload-badge">Image 3</span>
              <img class="admin-product-edit-thumb" src="<?php echo e($img3Url); ?>" alt="">
              <?php if ($img3 !== ''): ?><div class="admin-product-edit-image-path"><?php echo e($img3); ?></div><?php endif; ?>
            </div>
          </div>
        </div>

        <form method="post" action="" class="admin-product-edit-form" enctype="multipart/form-data" novalidate>
            <?php echo csrf_field(); ?>

            <section class="admin-panel admin-panel--padded admin-product-edit-section admin-product-edit-reveal" aria-labelledby="productEditEssentialsTitle">
              <div class="admin-product-edit-section__head">
                <div>
                  <span class="admin-product-edit-kicker">Essentiel</span>
                  <h2 id="productEditEssentialsTitle" class="admin-product-edit-section__title">Informations de base</h2>
                  <p class="admin-product-edit-section__text">Ajustez les informations qui structurent le catalogue et la fiche produit.</p>
                </div>
              </div>

              <div class="admin-product-edit-grid">
                <div class="admin-product-edit-field admin-product-edit-field--full">
                  <label class="admin-field-label" for="name">Nom du produit *</label>
                  <input id="name" name="name" type="text" class="admin-field" required value="<?php echo e($values['name'] ?? ''); ?>" placeholder="Ex. Robe satin midi">
                </div>

                <div class="admin-product-edit-field">
                  <label class="admin-field-label" for="price">Prix (FCFA) *</label>
                  <input id="price" name="price" type="number" class="admin-field" min="0" step="1" required value="<?php echo e($values['price'] ?? '0'); ?>">
                </div>

                <div class="admin-product-edit-field">
                  <label class="admin-field-label" for="stock">Stock disponible *</label>
                  <input id="stock" name="stock" type="number" class="admin-field" min="0" step="1" required value="<?php echo e($values['stock'] ?? '0'); ?>">
                </div>

                <div class="admin-product-edit-field">
                  <label class="admin-field-label" for="category">Catégorie</label>
                  <input id="category" name="category" type="text" class="admin-field" value="<?php echo e($values['category'] ?? ''); ?>" list="categoryList" placeholder="robes, t-shirts, vestes...">
                  <datalist id="categoryList">
                    <option value="robes"></option>
                    <option value="chandails"></option>
                    <option value="pantalons"></option>
                    <option value="chemises"></option>
                    <option value="t-shirts"></option>
                    <option value="vestes"></option>
                    <option value="autres"></option>
                  </datalist>
                  <div class="admin-product-edit-help">Gardez une catégorie simple pour le catalogue.</div>
                </div>

                <div class="admin-product-edit-field">
                  <label class="admin-field-label" for="image1">Image principale</label>
                  <input id="image1" name="image1" type="file" class="admin-field" accept="image/jpeg,image/png,image/webp">
                  <div class="admin-product-edit-help">Remplacez uniquement si vous avez une meilleure photo.</div>
                </div>
              </div>
            </section>

            <section class="admin-panel admin-panel--padded admin-product-edit-section admin-product-edit-reveal" aria-labelledby="productEditDescriptionTitle">
              <div class="admin-product-edit-section__head">
                <div>
                  <span class="admin-product-edit-kicker">Storytelling</span>
                  <h2 id="productEditDescriptionTitle" class="admin-product-edit-section__title">Description produit</h2>
                  <p class="admin-product-edit-section__text">Gardez un texte clair sur la pièce, son usage et sa valeur pour le client.</p>
                </div>
              </div>

              <div class="admin-product-edit-field admin-product-edit-field--full">
                <label class="admin-field-label" for="description">Description</label>
                <textarea id="description" name="description" class="admin-field admin-textarea" rows="5" placeholder="Ex. Matière douce, coupe fluide, idéale pour les sorties et les événements."><?php echo e($values['description'] ?? ''); ?></textarea>
              </div>
            </section>

            <details class="admin-panel admin-panel--padded admin-product-edit-section admin-product-edit-disclosure admin-product-edit-reveal">
              <summary>
                <span>
                  <span class="admin-product-edit-kicker">Avancé</span>
                  <h2 class="admin-product-edit-section__title">Paramètres complémentaires</h2>
                  <p class="admin-product-edit-section__text">SKU, collections, ciblage, images secondaires et attributs marketing.</p>
                </span>
                <i class="fas fa-chevron-down" aria-hidden="true"></i>
              </summary>

              <div class="admin-product-edit-grid">
                <div class="admin-product-edit-field">
                  <label class="admin-field-label" for="sku">SKU *</label>
                  <input id="sku" name="sku" type="text" class="admin-field" required value="<?php echo e($values['sku'] ?? ''); ?>">
                </div>

                <div class="admin-product-edit-field">
                  <label class="admin-field-label" for="gender">Genre</label>
                  <select id="gender" name="gender" class="admin-field admin-select">
                    <option value="unisex" <?php echo ($values['gender'] ?? 'unisex') === 'unisex' ? 'selected' : ''; ?>>Unisexe</option>
                    <option value="homme" <?php echo ($values['gender'] ?? '') === 'homme' ? 'selected' : ''; ?>>Homme</option>
                    <option value="femme" <?php echo ($values['gender'] ?? '') === 'femme' ? 'selected' : ''; ?>>Femme</option>
                  </select>
                </div>

                <?php if ($categories_for_form): ?>
                  <div class="admin-product-edit-field admin-product-edit-field--full">
                    <label class="admin-field-label">Collections</label>
                    <div class="admin-product-edit-help">Cochez les collections où le produit doit apparaître.</div>
                    <div class="admin-product-edit-collections">
                      <div class="admin-product-edit-collections-grid">
                        <?php foreach ($categories_for_form as $catRow): ?>
                          <?php $cid = (int) ($catRow['id'] ?? 0); ?>
                          <label class="featured-toggle admin-product-edit-check">
                            <input
                              type="checkbox"
                              name="category_ids[]"
                              value="<?php echo (int) $cid; ?>"
                              <?php echo in_array((string) $cid, array_map('strval', (array) ($values['category_ids'] ?? array())), true) ? 'checked' : ''; ?>
                            >
                            <?php echo e((string) ($catRow['name'] ?? '')); ?>
                          </label>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>

                <div class="admin-product-edit-field">
                  <label class="admin-field-label" for="material">Matière</label>
                  <input id="material" name="material" type="text" class="admin-field" maxlength="255" value="<?php echo e($values['material'] ?? ''); ?>">
                </div>

                <div class="admin-product-edit-field">
                  <label class="admin-field-label" for="style">Style</label>
                  <input id="style" name="style" type="text" class="admin-field" maxlength="255" value="<?php echo e($values['style'] ?? ''); ?>">
                </div>

                <div class="admin-product-edit-field">
                  <label class="admin-field-label" for="occasion">Occasion</label>
                  <input id="occasion" name="occasion" type="text" class="admin-field" maxlength="255" value="<?php echo e($values['occasion'] ?? ''); ?>">
                </div>

                <div class="admin-product-edit-field">
                  <label class="admin-field-label" for="cut">Coupe / variante</label>
                  <input id="cut" name="cut" type="text" class="admin-field" maxlength="255" value="<?php echo e($values['cut'] ?? ''); ?>">
                </div>

                <?php if ($supports_variants): ?>
                <div class="admin-product-edit-field admin-product-edit-field--full">
                  <label class="admin-field-label">Variantes</label>
                  <div class="admin-product-edit-help">Ajoutez les tailles, couleurs et stocks par variante si besoin.</div>
                  <div class="admin-product-edit-help">Ajoutez une ligne par taille. Exemple : S / Rouge / 5 puis M / Rouge / 10. Ne mettez pas le stock dans le champ taille.</div>
                  <div class="admin-product-edit-help">Une variante inactive n'est pas proposee au client et n'entre pas dans le stock total.</div>
                  <div class="admin-variants" data-variants>
                    <div class="admin-variants__list" data-variants-list>
                      <?php foreach ((array) ($values['variants'] ?? array()) as $variantIndex => $variantRow): ?>
                        <div class="admin-variants__row" data-variant-row>
                          <input type="hidden" data-variant-field="id" name="variants[<?php echo (int) $variantIndex; ?>][id]" value="<?php echo e((string) ($variantRow['id'] ?? '')); ?>">
                          <div>
                            <label class="admin-field-label">Taille</label>
                            <input type="text" class="admin-field" data-variant-field="size" name="variants[<?php echo (int) $variantIndex; ?>][size]" value="<?php echo e((string) ($variantRow['size'] ?? '')); ?>" placeholder="Ex. S">
                          </div>
                          <div>
                            <label class="admin-field-label">Couleur</label>
                            <input type="text" class="admin-field" data-variant-field="color" name="variants[<?php echo (int) $variantIndex; ?>][color]" value="<?php echo e((string) ($variantRow['color'] ?? '')); ?>" placeholder="Ex. Noir">
                          </div>
                          <div>
                            <label class="admin-field-label">Stock</label>
                            <input type="number" min="0" step="1" class="admin-field" data-variant-field="stock" name="variants[<?php echo (int) $variantIndex; ?>][stock]" value="<?php echo e((string) ($variantRow['stock'] ?? '')); ?>" placeholder="0">
                          </div>
                          <div>
                            <label class="admin-field-label">Statut</label>
                            <select class="admin-field admin-select" data-variant-field="is_active" name="variants[<?php echo (int) $variantIndex; ?>][is_active]">
                              <option value="1" <?php echo ((string) ($variantRow['is_active'] ?? '1') === '1') ? 'selected' : ''; ?>>Actif</option>
                              <option value="0" <?php echo ((string) ($variantRow['is_active'] ?? '1') === '0') ? 'selected' : ''; ?>>Inactif</option>
                            </select>
                          </div>
                          <button class="btn admin-btn admin-btn--secondary admin-variants__remove" type="button" data-variant-remove>Supprimer</button>
                        </div>
                      <?php endforeach; ?>
                    </div>
                    <div>
                      <button class="btn admin-btn admin-btn--secondary" type="button" data-variants-add>Ajouter variante</button>
                    </div>
                    <template>
                      <div class="admin-variants__row" data-variant-row>
                        <input type="hidden" data-variant-field="id" value="">
                        <div>
                          <label class="admin-field-label">Taille</label>
                          <input type="text" class="admin-field" data-variant-field="size" value="" placeholder="Ex. S">
                        </div>
                        <div>
                          <label class="admin-field-label">Couleur</label>
                          <input type="text" class="admin-field" data-variant-field="color" value="" placeholder="Ex. Noir">
                        </div>
                        <div>
                          <label class="admin-field-label">Stock</label>
                          <input type="number" min="0" step="1" class="admin-field" data-variant-field="stock" value="" placeholder="0">
                        </div>
                        <div>
                          <label class="admin-field-label">Statut</label>
                          <select class="admin-field admin-select" data-variant-field="is_active">
                            <option value="1" selected>Actif</option>
                            <option value="0">Inactif</option>
                          </select>
                        </div>
                        <button class="btn admin-btn admin-btn--secondary admin-variants__remove" type="button" data-variant-remove>Supprimer</button>
                      </div>
                    </template>
                  </div>
                </div>
                <?php endif; ?>

                <?php if (!$supports_multi): ?>
                  <div class="admin-alert admin-product-edit-field admin-product-edit-field--full" role="status">
                    <strong>Multi-images :</strong> pour activer Image 2 / Image 3, exécutez le SQL ALTER TABLE (image1,image2,image3).
                  </div>
                <?php endif; ?>

                <div class="admin-product-edit-field">
                  <label class="admin-field-label" for="image2">Image 2</label>
                  <input id="image2" name="image2" type="file" class="admin-field" accept="image/jpeg,image/png,image/webp" <?php echo $supports_multi ? '' : 'disabled'; ?>>
                </div>

                <div class="admin-product-edit-field">
                  <label class="admin-field-label" for="image3">Image 3</label>
                  <input id="image3" name="image3" type="file" class="admin-field" accept="image/jpeg,image/png,image/webp" <?php echo $supports_multi ? '' : 'disabled'; ?>>
                </div>

                <?php if ($supports_multi): ?>
                  <div class="admin-product-edit-field">
                    <label class="admin-product-edit-toggle">
                      <input type="checkbox" name="remove_image2" value="1" <?php echo $img2 !== '' ? '' : 'disabled'; ?>>
                      <span><strong>Supprimer l'image 2</strong></span>
                    </label>
                  </div>
                  <div class="admin-product-edit-field">
                    <label class="admin-product-edit-toggle">
                      <input type="checkbox" name="remove_image3" value="1" <?php echo $img3 !== '' ? '' : 'disabled'; ?>>
                      <span><strong>Supprimer l'image 3</strong></span>
                    </label>
                  </div>
                <?php endif; ?>

                <?php if ($supports_featured): ?>
                  <div class="admin-product-edit-field admin-product-edit-field--full">
                    <label class="admin-product-edit-toggle">
                      <input type="checkbox" name="is_featured" value="1" <?php echo ($values['is_featured'] ?? '0') === '1' ? 'checked' : ''; ?>>
                      <span>
                        <strong>Produit vedette</strong><br>
                        <span class="admin-product-edit-help">Conserve le même champ, avec une présentation plus lisible.</span>
                      </span>
                    </label>
                  </div>
                <?php endif; ?>

                <details class="admin-product-marketing admin-product-edit-field admin-product-edit-field--full">
                  <summary class="admin-product-marketing__summary">
                    <span>
                      <strong>Options marketing</strong>
                      <span class="admin-product-edit-help">SEO, finitions et priorité d'affichage vedette.</span>
                    </span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                  </summary>

                  <div class="admin-product-marketing__grid">
                    <div class="admin-product-edit-field">
                      <label class="admin-field-label" for="inspiration">SEO / inspiration</label>
                      <input id="inspiration" name="inspiration" type="text" class="admin-field" maxlength="255" value="<?php echo e($values['inspiration'] ?? ''); ?>">
                    </div>

                    <div class="admin-product-edit-field">
                      <label class="admin-field-label" for="finishes">Finitions</label>
                      <input id="finishes" name="finishes" type="text" class="admin-field" maxlength="255" value="<?php echo e($values['finishes'] ?? ''); ?>">
                    </div>

                    <?php if ($supports_featured): ?>
                      <div class="admin-product-edit-field">
                        <label class="admin-field-label" for="featured_rank">Ordre vedette</label>
                        <input id="featured_rank" name="featured_rank" type="number" class="admin-field" min="1" value="<?php echo e((string) ($values['featured_rank'] ?? '')); ?>" placeholder="1">
                        <div class="admin-product-edit-help">Plus petit = affiche en premier.</div>
                      </div>
                    <?php endif; ?>
                  </div>
                </details>

                <div class="admin-product-edit-field admin-product-edit-field--full">
                  <label class="admin-product-edit-toggle">
                    <input type="checkbox" name="is_active" value="1" <?php echo ($values['is_active'] ?? '1') === '1' ? 'checked' : ''; ?>>
                    <span>
                      <strong>Produit actif</strong><br>
                      <span class="admin-product-edit-help">Conserve le statut actuel au moment de l'enregistrement.</span>
                    </span>
                  </label>
                </div>
              </div>
            </details>

            <div class="admin-product-edit-actions admin-product-edit-reveal is-visible">
              <button class="btn admin-btn admin-btn--primary" type="submit">Enregistrer les modifications</button>
              <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/index.php')); ?>">Annuler</a>
            </div>
          </form>
      <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../_layout_footer.php'; ?>
