<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/cart.php';
require_once __DIR__ . '/../app/models/ProductModel.php';
require_once __DIR__ . '/../app/helpers/product_page.php';
require_once __DIR__ . '/../app/services/ProductVariantService.php';

function e($value) {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function product_variant_normalize_size(string $size): string
{
  return ProductVariantService::normalizePublicSize($size);
}

function product_variant_is_allowed_size(string $size): bool
{
  return ProductVariantService::isAllowedPublicSize($size);
}

function product_variant_display_size(string $size): string
{
  if (!product_variant_is_allowed_size($size)) {
    return '';
  }

  return product_variant_normalize_size($size);
}

function product_variant_sort_rank(string $size): int
{
  $value = product_variant_display_size($size);
  $map = array(
    'XS' => 10,
    'S' => 20,
    'M' => 30,
    'L' => 40,
    'XL' => 50,
    'XXL' => 60,
    'XXXL' => 70,
    '34' => 134,
    '36' => 136,
    '38' => 138,
    '40' => 140,
    '42' => 142,
    '44' => 144,
    '46' => 146,
  );

  return $map[$value] ?? 9999;
}

/**
 * @param array<string,mixed> $currentProduct
 * @return array<int,array<string,mixed>>
 */
function fetch_similar_products(PDO $pdo, array $currentProduct, int $limit, string $base_url): array
{
  $limit = max(1, min(12, (int) $limit));
  $currentId = (int) ($currentProduct['id'] ?? 0);
  if ($currentId <= 0) return array();

  $cols = product_page_table_columns($pdo, 'products');
  if (!$cols) return array();

  $whereBase = array('id <> :current_id');
  if (in_array('status', $cols, true)) $whereBase[] = "status = 'published'";
  elseif (in_array('is_active', $cols, true)) $whereBase[] = 'is_active = 1';
  if (in_array('stock', $cols, true)) $whereBase[] = 'stock > 0';
  if (in_array('deleted_at', $cols, true)) $whereBase[] = 'deleted_at IS NULL';
  if (in_array('is_deleted', $cols, true)) $whereBase[] = '(is_deleted = 0 OR is_deleted IS NULL)';

  $orderParts = array();
  if (in_array('featured', $cols, true)) $orderParts[] = 'featured DESC';
  if (in_array('is_featured', $cols, true)) $orderParts[] = 'is_featured DESC';
  if (in_array('created_at', $cols, true)) $orderParts[] = 'created_at DESC';
  $orderParts[] = 'id DESC';
  $orderBy = implode(', ', $orderParts);

  $selected = array();
  $seen = array($currentId => true);

  $run = function (array $extraWhere, array $params, int $take) use ($pdo, $whereBase, $orderBy, &$selected, &$seen): void {
    if ($take <= 0) return;

    $where = array_merge($whereBase, $extraWhere);
    $sql = 'SELECT * FROM products WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy . ' LIMIT ' . (int) $take;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

    foreach ($rows as $row) {
      $id = (int) ($row['id'] ?? 0);
      if ($id <= 0 || isset($seen[$id])) continue;
      $seen[$id] = true;
      $selected[] = $row;
    }
  };

  $paramsBase = array(':current_id' => $currentId);

  // 1) Priorite categorie
  if (in_array('category_id', $cols, true)) {
    $catId = (int) ($currentProduct['category_id'] ?? 0);
    if ($catId > 0) {
      $run(array('category_id = :category_id'), array_merge($paramsBase, array(':category_id' => $catId)), $limit);
    }
  } elseif (in_array('category', $cols, true)) {
    $cat = trim((string) ($currentProduct['category'] ?? ''));
    if ($cat !== '') {
      $run(array('TRIM(category) = :category'), array_merge($paramsBase, array(':category' => $cat)), $limit);
    }
  }

  // 2) Fallback type/style
  if (count($selected) < $limit) {
    $typeCandidates = array('type', 'style', 'subcategory', 'collection', 'gender');
    foreach ($typeCandidates as $field) {
      if (!in_array($field, $cols, true)) continue;
      $value = trim((string) ($currentProduct[$field] ?? ''));
      if ($value === '') continue;
      $remaining = $limit - count($selected);
      if ($remaining <= 0) break;
      $run(array('TRIM(' . $field . ') = :fv'), array_merge($paramsBase, array(':fv' => $value)), $remaining);
      break;
    }
  }

  // 3) Dernier fallback: autres produits actifs/disponibles
  if (count($selected) < $limit) {
    $remaining = $limit - count($selected);
    $run(array(), $paramsBase, $remaining);
  }

  $selected = array_slice($selected, 0, $limit);
  if (!$selected) return array();

  $ratingsByProduct = array();
  try {
    $ids = array_values(array_map(fn ($r) => (int) ($r['id'] ?? 0), $selected));
    $ids = array_values(array_filter($ids, fn ($v) => $v > 0));
    if ($ids) {
      $in = implode(',', array_fill(0, count($ids), '?'));
      $sqlR = "SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS total_reviews
               FROM product_reviews
               WHERE is_approved = 1 AND product_id IN ($in)
               GROUP BY product_id";
      $stmtR = $pdo->prepare($sqlR);
      $stmtR->execute($ids);
      $rowsR = $stmtR->fetchAll(PDO::FETCH_ASSOC) ?: array();
      foreach ($rowsR as $r) {
        $pid = (int) ($r['product_id'] ?? 0);
        if ($pid <= 0) continue;
        $ratingsByProduct[$pid] = array(
          'avg' => (float) ($r['avg_rating'] ?? 0),
          'count' => (int) ($r['total_reviews'] ?? 0),
        );
      }
    }
  } catch (Throwable $e) {
    $ratingsByProduct = array();
  }

  $result = array();
  foreach ($selected as $row) {
    $rid = (int) ($row['id'] ?? 0);
    if ($rid <= 0) continue;

    $rawImage = (string) ($row['image1'] ?? ($row['image_path'] ?? ($row['image_main'] ?? ($row['image'] ?? ''))));
    $imgUrl = product_page_normalize_image_url($rawImage, $base_url);

    $priceValue = (int) ($row['price'] ?? 0);
    $priceLabel = function_exists('format_price') ? format_price($priceValue) : (number_format($priceValue, 0, ',', ' ') . ' FCFA');

    $badges = product_page_card_badges_data($row, 2);
    $showsStockSignal = false;
    foreach ($badges as $badgeRow) {
      $bClass = (string) ($badgeRow['class'] ?? '');
      if ($bClass === 'is-low-stock' || $bClass === 'is-in-stock') {
        $showsStockSignal = true;
        break;
      }
    }
    $stockShort = '';
    if (!$showsStockSignal && array_key_exists('stock', $row)) {
      $st = (int) ($row['stock'] ?? 0);
      $stockShort = $st > 5 ? 'En stock' : ($st > 0 ? 'Stock limite' : 'Rupture');
    }

    $ratingInfo = $ratingsByProduct[$rid] ?? array('avg' => 0.0, 'count' => 0);

    $result[] = array(
      'id' => $rid,
      'name' => (string) ($row['name'] ?? 'Produit'),
      'price' => $priceLabel,
      'image' => $imgUrl !== '' ? $imgUrl : (rtrim((string) $base_url, '/') . '/assets/images/placeholders/product-placeholder.svg'),
      'url' => $base_url . 'pages/produit.php?id=' . $rid,
      'badges' => $badges,
      'stock_short' => $stockShort,
      'avg_rating' => (float) ($ratingInfo['avg'] ?? 0),
      'rating_count' => (int) ($ratingInfo['count'] ?? 0),
    );
  }

  return $result;
}

$csrf_token = csrf_token();

$db_error = '';
$product = null;
$images = array();
$similar_products = array();
$product_reviews = array();
$product_reviews_total = 0;
$product_reviews_avg = 0.0;
$product_variants = array();
$product_has_variants = false;

$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, array(
  'options' => array('min_range' => 1),
));
$sku = isset($_GET['sku']) ? trim((string) $_GET['sku']) : '';
if ($sku !== '' && !preg_match('/^[A-Za-z0-9_-]{2,64}$/', $sku)) {
  $sku = '';
}

