<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../_flash.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/ProductModel.php';
require_once __DIR__ . '/../../app/models/CategoryModel.php';
require_once __DIR__ . '/../../app/services/ProductVariantService.php';
require_once __DIR__ . '/../../app/services/SkuService.php';
require_once __DIR__ . '/../../app/services/ProductImageService.php';
require_once __DIR__ . '/../../app/services/AdminAuditService.php';
require_once __DIR__ . '/../../app/services/StockMovementService.php';

$page_title = 'Admin - Créer un produit';
$page_css = 'pages/admin-products.css';
$page_js = '';
$supports_variants = false;
$adminRole = function_exists('admin_current_role') ? admin_current_role() : '';
$isPartner = ($adminRole === 'partner');

$product_form_error_intro = 'Le formulaire contient des informations à corriger. Vérifiez les champs obligatoires et les valeurs saisies.';
$product_save_error_fallback = "L'enregistrement du produit a échoué. Vérifiez les champs requis puis réessayez.";
$product_create_success_message = 'Le produit a été créé avec succès.';

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
 * @param array<int, array{id:int,size:string,color:string,stock:int,is_active:int}> $rows
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

function admin_generate_unique_sku(ProductModel $model, string $prefix = 'ML'): string
{
  for ($i = 0; $i < 20; $i += 1) {
    $sku = SkuService::generate($prefix);
    if (!$model->isSkuTaken($sku)) {
      return $sku;
    }
  }
  throw new RuntimeException('Impossible de générer un SKU unique.');
}

$errors = array();
$values = array(
  'name' => '',
  'sku' => '',
  'price' => '',
  'description' => '',
  'material' => '',
  'style' => '',
  'occasion' => '',
  'cut' => '',
  'finishes' => '',
  'inspiration' => '',
  'category' => '',
  'gender' => 'unisex',
  'stock' => '0',
  'is_active' => '1',
  'variants' => array(
    array('id' => '', 'size' => '', 'color' => '', 'stock' => '', 'is_active' => '1'),
  ),
 
  'category_ids' => array(),
);

$supports_multi = false;
try {
  $pdoProbe = db();
  $fields = function_exists('db_table_columns') ? db_table_columns($pdoProbe, 'products') : array();
  $supports_multi = in_array('image1', $fields, true) && in_array('image2', $fields, true) && in_array('image3', $fields, true);
  $supports_variants = db_table_columns($pdoProbe, 'product_variants') !== array();
} catch (Throwable $e) {
  $supports_multi = false;
  $supports_variants = false;
}