try {
  $model = new ProductModel(db());
  if ($sku !== '') {
    $product = $model->getBySku($sku);
  } elseif ($product_id) {
    $product = $model->getById((int) $product_id);
  }

  if ($product) {
    $candidates = array(
      (string) ($product['image1'] ?? ''),
      (string) ($product['image2'] ?? ''),
      (string) ($product['image3'] ?? ''),
      (string) ($product['image_path'] ?? ''),
      (string) ($product['image_main'] ?? ''),
      (string) ($product['image'] ?? ''),
    );

    foreach ($candidates as $raw) {
      $url = product_page_normalize_image_url($raw, $base_url);
      if ($url !== '') $images[] = $url;
    }

    try {
      // Table optionnelle (V1). Si elle n'existe pas, on ignore.
      $pdo = db();
      $stmt_imgs = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = :pid ORDER BY id ASC LIMIT 4');
      $stmt_imgs->execute(array(':pid' => (int) $product['id']));
      foreach ($stmt_imgs->fetchAll() as $row) {
        $url = product_page_normalize_image_url($row['image_path'] ?? '', $base_url);
        if ($url !== '') $images[] = $url;
      }
    } catch (PDOException $e) {
      // Table optionnelle: si elle n'existe pas, on ignore.
    }

    $images = array_values(array_unique($images));
    $images = array_slice($images, 0, 4);
    if (count($images) === 0) {
      $images = array('');
    }

    try {
      $variantService = new ProductVariantService(db());
      if ($variantService->isSupported()) {
        $rawProductVariants = $variantService->listByProduct((int) $product['id'], true, true);
        $product_variants = array_values(array_filter($rawProductVariants, static function (array $variantRow) use ($variantService): bool {
          return $variantService->isPurchasableVariant($variantRow);
        }));
        $product_has_variants = count($product_variants) > 0;
      }
    } catch (Throwable $e) {
      $product_variants = array();
      $product_has_variants = false;
    }

    try {
      $similar_products = fetch_similar_products(db(), $product, 4, $base_url);
    } catch (Throwable $e) {
      $similar_products = array();
    }

    try {
      $pdo = db();

      $stmtAvg = $pdo->prepare(
        'SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews
         FROM product_reviews
         WHERE product_id = :pid AND is_approved = 1'
      );
      $stmtAvg->execute(array(':pid' => (int) $product['id']));
      $avgRow = $stmtAvg->fetch(PDO::FETCH_ASSOC) ?: array();
      $product_reviews_total = (int) ($avgRow['total_reviews'] ?? 0);
      $product_reviews_avg = (float) ($avgRow['avg_rating'] ?? 0);

      $stmtReviews = $pdo->prepare(
        'SELECT customer_name, customer_city, rating, comment, created_at
         FROM product_reviews
         WHERE product_id = :pid AND is_approved = 1
         ORDER BY created_at DESC
         LIMIT 6'
      );
      $stmtReviews->execute(array(':pid' => (int) $product['id']));
      $product_reviews = $stmtReviews->fetchAll(PDO::FETCH_ASSOC) ?: array();
    } catch (Throwable $e) {
      $product_reviews = array();
      $product_reviews_total = 0;
      $product_reviews_avg = 0.0;
    }
  }
} catch (Throwable $e) {
  $db_error = 'Connexion a la base de donnees impossible pour le moment. Verifiez la configuration dans config.php.';
  if (defined('DEBUG_MODE') && DEBUG_MODE) {
    $db_error_details = $e->getMessage();
  }
}

// Ajout au panier (V1: qty = 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
  $posted_token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
  $posted_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT, array(
    'options' => array('min_range' => 1),
  ));
  $posted_variant_id = filter_input(INPUT_POST, 'variant_id', FILTER_VALIDATE_INT, array(
    'options' => array('min_range' => 1),
  ));

    if (!$product || !$posted_id || (int) $product['id'] !== (int) $posted_id) {
        header('Location: ' . $base_url . 'pages/catalogue.php');
        exit;
    }

    if (!hash_equals($csrf_token, $posted_token)) {
        header('Location: ' . $base_url . 'pages/produit.php?id=' . (int) $product['id']);
        exit;
    }

  $stock = (int) ($product['stock'] ?? 0);
    if ($stock <= 0) {
        header('Location: ' . $base_url . 'pages/produit.php?id=' . (int) $product['id']);
        exit;
    }

  if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
  }
  $_SESSION['cart'] = cart_normalize_map($_SESSION['cart']);

  if ($product_has_variants) {
    if (!$posted_variant_id) {
      header('Location: ' . $base_url . 'pages/produit.php?id=' . (int) $product['id']);
      exit;
    }

    $variantService = new ProductVariantService(db());
    $variant = $variantService->findForProduct((int) $product['id'], (int) $posted_variant_id, true);
    if (!$variant || !$variantService->isPurchasableVariant($variant)) {
      header('Location: ' . $base_url . 'pages/produit.php?id=' . (int) $product['id']);
      exit;
    }

    $lineKey = cart_item_key((int) $product['id'], (int) $posted_variant_id);
    $current = isset($_SESSION['cart'][$lineKey]) ? (int) $_SESSION['cart'][$lineKey] : 0;
    $next = min($current + 1, (int) $variant['stock']);
    $_SESSION['cart'][$lineKey] = $next;
  } else {
    $lineKey = cart_item_key((int) $product['id'], null);
    $current = isset($_SESSION['cart'][$lineKey]) ? (int) $_SESSION['cart'][$lineKey] : 0;
    $next = min($current + 1, $stock);
    $_SESSION['cart'][$lineKey] = $next;
  }

    header('Location: ' . $base_url . 'pages/panier.php');
    exit;
}