/* Collections du formulaire */
$categories_for_form = array();
try {
  $pdoCats = db();
  $cm = new CategoryModel($pdoCats);
  if ($cm->exists()) {
    $categories_for_form = $cm->list(array('is_active' => 1));
  }
} catch (Throwable $e) {
  $categories_for_form = array();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
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

    if ($values['name'] === '') {
      $errors[] = 'Le nom est obligatoire.';
    } elseif (admin_strlen($values['name']) > 150) {
      $errors[] = 'Le nom est trop long (max 150).';
    }

    if ($isPartner) {
      $values['price'] = '0';
    }

    $priceInt = $isPartner ? 0 : admin_parse_int($values['price']);
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

    if ($values['sku'] !== '' && !SkuService::isValid($values['sku'])) {
      $errors[] = 'Le SKU est invalide (ex: ML-ABC123).';
    }

    foreach (array('material', 'style', 'occasion', 'cut', 'finishes', 'inspiration') as $attrKey) {
      if (admin_strlen((string) ($values[$attrKey] ?? '')) > 255) {
        $errors[] = 'Le champ "' . $attrKey . '" est trop long (max 255).';
      }
    }

    $img1 = $_FILES['image1'] ?? null;
    $img2 = $_FILES['image2'] ?? null;
    $img3 = $_FILES['image3'] ?? null;

    if (!$img1 || !is_array($img1) || ((int) ($img1['error'] ?? UPLOAD_ERR_NO_FILE)) === UPLOAD_ERR_NO_FILE) {
      $errors[] = 'Veuillez ajouter au moins une image (Image 1).';
    }

    if (!$supports_multi) {
      $has2 = $img2 && is_array($img2) && ((int) ($img2['error'] ?? UPLOAD_ERR_NO_FILE)) !== UPLOAD_ERR_NO_FILE;
      $has3 = $img3 && is_array($img3) && ((int) ($img3['error'] ?? UPLOAD_ERR_NO_FILE)) !== UPLOAD_ERR_NO_FILE;
      if ($has2 || $has3) {
        $errors[] = 'Votre base ne supporte pas encore image2/image3. Exécutez le SQL ALTER TABLE (image1,image2,image3).';
      }
    }

    if (!$errors) {
      $pdo = db();
      $model = new ProductModel($pdo);
     
      $catModel = new CategoryModel($pdo);
      $supportsCats = $catModel->exists();

      if ($values['sku'] === '') {
        $values['sku'] = admin_generate_unique_sku($model, 'ML');
      } elseif ($model->isSkuTaken($values['sku'])) {
        $errors[] = 'Ce SKU est déjà utilisé.';
      }

      if (!$errors) {
        $pdo->beginTransaction();
        $saved = array();
        try {
          $variantService = new ProductVariantService($pdo);
          $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
         
          $productStatus = ($adminRole === 'partner') ? 'pending' : 'published';

          $id = $model->create(array(
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
           
            'status' => $productStatus,
          ));

         
          if ($supportsCats) {
            $catIds = array_values(array_filter(array_map('intval', (array) $values['category_ids']), fn ($v) => $v > 0));
            $catModel->setProductCategories((int) $id, $catIds);
          }

          $img1Path = ProductImageService::saveUploadedSlot($img1, $id, $values['sku'], 1);
          $saved[] = $img1Path;

          $payload = array('image1' => $img1Path);

          $img2Path = '';
          if ($supports_multi && $img2 && is_array($img2) && ((int) ($img2['error'] ?? UPLOAD_ERR_NO_FILE)) !== UPLOAD_ERR_NO_FILE) {
            $img2Path = ProductImageService::saveUploadedSlot($img2, $id, $values['sku'], 2);
            $saved[] = $img2Path;
            $payload['image2'] = $img2Path;
          }

          $img3Path = '';
          if ($supports_multi && $img3 && is_array($img3) && ((int) ($img3['error'] ?? UPLOAD_ERR_NO_FILE)) !== UPLOAD_ERR_NO_FILE) {
            $img3Path = ProductImageService::saveUploadedSlot($img3, $id, $values['sku'], 3);
            $saved[] = $img3Path;
            $payload['image3'] = $img3Path;
          }

          // Compat: image1 sera aussi miroir sur image_path via ProductModel::update()
          $model->update($id, $payload);

          if ($supports_variants && $variantService->isSupported()) {
            $variantService->replaceForProduct((int) $id, $validatedVariants);
            if ($validatedVariants) {
              $model->update((int) $id, array(
                'stock' => $variantService->calculateActiveStock((int) $id),
              ));
            }
          }

          $finalStock = $validatedVariants && $variantService->isSupported()
            ? $variantService->calculateActiveStock((int) $id)
            : $stockInt;
          if ($finalStock > 0) {
            StockMovementService::record(
              $pdo,
              (int) $id,
              $finalStock,
              'restock',
              null,
              $adminId,
              $validatedVariants ? 'Stock initial enregistre via creation produit avec variantes' : 'Stock initial enregistre via creation produit'
            );
          }

          $pdo->commit();

         
          $msg = ($productStatus === 'pending' && $adminRole === 'partner')
            ? 'Le produit a été créé avec succès (en attente de publication). Le prix reste à 0 en attente de validation owner.'
            : (($productStatus === 'pending')
              ? 'Le produit a été créé avec succès (en attente de publication).'
              : $product_create_success_message);
          admin_flash_set('products', 'success', $msg);

         
          AdminAuditService::log($pdo, $adminId, 'product_created', 'product', (int) $id, array(
            'actor_role' => $adminRole,
            'old_status' => null,
            'new_status' => $productStatus,
            'old_stock' => 0,
            'new_stock' => $finalStock,
            'uses_variants' => $validatedVariants ? 1 : 0,
            'variant_rows' => $validatedVariants ? admin_variant_audit_snapshot($validatedVariants) : array(),
          ));

         
          if (function_exists('admin_current_role') && admin_current_role() === 'owner') {
            redirect('admin/products/edit.php?id=' . $id);
          }
          redirect('admin/products/index.php');
        } catch (Throwable $e) {
          if ($pdo->inTransaction()) {
            $pdo->rollBack();
          }
          foreach ($saved as $rel) {
            ProductImageService::deleteIfLocal($rel);
          }
          error_log('[admin/products/create] ' . $e->getMessage());
          $errors[] = $e instanceof RuntimeException ? $e->getMessage() : $product_save_error_fallback;
        }
      }
    }
  }
}

require_once __DIR__ . '/../_layout_header.php';
?>

<link rel="stylesheet" href="<?php echo e(base_url('assets/css/pages/admin-products-create.css')); ?>">

<script src="<?php echo e(base_url('assets/js/pages/admin-products-create.js')); ?>"></script>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-product-create-page">
        <div class="admin-page-header admin-product-create-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow">Catalogue</p>
            <h1 class="admin-page-header__title">Créer un produit</h1>
            <p class="admin-page-header__subtitle">Ajoutez une nouvelle fiche produit dans une interface plus nette, plus alignée et plus premium pour le back-office.</p>
            <div class="admin-product-create-meta" aria-label="Repères création produit">
              <span class="admin-product-create-meta__chip"><strong>3</strong> blocs principaux</span>
              <span class="admin-product-create-meta__chip"><strong><?php echo $supports_multi ? '3' : '1'; ?></strong> image<?php echo $supports_multi ? 's' : ''; ?> gérée<?php echo $supports_multi ? 's' : ''; ?></span>
              <span class="admin-product-create-meta__chip"><strong><?php echo e((string) count($errors)); ?></strong> alerte(s) active(s)</span>
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
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-product-create-reveal is-visible" role="alert">
            <strong><?php echo e($product_form_error_intro); ?></strong>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" action="" class="admin-product-create-form" enctype="multipart/form-data" novalidate>
          <?php echo csrf_field(); ?>

          <div class="admin-panel admin-panel--padded admin-product-create-section admin-product-create-reveal" aria-labelledby="productEssentialsTitle">
            <div class="admin-product-create-section__head">
              <div>
                <span class="admin-product-create-kicker">Essentiel</span>
                <h2 id="productEssentialsTitle" class="admin-product-create-section__title">Informations de base</h2>
                <p class="admin-product-create-section__text">Commencez par les informations qui structurent le catalogue et rendent le produit immédiatement exploitable.</p>
              </div>
            </div>

            <div class="admin-product-create-grid">
              <div class="admin-product-create-field admin-product-create-field--full">
                <label class="admin-field-label" for="name">Nom du produit *</label>
                <input id="name" name="name" type="text" class="admin-field" required value="<?php echo e($values['name']); ?>" placeholder="Ex. Robe satin midi">
              </div>

              <?php if ($isPartner): ?>
                <div class="admin-product-create-field">
                  <label class="admin-field-label">Prix</label>
                  <div class="admin-product-create-partner-note">
                    <strong>Tarification propriétaire</strong>
                    <span class="admin-product-create-help">Le prix sera défini par le propriétaire avant publication. Aucun comportement n’est modifié.</span>
                  </div>
                </div>
              <?php else: ?>
                <div class="admin-product-create-field">
                  <label class="admin-field-label" for="price">Prix (FCFA) *</label>
                  <input id="price" name="price" type="number" class="admin-field" min="0" step="1" required value="<?php echo e($values['price']); ?>" placeholder="15000">
                </div>
              <?php endif; ?>

              <div class="admin-product-create-field">
                <label class="admin-field-label" for="stock">Stock disponible *</label>
                <input id="stock" name="stock" type="number" class="admin-field" min="0" step="1" required value="<?php echo e($values['stock']); ?>" placeholder="10">
              </div>

              <div class="admin-product-create-field">
                <label class="admin-field-label" for="category">Catégorie</label>
                <input id="category" name="category" type="text" class="admin-field" value="<?php echo e($values['category']); ?>" list="categoryList" placeholder="robes, t-shirts, vestes...">
                <datalist id="categoryList">
                  <option value="robes"></option>
                  <option value="chandails"></option>
                  <option value="pantalons"></option>
                  <option value="chemises"></option>
                  <option value="t-shirts"></option>
                  <option value="vestes"></option>
                  <option value="autres"></option>
                </datalist>
                <div class="admin-product-create-help">Choisissez une catégorie simple et cohérente pour la navigation catalogue.</div>
              </div>
            </div>
          </div>

          <div class="admin-panel admin-panel--padded admin-product-create-section admin-product-create-reveal" aria-labelledby="productImagesTitle">
            <div class="admin-product-create-section__head">
              <div>
                <span class="admin-product-create-kicker">Visuels</span>
                <h2 id="productImagesTitle" class="admin-product-create-section__title">Images produit</h2>
                <p class="admin-product-create-section__text">Même mécanique d’upload, avec une présentation plus propre et plus lisible pour hiérarchiser les visuels.</p>
              </div>
            </div>

            <?php if (!$supports_multi): ?>
              <div class="admin-alert admin-panel" role="status">
                <strong>Multi-images :</strong> pour activer Image 2 / Image 3, exécutez le SQL ALTER TABLE (image1,image2,image3).
              </div>
            <?php endif; ?>

            <div class="admin-product-create-upload-grid">
              <div class="admin-product-create-upload-card admin-product-create-upload-card--primary">
                <span class="admin-product-create-upload-badge">Image principale</span>
                <div class="admin-product-create-field">
                  <label class="admin-field-label" for="image1">Image 1 *</label>
                  <input id="image1" name="image1" type="file" class="admin-field" accept="image/jpeg,image/png,image/webp" required>
                  <div class="admin-product-create-help">Ajoutez la photo principale du produit. Elle sert de visuel prioritaire dans le catalogue.</div>
                </div>
              </div>

              <div class="admin-product-create-upload-card">
                <span class="admin-product-create-upload-badge">Vue secondaire</span>
                <div class="admin-product-create-field">
                  <label class="admin-field-label" for="image2">Image 2</label>
                  <input id="image2" name="image2" type="file" class="admin-field" accept="image/jpeg,image/png,image/webp" <?php echo $supports_multi ? '' : 'disabled'; ?>>
                  <div class="admin-product-create-help">Ajoutez un angle complémentaire si votre base supporte les images multiples.</div>
                </div>
              </div>

              <div class="admin-product-create-upload-card">
                <span class="admin-product-create-upload-badge">Détail produit</span>
                <div class="admin-product-create-field">
                  <label class="admin-field-label" for="image3">Image 3</label>
                  <input id="image3" name="image3" type="file" class="admin-field" accept="image/jpeg,image/png,image/webp" <?php echo $supports_multi ? '' : 'disabled'; ?>>
                  <div class="admin-product-create-help">Pratique pour un détail matière, une texture ou une vue portée.</div>
                </div>
              </div>
            </div>
          </div>

          <div class="admin-panel admin-panel--padded admin-product-create-section admin-product-create-reveal" aria-labelledby="productDescriptionTitle">
            <div class="admin-product-create-section__head">
              <div>
                <span class="admin-product-create-kicker">Storytelling</span>
                <h2 id="productDescriptionTitle" class="admin-product-create-section__title">Description produit</h2>
                <p class="admin-product-create-section__text">Décrivez clairement la pièce, son usage et ce qui la rend désirable, sans alourdir la fiche.</p>
              </div>
            </div>

            <div class="admin-product-create-field admin-product-create-field--full">
              <label class="admin-field-label" for="description">Description</label>
              <textarea id="description" name="description" class="admin-field admin-textarea" rows="5" placeholder="Ex. Robe légère, coupe fluide, parfaite pour les sorties et les événements."><?php echo e($values['description']); ?></textarea>
            </div>
          </div>

          <details class="admin-panel admin-panel--padded admin-product-create-section admin-product-create-disclosure admin-product-create-reveal">
            <summary>
              <span>
                <span class="admin-product-create-kicker">Avancé</span>
                <h2 class="admin-product-create-section__title">Paramètres complémentaires</h2>
                <p class="admin-product-create-section__text">SKU, collections, ciblage, variantes et attributs marketing. Même structure métier, présentation plus lisible.</p>
              </span>
              <i class="fas fa-chevron-down" aria-hidden="true"></i>
            </summary>

            <div class="admin-product-create-grid">
              <div class="admin-product-create-field">
                <label class="admin-field-label" for="sku">SKU</label>
                <input id="sku" name="sku" type="text" class="admin-field" value="<?php echo e($values['sku']); ?>" placeholder="ML-ABC123">
                <div class="admin-product-create-help">Laissez vide pour générer automatiquement.</div>
              </div>

              <div class="admin-product-create-field">
                <label class="admin-field-label" for="gender">Genre</label>
                <select id="gender" name="gender" class="admin-field admin-select">
                  <option value="unisex" <?php echo ($values['gender'] ?? 'unisex') === 'unisex' ? 'selected' : ''; ?>>Unisexe</option>
                  <option value="homme" <?php echo ($values['gender'] ?? '') === 'homme' ? 'selected' : ''; ?>>Homme</option>
                  <option value="femme" <?php echo ($values['gender'] ?? '') === 'femme' ? 'selected' : ''; ?>>Femme</option>
                </select>
                <div class="admin-product-create-help">Utile pour les regroupements Homme / Femme / Unisexe.</div>
              </div>

              <?php if ($categories_for_form): ?>
                <div class="admin-product-create-field admin-product-create-field--full">
                  <label class="admin-field-label">Collections</label>
                  <div class="admin-product-create-help">Cochez les collections où le produit doit apparaître.</div>
                  <div class="admin-product-create-collections feature-card admin-product-edit-collections">
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

              <div class="admin-product-create-field">
                <label class="admin-field-label" for="material">Matière</label>
                <input id="material" name="material" type="text" class="admin-field" maxlength="255" value="<?php echo e($values['material']); ?>" placeholder="Ex. Satin, coton">
              </div>

              <div class="admin-product-create-field">
                <label class="admin-field-label" for="style">Style</label>
                <input id="style" name="style" type="text" class="admin-field" maxlength="255" value="<?php echo e($values['style']); ?>" placeholder="Ex. Chic, casual">
              </div>

              <div class="admin-product-create-field">
                <label class="admin-field-label" for="occasion">Occasion</label>
                <input id="occasion" name="occasion" type="text" class="admin-field" maxlength="255" value="<?php echo e($values['occasion']); ?>" placeholder="Ex. Soirée, bureau">
              </div>

              <div class="admin-product-create-field">
                <label class="admin-field-label" for="cut">Coupe / variante</label>
                <input id="cut" name="cut" type="text" class="admin-field" maxlength="255" value="<?php echo e($values['cut']); ?>" placeholder="Ex. Ajustée, ample">
              </div>

              <?php if ($supports_variants): ?>
              <div class="admin-product-create-field admin-product-create-field--full">
                <label class="admin-field-label">Variantes</label>
                <div class="admin-product-create-help">Ajoutez les tailles, couleurs et stocks par variante si besoin.</div>
                <div class="admin-product-create-help">Ajoutez une ligne par taille. Exemple : S / Rouge / 5 puis M / Rouge / 10. Ne mettez pas le stock dans le champ taille.</div>
                <div class="admin-product-create-help">Une variante inactive n'est pas proposee au client et n'entre pas dans le stock total.</div>
                <div class="admin-variants" data-variants>
                  <div class="admin-variants__list" data-variants-list>
                    <?php foreach ((array) ($values['variants'] ?? array()) as $variantIndex => $variantRow): ?>
                      <div class="admin-variants__row" data-variant-row>
                        <input type="hidden" data-variant-field="id" name="variants[<?php echo (int) $variantIndex; ?>][id]" value="<?php echo e((string) ($variantRow['id'] ?? '')); ?>">
                        <div class="admin-product-create-field">
                          <label class="admin-field-label">Taille</label>
                          <input type="text" class="admin-field" data-variant-field="size" name="variants[<?php echo (int) $variantIndex; ?>][size]" value="<?php echo e((string) ($variantRow['size'] ?? '')); ?>" placeholder="Ex. S">
                        </div>
                        <div class="admin-product-create-field">
                          <label class="admin-field-label">Couleur</label>
                          <input type="text" class="admin-field" data-variant-field="color" name="variants[<?php echo (int) $variantIndex; ?>][color]" value="<?php echo e((string) ($variantRow['color'] ?? '')); ?>" placeholder="Ex. Noir">
                        </div>
                        <div class="admin-product-create-field">
                          <label class="admin-field-label">Stock</label>
                          <input type="number" min="0" step="1" class="admin-field" data-variant-field="stock" name="variants[<?php echo (int) $variantIndex; ?>][stock]" value="<?php echo e((string) ($variantRow['stock'] ?? '')); ?>" placeholder="0">
                        </div>
                        <div class="admin-product-create-field">
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
                    <button class="btn admin-btn admin-btn--secondary" type="button" data-variants-add>Ajouter une variante</button>
                  </div>
                  <template>
                    <div class="admin-variants__row" data-variant-row>
                      <input type="hidden" data-variant-field="id" value="">
                      <div class="admin-product-create-field">
                        <label class="admin-field-label">Taille</label>
                        <input type="text" class="admin-field" data-variant-field="size" value="" placeholder="Ex. S">
                      </div>
                      <div class="admin-product-create-field">
                        <label class="admin-field-label">Couleur</label>
                        <input type="text" class="admin-field" data-variant-field="color" value="" placeholder="Ex. Noir">
                      </div>
                      <div class="admin-product-create-field">
                        <label class="admin-field-label">Stock</label>
                        <input type="number" min="0" step="1" class="admin-field" data-variant-field="stock" value="" placeholder="0">
                      </div>
                      <div class="admin-product-create-field">
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

              <details class="admin-product-marketing admin-product-create-field admin-product-create-field--full">
                <summary class="admin-product-marketing__summary">
                  <span>
                    <strong>Options marketing</strong>
                    <span class="admin-product-create-help">SEO et finitions, sans changer la logique existante.</span>
                  </span>
                  <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </summary>

                <div class="admin-product-create-grid admin-product-marketing__grid">
                  <div class="admin-product-create-field">
                    <label class="admin-field-label" for="inspiration">SEO / inspiration</label>
                    <input id="inspiration" name="inspiration" type="text" class="admin-field" maxlength="255" value="<?php echo e($values['inspiration']); ?>" placeholder="Ex. Tenue invitée mariage">
                  </div>

                  <div class="admin-product-create-field">
                    <label class="admin-field-label" for="finishes">Finitions</label>
                    <input id="finishes" name="finishes" type="text" class="admin-field" maxlength="255" value="<?php echo e($values['finishes']); ?>" placeholder="Ex. Broderie, boutons nacrés">
                  </div>
                </div>
              </details>

              <div class="admin-product-create-field admin-product-create-field--full">
                <label class="admin-product-create-toggle">
                  <input type="checkbox" name="is_active" value="1" <?php echo $values['is_active'] === '1' ? 'checked' : ''; ?>>
                  <span>
                    <strong>Produit actif dès maintenant</strong><br>
                    <span class="admin-product-create-help">Conserve exactement le même statut à la création, avec une présentation plus claire.</span>
                  </span>
                </label>
              </div>
            </div>
          </details>

          <div class="admin-product-create-actions admin-product-create-reveal is-visible">
            <button class="btn admin-btn admin-btn--primary" type="submit">Publier le produit</button>
            <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/index.php')); ?>">Annuler</a>
          </div>
        </form>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../_layout_footer.php'; ?>