$page_title = $product ? (string) $product['name'] : 'Produit';
$page_css = 'pages/produit.css';
$page_js = 'pages/produit.js';

/* SEO de la page produit */
if ($product) {
  $page_seo_title = trim((string) ($product['seo_title'] ?? ''));
  $meta = trim((string) ($product['seo_description'] ?? ''));
  if ($meta === '') {
    $meta = trim((string) ($product['description'] ?? ''));
  }
  if ($meta === '') {
    $name_meta = trim((string) ($product['name'] ?? 'Produit'));
    $cat_meta = trim((string) ($product['category'] ?? ''));
    $meta = $name_meta;
    if ($cat_meta !== '') {
      $meta .= ' - ' . $cat_meta;
    }
    $meta .= ' disponible chez SORA Collection, commande en ligne avec livraison locale au Mali.';
  }
  if ($meta !== '') {
    $page_meta_description = (function_exists('mb_substr') ? mb_substr($meta, 0, 255) : substr($meta, 0, 255));
  }

  $og = trim((string) ($product['og_image'] ?? ''));
  if ($og === '') {
    $og = (string) ($product['image1'] ?? ($product['image_path'] ?? ($product['image_main'] ?? ($product['image'] ?? ''))));
  }
  $page_og_image = $og !== '' ? product_page_normalize_image_url($og, $base_url) : '';

  $product_url = absolute_url('pages/produit.php' . ($sku !== '' ? ('?sku=' . rawurlencode($sku)) : ('?id=' . (int) ($product['id'] ?? 0))));
  $product_image = trim((string) $page_og_image);
  if ($product_image === '' && !empty($images[0])) {
    $product_image = trim((string) $images[0]);
  }
  if ($product_image !== '' && !preg_match('#^https?://#i', $product_image)) {
    $product_image = absolute_url(ltrim($product_image, '/'));
  }

  $product_description_ld = trim((string) ($product['description'] ?? ''));
  if ($product_description_ld === '') {
    $product_description_ld = trim((string) ($page_meta_description ?? ''));
  }
  if ($product_description_ld !== '') {
    $product_description_ld = preg_replace('/\s+/u', ' ', strip_tags($product_description_ld)) ?: '';
    $product_description_ld = trim((string) $product_description_ld);
  }

  $price_ld = (int) ($product['price'] ?? 0);
  $availability_ld = ((int) ($product['stock'] ?? 0) > 0)
    ? 'https://schema.org/InStock'
    : 'https://schema.org/OutOfStock';

  $page_json_ld_product = array(
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => trim((string) ($product['name'] ?? '')),
    'url' => $product_url,
    'sku' => trim((string) ($product['sku'] ?? '')),
    'category' => trim((string) ($product['category'] ?? '')),
    'description' => $product_description_ld,
    'image' => $product_image,
    'offers' => array(
      '@type' => 'Offer',
      'url' => $product_url,
      'priceCurrency' => 'XOF',
      'price' => (string) $price_ld,
      'availability' => $availability_ld,
      'itemCondition' => 'https://schema.org/NewCondition',
    ),
  );

  if ($product_reviews_total > 0 && $product_reviews_avg > 0) {
    $page_json_ld_product['aggregateRating'] = array(
      '@type' => 'AggregateRating',
      'ratingValue' => (string) round((float) $product_reviews_avg, 1),
      'reviewCount' => (int) $product_reviews_total,
    );
  }

  // Nettoyage des champs vides pour eviter du JSON-LD bruit.
  foreach (array('name', 'sku', 'category', 'description', 'image') as $k) {
    if (!isset($page_json_ld_product[$k])) continue;
    if (trim((string) $page_json_ld_product[$k]) === '') {
      unset($page_json_ld_product[$k]);
    }
  }
}

include __DIR__ . '/../includes/header.php';

?>

<main id="main">

<section class="breadcrumb">
  <div class="container">
    <nav aria-label="breadcrumb">
      <ol>
                <li><a href="<?php echo e($base_url); ?>index.php">Accueil</a></li>
                <li><a href="<?php echo e($base_url); ?>pages/catalogue.php">Catalogue</a></li>
        <li class="active"><?php echo $product ? e($product['name']) : 'Produit introuvable'; ?></li>
      </ol>
    </nav>
  </div>
</section>

<section class="product-page">
  <div class="container">
    <?php if ($db_error): ?>
      <div class="notice notice--error" role="alert">
        <?php echo e($db_error); ?>
      </div>
      <?php if (isset($db_error_details) && defined('DEBUG_MODE') && DEBUG_MODE): ?>
        <details class="notice notice--info">
          <summary>Details (dev)</summary>
          <pre style="white-space: pre-wrap; margin: 10px 0 0;"><?php echo e($db_error_details); ?></pre>
        </details>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!$product): ?>
      <div class="product-not-found">
        <h1>Produit introuvable</h1>
        <p>Ce produit n'existe pas ou n'est plus disponible.</p>
                <a class="btn btn-secondary" href="<?php echo e($base_url); ?>pages/catalogue.php">Retour au catalogue</a>
      </div>
    <?php else: ?>
      <?php
        $stock = (int) ($product['stock'] ?? 0);
        list($stock_label, $stock_class) = product_page_stock_badge($stock);
        $price = isset($product['price']) ? (int) $product['price'] : 0;
        $price_display = function_exists('format_price') ? format_price($price) : (number_format($price, 0, ',', ' ') . ' FCFA');
        $main_src = $images[0] ?? '';
        $rating_value = $product_reviews_total > 0 ? $product_reviews_avg : 0.0;
        $rating_count = $product_reviews_total;
        $product_badges = product_page_badges_data($product, 2);
        $description_text = trim((string) ($product['description'] ?? ''));
        if ($description_text === '') {
          $description_text = 'Piece pensee pour un style quotidien elegant, confortable et facile a porter.';
        }
        $description_paragraphs = preg_split('/\r\n|\r|\n/', $description_text) ?: array();
        $description_paragraphs = array_values(array_filter(array_map('trim', $description_paragraphs), fn ($p) => $p !== ''));
        if (!$description_paragraphs) {
          $description_paragraphs = array($description_text);
        }

        $variant_selection_error = 'Veuillez choisir une taille.';
        $variant_options = array();
        $variant_default_color = '';
        foreach ($product_variants as $variantRow) {
          $variantId = (int) ($variantRow['id'] ?? 0);
          $variantSize = product_variant_display_size((string) ($variantRow['size'] ?? ''));
          if ($variantId <= 0 || $variantSize === '') {
            continue;
          }

          $variantColor = trim((string) ($variantRow['color'] ?? ''));
          $variant_options[] = array(
            'id' => $variantId,
            'size' => $variantSize,
            'color' => $variantColor,
          );
        }

        usort($variant_options, static function (array $a, array $b): int {
          $rankA = product_variant_sort_rank((string) ($a['size'] ?? ''));
          $rankB = product_variant_sort_rank((string) ($b['size'] ?? ''));
          if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
          }

          return strcmp((string) ($a['size'] ?? ''), (string) ($b['size'] ?? ''));
        });

        if (count($variant_options) > 0) {
          $firstVariant = $variant_options[0];
          $variant_default_color = trim((string) ($firstVariant['color'] ?? ''));
        } else {
          $product_has_variants = false;
          $variant_default_color = '';
        }

        $product_details = array();
        $material = trim((string) ($product['material'] ?? ''));
        $style = trim((string) ($product['style'] ?? ''));
        $occasion = trim((string) ($product['occasion'] ?? ''));
        $cut = trim((string) ($product['cut'] ?? ''));
        $finishes = trim((string) ($product['finishes'] ?? ''));
        $inspiration = trim((string) ($product['inspiration'] ?? ''));

        if ($material !== '') $product_details['Matiere / tissu'] = $material;
        if ($style !== '') $product_details['Style'] = $style;
        if ($occasion !== '') $product_details['Occasion'] = $occasion;
        if ($cut !== '') $product_details['Coupe'] = $cut;
        if ($finishes !== '') $product_details['Finitions'] = $finishes;
        if ($inspiration !== '') $product_details['Inspiration'] = $inspiration;
      ?>

      <?php if (isset($_GET['added']) && $_GET['added'] === '1'): ?>
        <div class="notice notice--success" role="status">
          Ajoute au panier.
        </div>
      <?php endif; ?>

      <div class="product-grid">
        <div class="product-gallery" aria-label="Galerie produit">
          <div class="product-main">
            <?php if ($main_src !== ''): ?>
              <img id="productMainImage" src="<?php echo e($main_src); ?>" alt="<?php echo e($product['name']); ?>">
            <?php else: ?>
              <div class="image-placeholder" id="productMainPlaceholder" aria-label="Aucune image disponible">
                <i class="fas fa-image" aria-hidden="true"></i>
                <span>Image indisponible</span>
              </div>
            <?php endif; ?>
          </div>

          <div class="product-thumbs" role="list" aria-label="Mini galerie">
            <?php foreach ($images as $idx => $src): ?>
              <button
                type="button"
                class="thumb <?php echo $idx === 0 ? 'is-active' : ''; ?>"
                data-src="<?php echo e($src); ?>"
                aria-current="<?php echo $idx === 0 ? 'true' : 'false'; ?>"
                aria-label="Voir l'image <?php echo (int) ($idx + 1); ?>"
                role="listitem"
              >
                <?php if ($src !== ''): ?>
                  <img src="<?php echo e($src); ?>" alt="" loading="lazy" decoding="async">
                <?php else: ?>
                  <span class="thumb__placeholder" aria-hidden="true">
                    <i class="fas fa-image"></i>
                  </span>
                <?php endif; ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="product-info">
          <h1 class="product-title"><?php echo e($product['name']); ?></h1>
          <div class="product-meta">
            <span class="product-sku">SKU: <?php echo e($product['sku']); ?></span>
          </div>
          <?php if (!empty($product_badges)): ?>
            <div class="product-key-badges" aria-label="Informations produit">
              <?php foreach ($product_badges as $badge): ?>
                <span class="product-key-badge <?php echo e((string) ($badge['class'] ?? '')); ?>">
                  <?php echo e((string) ($badge['label'] ?? '')); ?>
                </span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="product-rating" aria-label="Evaluation produit">
            <?php if ($rating_count > 0): ?>
              <span class="rating-stars" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
              <span class="rating-score"><?php echo e(number_format($rating_value, 1, '.', '')); ?></span>
              <span class="rating-count">(<?php echo (int) $rating_count; ?> avis)</span>
            <?php else: ?>
              <span class="rating-new">Nouveau produit</span>
            <?php endif; ?>
          </div>

          <div class="product-buy-panel" aria-label="Achat produit">
            <div class="product-price-wrap">
              <span class="product-price-label">Prix</span>
              <div class="product-price"><?php echo e($price_display); ?></div>
            </div>

            <div class="product-availability">
              <span class="availability-label">Disponibilite</span>
              <div class="badge-stock <?php echo e($stock_class); ?>" aria-label="<?php echo e($stock_label); ?>">
                <?php echo e($stock_label); ?>
              </div>
            </div>

            <div class="product-actions">
              <form method="post" class="add-to-cart-form<?php echo $product_has_variants ? ' add-to-cart-form--has-variants' : ''; ?>"<?php echo $product_has_variants ? ' data-variant-form data-variant-message="' . e($variant_selection_error) . '"' : ''; ?>>
                <input type="hidden" name="action" value="add_to_cart">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                <?php if ($product_has_variants): ?>
                  <div class="product-variant-picker" data-variant-picker>
                    <div class="product-variant-picker__head">
                      <label class="product-variant-picker__title" for="productVariant">Choisir une taille</label>
                    </div>
                    <input type="hidden" id="productVariant" name="variant_id" value="" data-variant-input>
                    <div class="product-variant-picker__buttons" role="list" aria-label="Choisir une taille">
                      <?php foreach ($variant_options as $variantOption): ?>
                        <button
                          type="button"
                          class="product-variant-btn"
                          data-variant-option
                          data-variant-id="<?php echo (int) $variantOption['id']; ?>"
                          data-variant-size="<?php echo e((string) $variantOption['size']); ?>"
                          data-variant-color="<?php echo e((string) $variantOption['color']); ?>"
                          aria-pressed="false"
                          role="listitem"
                        >
                          <span><?php echo e((string) $variantOption['size']); ?></span>
                        </button>
                      <?php endforeach; ?>
                    </div>
                    <p class="product-variant-picker__meta is-hidden" data-variant-color-meta>
                      Couleur : <span><?php echo e($variant_default_color); ?></span>
                    </p>
                    <p class="product-variant-picker__error is-hidden" data-variant-error role="alert" aria-live="polite">
                      <?php echo e($variant_selection_error); ?>
                    </p>
                  </div>
                <?php endif; ?>
                <button class="btn btn-primary btn-buy-primary" type="submit" <?php echo ($stock <= 0 || $product_has_variants) ? 'disabled aria-disabled="true"' : ''; ?>>
                  <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                  <?php echo $stock <= 0 ? 'Indisponible' : 'Ajouter au panier'; ?>
                </button>
              </form>

              <a class="btn btn-secondary" href="<?php echo e($base_url); ?>pages/catalogue.php">
                Retour au catalogue
              </a>
            </div>

            <p class="product-delivery-note">
              <i class="fas fa-truck" aria-hidden="true"></i>
              Livraison locale et confirmation de commande par notre équipe.
            </p>

            
          </div>
        </div>
      </div>

      <div class="product-sections">
        <section class="section-card section-card--description">
          <h2>Description detaillee</h2>
          <div class="description-layout">
            <div class="description-content">
              <p class="description-kicker">Description</p>
              <?php foreach ($description_paragraphs as $paragraph): ?>
                <p><?php echo e($paragraph); ?></p>
              <?php endforeach; ?>
            </div>

            <?php if (!empty($product_details)): ?>
              <div class="description-details">
                <p class="description-kicker">Caracteristiques</p>
                <dl class="product-details-list" aria-label="Details produit">
                  <?php foreach ($product_details as $label => $value): ?>
                    <div class="detail-row">
                      <dt><?php echo e($label); ?></dt>
                      <dd><?php echo e($value); ?></dd>
                    </div>
                  <?php endforeach; ?>
                </dl>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <section class="section-card section-card--reviews">
          <h2>Avis clients</h2>
          <?php if (!empty($product_reviews)): ?>
            <div class="reviews-grid">
              <?php foreach ($product_reviews as $review): ?>
                <?php
                  $r = (int) ($review['rating'] ?? 0);
                  if ($r < 1) $r = 1;
                  if ($r > 5) $r = 5;
                  $stars = str_repeat('★', $r) . str_repeat('☆', 5 - $r);
                  $author = trim((string) ($review['customer_name'] ?? 'Client'));
                  $city = trim((string) ($review['customer_city'] ?? ''));
                ?>
                <article class="review-card">
                  <div class="review-rating" aria-label="Note client"><?php echo e($stars); ?></div>
                  <p class="review-text"><?php echo e((string) ($review['comment'] ?? '')); ?></p>
                  <p class="review-author">
                    <?php echo e($author); ?><?php echo $city !== '' ? ' — ' . e($city) : ''; ?>
                  </p>
                </article>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="reviews-empty">Pas encore d'avis pour ce produit.</p>
          <?php endif; ?>

          <div class="review-form-wrap">
            <h3>Laisser un avis</h3>
            <form id="productReviewForm" class="review-form" data-endpoint="<?php echo e(base_url('public/api/product_reviews_create.php')); ?>">
              <input type="hidden" name="_csrf" value="<?php echo e($csrf_token); ?>">
              <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">

              <div class="review-form-grid">
                <div class="field">
                  <label for="prName">Nom</label>
                  <input id="prName" name="customer_name" type="text" maxlength="100" required>
                </div>
                <div class="field">
                  <label for="prCity">Ville</label>
                  <input id="prCity" name="customer_city" type="text" maxlength="100">
                </div>
                <div class="field">
                  <label for="prRating">Note</label>
                  <select id="prRating" name="rating" required>
                    <option value="">Choisir</option>
                    <option value="5">5 - Excellent</option>
                    <option value="4">4 - Tres bien</option>
                    <option value="3">3 - Bien</option>
                    <option value="2">2 - Moyen</option>
                    <option value="1">1 - Faible</option>
                  </select>
                </div>
              </div>

              <div class="field">
                <label for="prComment">Commentaire</label>
                <textarea id="prComment" name="comment" rows="4" maxlength="2000" required></textarea>
              </div>

              <div class="review-form-actions">
                <button class="btn btn-primary" type="submit">Envoyer l'avis</button>
                <p id="productReviewNotice" class="review-notice" role="status" aria-live="polite"></p>
              </div>
            </form>
          </div>
        </section>

        <section class="section-card section-card--similar related-products">
          <h2>Vous pourriez aussi aimer</h2>
          <p class="related-products-intro">Decouvrez des pieces similaires pour completer votre selection.</p>
          <?php if (!empty($similar_products)): ?>
            <div class="similar-grid related-products-grid">
              <?php foreach ($similar_products as $item): ?>
                <article class="similar-card related-product-card">
                  <a class="similar-media related-product-thumb" href="<?php echo e($item['url']); ?>" aria-label="Voir <?php echo e($item['name']); ?>">
                    <img src="<?php echo e($item['image']); ?>" alt="<?php echo e($item['name']); ?>" loading="lazy" decoding="async">
                  </a>
                  <div class="similar-body related-product-body">
                    <div class="related-product-top">
                      <?php if (!empty($item['badges']) && is_array($item['badges'])): ?>
                        <div class="related-product-badges">
                          <?php foreach ($item['badges'] as $badge): ?>
                            <span class="related-product-badge <?php echo e((string) ($badge['class'] ?? '')); ?>">
                              <?php echo e((string) ($badge['label'] ?? '')); ?>
                            </span>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($item['stock_short'])): ?>
                        <span class="related-product-stock"><?php echo e((string) $item['stock_short']); ?></span>
                      <?php endif; ?>
                    </div>
                    <h3 class="related-product-title"><a href="<?php echo e($item['url']); ?>"><?php echo e($item['name']); ?></a></h3>
                    <?php if ((int) ($item['rating_count'] ?? 0) > 0): ?>
                      <div class="related-product-rating">
                        &#9733; <?php echo e(number_format((float) ($item['avg_rating'] ?? 0), 1, '.', '')); ?>
                        <span>(<?php echo e((string) ((int) ($item['rating_count'] ?? 0))); ?>)</span>
                      </div>
                    <?php endif; ?>
                    <div class="similar-price related-product-price"><?php echo e($item['price']); ?></div>
                    <a class="btn btn-secondary similar-btn related-product-btn" href="<?php echo e($item['url']); ?>">Voir le produit</a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="muted">Aucune recommandation disponible pour le moment.</p>
          <?php endif; ?>
        </section>

        <section class="section-card">
          <h2>FAQ</h2>
          <div class="accordion" data-accordion>
            <div class="accordion-item">
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="faq1">
                Puis-je payer à la livraison ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="faq1" hidden>
                <p>Oui. Le paiement est possible à la livraison, après réception de votre commande.</p>
              </div>
            </div>

            <div class="accordion-item">
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="faq2">
                Quand vais-je recevoir ma commande ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="faq2" hidden>
                <p>Selon votre ville, la livraison prend generalement 24 a 72h ouvrees.</p>
              </div>
            </div>

            <div class="accordion-item">
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="faq3">
                Que faire si le produit n'est plus disponible ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="faq3" hidden>
                <p>Si un article est en rupture, contactez-nous : nous proposons une alternative ou un reassort si possible.</p>
              </div>
            </div>
          </div>
        </section>
      </div>
    <?php endif; ?>
  </div>
</section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>




