<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/ProductModel.php';
require_once __DIR__ . '/../app/models/CategoryModel.php';
require_once __DIR__ . '/../app/services/CategoryImageService.php';
require_once __DIR__ . '/../app/services/ProductVariantService.php';

// Récupérer la catégorie depuis l'URL
$categorie = isset($_GET['categorie']) ? trim((string) $_GET['categorie']) : '';
$sous_categorie = isset($_GET['sous_categorie']) ? trim((string) $_GET['sous_categorie']) : '';
/* Filtre collection */
$cat = isset($_GET['cat']) ? trim((string) $_GET['cat']) : '';
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$sort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : '';
if ($sort === 'popular') {
  // Compat legacy: ancienne URL de tri.
  $sort = 'featured';
}
$allowed_sorts = [
  'featured' => 'En vedette',
  'newest' => 'Plus recents',
  'price_asc' => 'Prix croissant',
  'price_desc' => 'Prix decroissant',
];
if ($sort !== '' && !array_key_exists($sort, $allowed_sorts)) {
  $sort = '';
}

// Libellés pour affichage (badge, titre, etc.)
$categories_labels = [
  'homme' => 'Homme',
  'femme' => 'Femme',
  'unisex' => 'Unisexe',
  'robes' => 'Robes',
  'boubous' => 'Boubous',
  'ensembles' => 'Ensembles',
  'chemises' => 'Chemises',
];
$sous_categories_labels = [
  'robes' => 'Robes',
  'boubous' => 'Boubous',
  'ensembles' => 'Ensembles',
  'chemises' => 'Chemises & Tops',
];
$catalog_menu_filters = [
  'femme' => ['robes', 'boubous', 'ensembles', 'chemises'],
  'homme' => ['boubous', 'ensembles', 'chemises'],
  'unisex' => [],
];

if ($categorie !== '' && !array_key_exists($categorie, $categories_labels)) {
  $categorie = '';
}
if ($sous_categorie !== '') {
  if (
    $categorie === ''
    || !isset($catalog_menu_filters[$categorie])
    || !in_array($sous_categorie, $catalog_menu_filters[$categorie], true)
  ) {
    $sous_categorie = '';
  }
}

/* Collection active et filtres */
$collection = null;
$collections = array();
if ($cat !== '' && preg_match('/^[a-z0-9-]{1,140}$/', $cat)) {
  try {
    $cm = new CategoryModel(db());
    if ($cm->exists()) {
      $collections = $cm->list(array('is_active' => 1));
      $collection = $cm->findBySlug($cat, true);
      if (!$collection) {
        $cat = '';
      }
    } else {
      $cat = '';
    }
  } catch (Throwable $e) {
    $cat = '';
    $collection = null;
  }
} else {
  $cat = '';
}

/* Liste des collections */
if (!$collections) {
  try {
    $cm2 = new CategoryModel(db());
    if ($cm2->exists()) {
      $collections = $cm2->list(array('is_active' => 1));
    }
  } catch (Throwable $e) {
    $collections = array();
  }
}

function stock_indicator(int $stock): array
{
  if ($stock <= 0) {
    return ['Rupture de stock', 'stock-out'];
  }
  if ($stock <= 5) {
    return ['Stock limité (' . $stock . ')', 'stock-low'];
  }
  return ['En stock (' . $stock . ')', 'stock-ok'];
}

/**
 * Badges produits credibles (max 2):
 * - En vedette: is_featured / featured / featured_rank
 * - Nouveau: created_at <= 30 jours
 * - Stock limite: stock > 0 && stock <= 5
 *
 * @param array<string,mixed> $row
 * @return array<int,array{label:string,class:string}>
 */
function catalog_product_badges(array $row, int $max = 2): array
{
  $max = max(1, $max);
  $badges = array();
  $priority = array(
    'En vedette' => 1,
    'Nouveau' => 2,
    'Stock limite' => 3,
    'En stock' => 4,
  );

  $isFeatured = false;
  if (array_key_exists('is_featured', $row) && (int) ($row['is_featured'] ?? 0) === 1) {
    $isFeatured = true;
  }
  if (!$isFeatured && array_key_exists('featured', $row) && (int) ($row['featured'] ?? 0) === 1) {
    $isFeatured = true;
  }
  if (!$isFeatured && array_key_exists('featured_rank', $row)) {
    $isFeatured = ((int) ($row['featured_rank'] ?? 0) > 0);
  }
  if ($isFeatured) {
    $badges[] = array('label' => 'En vedette', 'class' => 'product-badge--featured');
  }

  if (array_key_exists('created_at', $row)) {
    $ts = strtotime((string) ($row['created_at'] ?? ''));
    if ($ts !== false && $ts >= (time() - (86400 * 30))) {
      $badges[] = array('label' => 'Nouveau', 'class' => 'product-badge--new');
    }
  }

  if (array_key_exists('stock', $row)) {
    $stock = (int) ($row['stock'] ?? 0);
    if ($stock > 0 && $stock <= 5) {
      $badges[] = array('label' => 'Stock limite', 'class' => 'product-badge--low');
    } elseif ($stock > 5) {
      $badges[] = array('label' => 'En stock', 'class' => 'product-badge--in-stock');
    }
  }

  usort($badges, static function (array $a, array $b) use ($priority): int {
    $pa = $priority[(string) ($a['label'] ?? '')] ?? 99;
    $pb = $priority[(string) ($b['label'] ?? '')] ?? 99;
    return $pa <=> $pb;
  });

  return array_slice($badges, 0, $max);
}

function format_fcfa(int $price): string
{
  return number_format($price, 0, ',', ' ') . ' FCFA';
}

// Titre de page dynamique
if ($collection) {
  $page_title = ((string) ($collection['name'] ?? 'Collection')) . ' - Catalogue';
} elseif ($categorie) {
  $titre_categorie = $categories_labels[$categorie] ?? ucfirst($categorie);
  if ($sous_categorie !== '') {
    $titre_sous_categorie = $sous_categories_labels[$sous_categorie] ?? ucfirst($sous_categorie);
    $page_title = $titre_sous_categorie . ' ' . $titre_categorie . ' - Catalogue';
  } else {
    $page_title = "$titre_categorie - Catalogue";
  }
} else {
  $page_title = 'Notre catalogue de vêtements';
}

/* SEO */
$page_seo_title = '';
$page_meta_description = 'Découvrez les collections SORA Collection et trouvez des articles adaptés à vos besoins, avec commande en ligne au Mali.';
$page_og_image = '';
if ($collection) {
  $nameSeo = trim((string) ($collection['seo_title'] ?? ''));
  if ($nameSeo === '') {
    $nameSeo = (string) ($collection['name'] ?? 'Collection');
  }
  $page_seo_title = $nameSeo;

  $descSeo = trim((string) ($collection['seo_description'] ?? ''));
  if ($descSeo === '') {
    $descSeo = trim((string) ($collection['description'] ?? ''));
  }
  if ($descSeo !== '') {
    $page_meta_description = (function_exists('mb_substr') ? mb_substr($descSeo, 0, 255) : substr($descSeo, 0, 255));
  }

  $og = trim((string) ($collection['og_image'] ?? ''));
  if ($og === '') {
    $og = trim((string) ($collection['banner_image'] ?? ''));
  }
  $page_og_image = $og !== '' ? CategoryImageService::toUrl($og) : '';
} elseif ($categorie) {
  $page_seo_title = (string) ($titre_categorie ?? 'Catalogue');
  if ($categorie === 'unisex') {
    $page_meta_description = 'Decouvrez nos articles unisexes disponibles au Mali.';
  } else {
    $page_meta_description = 'Découvrez nos ' . (string) ($titre_categorie ?? 'produits') . ' disponibles au Mali.';
  }
}

$page_css = 'pages/catalogue.css';
$page_js = 'pages/catalogue.js';

// Pagination (V1) - branchée DB
$produits_par_page = 6;
$page_courante = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$filters = [];
/* Filtre collection conservé */
if ($cat !== '') {
  $filters['category_slug'] = $cat;
}
if ($categorie) {
  if (in_array($categorie, array('homme', 'femme', 'unisex'), true)) {
    $filters['gender'] = $categorie;
    if ($sous_categorie !== '') {
      $filters['category'] = $sous_categorie;
    }
  } else {
    $filters['category'] = $categorie;
  }
}
if ($q !== '') {
  $filters['q'] = $q;
}
if ($sort !== '') {
  $filters['sort'] = $sort;
}

$db_error = false;
$products = [];
$product_ratings = [];
$products_with_variants = [];
$total_produits = 0;
$derniere_page = 1;

try {
  $model = new ProductModel(db());
  $total_produits = $model->countAll($filters);
  $derniere_page = max(1, (int) ceil($total_produits / $produits_par_page));
  $page_courante = min($page_courante, $derniere_page);

  $offset = ($page_courante - 1) * $produits_par_page;
  $products = $model->getAll(array_merge($filters, [
    'limit' => $produits_par_page,
    'offset' => $offset,
  ]));

  $productIds = array_values(array_filter(array_map(static fn (array $p): int => (int) ($p['id'] ?? 0), $products), static fn (int $v): bool => $v > 0));
  if ($productIds) {
    $variantService = new ProductVariantService(db());
    if ($variantService->isSupported()) {
      $in = implode(',', array_fill(0, count($productIds), '?'));
      $stmtVariants = db()->prepare(
        "SELECT DISTINCT product_id
         FROM product_variants
         WHERE is_active = 1 AND product_id IN ($in)"
      );
      $stmtVariants->execute($productIds);
      foreach (($stmtVariants->fetchAll(PDO::FETCH_COLUMN) ?: array()) as $variantProductId) {
        $products_with_variants[(int) $variantProductId] = true;
      }
    }
  }
} catch (Throwable $e) {
  $db_error = true;
  $products = [];
  $product_ratings = [];
  $products_with_variants = [];
  $total_produits = 0;
  $derniere_page = 1;
}

if (!$db_error && !empty($products)) {
  try {
    $ids = array_values(array_filter(array_map(static fn (array $p): int => (int) ($p['id'] ?? 0), $products), static fn (int $v): bool => $v > 0));
    if (!empty($ids)) {
      $in = implode(',', array_fill(0, count($ids), '?'));
      $sql = "SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS total_reviews
              FROM product_reviews
              WHERE is_approved = 1 AND product_id IN ($in)
              GROUP BY product_id";
      $stmt = db()->prepare($sql);
      $stmt->execute($ids);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
      foreach ($rows as $r) {
        $pid = (int) ($r['product_id'] ?? 0);
        if ($pid <= 0) continue;
        $product_ratings[$pid] = array(
          'avg' => (float) ($r['avg_rating'] ?? 0),
          'count' => (int) ($r['total_reviews'] ?? 0),
        );
      }
    }
  } catch (Throwable $e) {
    $product_ratings = [];
  }
}

$index_debut = $total_produits > 0 ? (($page_courante - 1) * $produits_par_page) + 1 : 0;
$index_fin = $total_produits > 0 ? min($page_courante * $produits_par_page, $total_produits) : 0;

// Retour après "Ajouter au panier" depuis le catalogue (relatif à app_base_url()).
$return_to = 'pages/catalogue.php';
try {
  $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
  $base = app_base_url(); // ex: /xampp/site_v1/
  if ($uri !== '' && $base !== '' && str_starts_with($uri, $base)) {
    $return_to = ltrim(substr($uri, strlen($base)), '/');
  } elseif ($uri !== '') {
    $return_to = ltrim($uri, '/');
  }
} catch (Throwable $e) {
  $return_to = 'pages/catalogue.php';
}

include __DIR__ . '/../includes/header.php';
?>

<main id="main" data-catalogue-mode="server">

<!-- En-tête Catalogue -->
<section class="catalogue-header">
  <div class="container">
    <?php
     
      $header_h1 = 'Notre catalogue de vêtements';
      if ($collection) {
        $header_h1 = (string) ($collection['name'] ?? 'Collection');
      } elseif ($categorie && $sous_categorie !== '') {
        $header_h1 = (string) ($sous_categories_labels[$sous_categorie] ?? ucfirst($sous_categorie)) . ' ' . (string) ($categories_labels[$categorie] ?? ucfirst($categorie));
      } elseif ($categorie) {
        $header_h1 = $categorie === 'unisex'
          ? 'Nos articles unisexes'
          : 'Nos ' . (string) ($titre_categorie ?? 'produits');
      }

      $header_subtitle = 'Découvrez tous nos vêtements disponibles, classés par style.';
      if ($collection && trim((string) ($collection['description'] ?? '')) !== '') {
        $header_subtitle = (string) $collection['description'];
      } elseif ($categorie && $sous_categorie !== '') {
        $header_subtitle = 'Affichage cible des produits ' . (string) ($sous_categories_labels[$sous_categorie] ?? $sous_categorie) . ' pour ' . (string) ($categories_labels[$categorie] ?? $categorie) . '.';
      }

      $banner_url = '';
      if ($collection) {
        $banner_url = CategoryImageService::toUrl((string) ($collection['banner_image'] ?? ''));
      }
    ?>

    <?php if ($banner_url !== ''): ?>
      <div class="collection-banner" style="background-image: url('<?php echo e($banner_url); ?>');" role="img" aria-label="<?php echo e($header_h1); ?>"></div>
    <?php endif; ?>

    <h1><?php echo e($header_h1); ?></h1>
    <p class="section-subtitle"><?php echo e($header_subtitle); ?></p>

    <?php if ($collections): ?>
      <div class="collections-row" aria-label="Collections">
        <a class="collection-chip <?php echo ($cat === '' && !$categorie) ? 'is-active' : ''; ?>" href="<?php echo e($base_url); ?>pages/catalogue.php">Tout</a>
        <?php foreach ($collections as $cRow): ?>
          <?php
            $cName = (string) ($cRow['name'] ?? '');
            $cSlug = (string) ($cRow['slug'] ?? '');
            if ($cName === '' || $cSlug === '') continue;
          ?>
          <a
            class="collection-chip <?php echo ($cat !== '' && $cat === $cSlug) ? 'is-active' : ''; ?>"
            href="<?php echo e($base_url); ?>pages/catalogue.php?cat=<?php echo e($cSlug); ?>"
          >
            <?php echo e($cName); ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="filters-section">
      <form class="search-bar" method="get" action="catalogue.php">
        <?php ?>
        <?php if ($cat !== ''): ?>
          <input type="hidden" name="cat" value="<?php echo e($cat); ?>">
        <?php endif; ?>
        <?php if ($categorie): ?>
          <input type="hidden" name="categorie" value="<?php echo htmlspecialchars($categorie); ?>">
        <?php endif; ?>
        <?php if ($sous_categorie !== ''): ?>
          <input type="hidden" name="sous_categorie" value="<?php echo htmlspecialchars($sous_categorie); ?>">
        <?php endif; ?>
        <?php if ($sort !== ''): ?>
          <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
        <?php endif; ?>
        <input type="text" class="search-input" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Rechercher un produit ou code (SKU)">
        <button type="submit" class="search-btn" aria-label="Rechercher">
          <i class="fas fa-search" aria-hidden="true"></i>
        </button>
      </form>

      <form class="sort-filter" method="get" action="catalogue.php">
        <?php if ($cat !== ''): ?>
          <input type="hidden" name="cat" value="<?php echo e($cat); ?>">
        <?php endif; ?>
        <?php if ($categorie !== ''): ?>
          <input type="hidden" name="categorie" value="<?php echo htmlspecialchars($categorie); ?>">
        <?php endif; ?>
        <?php if ($sous_categorie !== ''): ?>
          <input type="hidden" name="sous_categorie" value="<?php echo htmlspecialchars($sous_categorie); ?>">
        <?php endif; ?>
        <?php if ($q !== ''): ?>
          <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
        <?php endif; ?>
        <select name="sort" class="filter-select" aria-label="Trier par" onchange="this.form.submit()">
          <option value="">Trier par</option>
          <?php foreach ($allowed_sorts as $sort_value => $sort_label): ?>
            <option value="<?php echo htmlspecialchars($sort_value); ?>" <?php echo $sort === $sort_value ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($sort_label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>
</section>

<!-- Liste des produits -->
<section class="products-section">
  <div class="container">
    <?php $catalog_reset_href = $base_url . 'pages/catalogue.php' . ($sous_categorie !== '' ? ('?categorie=' . rawurlencode($categorie)) : ''); ?>
    <?php if ($categorie && $sous_categorie !== ''): ?>
      <div class="active-filter" role="status" aria-live="polite">
        <span class="active-filter__label">
          Genre : <strong><?php echo htmlspecialchars($titre_categorie); ?></strong> | Sous-categorie : <strong><?php echo htmlspecialchars((string) ($sous_categories_labels[$sous_categorie] ?? $sous_categorie)); ?></strong>
        </span>
        <a class="active-filter__reset" href="<?php echo $catalog_reset_href; ?>" aria-label="Reinitialiser la categorie">
          <span aria-hidden="true">x</span> Reinitialiser
        </a>
      </div>
    <?php endif; ?>
    <?php if ($categorie && $sous_categorie === ''): ?>
      <div class="active-filter" role="status" aria-live="polite">
        <span class="active-filter__label">
          <?php echo in_array($categorie, array('homme', 'femme', 'unisex'), true) ? 'Genre' : 'Catégorie'; ?> : <strong><?php echo htmlspecialchars($titre_categorie); ?></strong>
        </span>
        <a class="active-filter__reset" href="<?php echo $base_url; ?>pages/catalogue.php" aria-label="Réinitialiser la catégorie">
          <span aria-hidden="true">×</span> Réinitialiser
        </a>
      </div>
    <?php endif; ?>

    <?php if ($cat !== '' && $collection): ?>
      <div class="active-filter" role="status" aria-live="polite">
        <span class="active-filter__label">
          Collection : <strong><?php echo e((string) ($collection['name'] ?? '')); ?></strong>
        </span>
        <a class="active-filter__reset" href="<?php echo $base_url; ?>pages/catalogue.php" aria-label="Réinitialiser la collection">
          <span aria-hidden="true">×</span> Réinitialiser
        </a>
      </div>
    <?php endif; ?>

    <div class="products-grid" id="productsGrid">
      <?php $placeholder_img = $base_url . 'assets/images/placeholders/product-placeholder.svg'; ?>

      <?php foreach ($products as $product): ?>
        <?php
          $id = (int) ($product['id'] ?? 0);
          $name = (string) ($product['name'] ?? 'Produit');
          $sku = (string) ($product['sku'] ?? '');
          $desc = (string) ($product['description'] ?? '');
          $price = (int) ($product['price'] ?? ($product['price_fcfa'] ?? ($product['prix'] ?? 0)));
          $has_stock = array_key_exists('stock', $product);
          $stock = $has_stock ? (int) ($product['stock'] ?? 0) : 0;
          $stock_short = '';
          if ($has_stock) {
            $stock_short = ($stock > 5) ? 'En stock' : (($stock > 0) ? 'Stock limite' : 'Rupture');
          }
          $product_category = (string) ($product['category'] ?? '');

          $img = (string) ($product['image1'] ?? ($product['image_path'] ?? ($product['image_main'] ?? ($product['image'] ?? ''))));
          $img = trim($img);
          if ($img === '') {
            $img_src = $placeholder_img;
          } elseif (strpos($img, 'http://') === 0 || strpos($img, 'https://') === 0) {
            $img_src = $img;
          } elseif ($img[0] === '/') {
            $img_src = $img;
          } else {
            $img = str_replace('\\', '/', $img);
            $pos = stripos($img, 'uploads/products/');
            if ($pos !== false) {
              $img = substr($img, $pos);
            } elseif (preg_match('/^[a-zA-Z]:\\//', $img)) {
              $img = basename($img);
            }
            // Si la DB stocke seulement un nom de fichier, supposer /uploads/products/
            if (strpos($img, '/') === false) {
              $img = 'uploads/products/' . ltrim($img, '/');
            }

            // Si le fichier n'existe pas, fallback placeholder (évite image cassée)
            $fs = base_path($img);
            if (!is_file($fs)) {
              $img_src = $placeholder_img;
            } else {
              $img_src = $base_url . ltrim($img, '/');
            }
          }

          [$stock_label, $stock_class] = stock_indicator($stock);
          $card_badges = catalog_product_badges($product, 2);
          $rating_data = $product_ratings[$id] ?? array('avg' => 0.0, 'count' => 0);
          $rating_count = (int) ($rating_data['count'] ?? 0);
          $rating_avg = (float) ($rating_data['avg'] ?? 0);
          $shows_stock_badge = false;
          foreach ($card_badges as $badge_row) {
            $cls = (string) ($badge_row['class'] ?? '');
            if ($cls === 'product-badge--low' || $cls === 'product-badge--in-stock') {
              $shows_stock_badge = true;
              break;
            }
          }
          $product_url = $base_url . 'pages/produit.php?id=' . $id;
          $has_variants = isset($products_with_variants[$id]);
        ?>

        <div class="product-card" data-category="<?php echo htmlspecialchars($product_category); ?>" data-sku="<?php echo htmlspecialchars($sku); ?>">
          <div class="product-image">
            <?php if (!empty($card_badges)): ?>
              <div class="product-badges" aria-label="Informations produit">
                <?php foreach ($card_badges as $badge): ?>
                  <span class="product-badge <?php echo htmlspecialchars((string) ($badge['class'] ?? '')); ?>">
                    <?php echo htmlspecialchars((string) ($badge['label'] ?? '')); ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <a href="<?php echo $product_url; ?>" class="product-media-link" aria-label="Voir le produit <?php echo htmlspecialchars($name); ?>">
              <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($name); ?>" loading="lazy">
            </a>
          </div>
          <div class="product-info">
            <h3><a href="<?php echo $product_url; ?>" class="product-title-link"><?php echo htmlspecialchars($name); ?></a></h3>
            <?php if ($rating_count > 0): ?>
              <div class="product-rating-mini" aria-label="Note moyenne produit">
                <span aria-hidden="true">&#9733;</span> <?php echo e(number_format($rating_avg, 1, '.', '')); ?> (<?php echo (int) $rating_count; ?>)
              </div>
            <?php endif; ?>
            <?php if ($sku !== ''): ?>
              <p class="product-sku">SKU: <?php echo htmlspecialchars($sku); ?></p>
            <?php endif; ?>
            <?php if ($desc !== ''): ?>
              <p class="product-description"><?php echo htmlspecialchars($desc); ?></p>
            <?php endif; ?>
            <?php if ($stock_short !== '' && !$shows_stock_badge): ?>
              <span class="product-stock-short"><?php echo e($stock_short); ?></span>
            <?php elseif ($has_stock && !$shows_stock_badge): ?>
              <span class="stock-indicator <?php echo $stock_class; ?>" aria-label="Disponibilité : <?php echo $stock_label; ?>">
                <?php echo $stock_label; ?>
              </span>
            <?php endif; ?>
            <div class="product-price"><?php echo format_fcfa($price); ?></div>
            <div class="product-actions">
              <?php if ($has_variants && (!$has_stock || $stock > 0)): ?>
                <a href="<?php echo $product_url; ?>" class="btn btn-primary add-to-cart add-to-cart--variant" aria-label="Choisir une taille pour <?php echo htmlspecialchars($name); ?>">
                  Choisir la taille
                </a>
              <?php else: ?>
                <form method="post" action="<?php echo e($base_url); ?>pages/cart_add.php" class="product-actions__form">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="product_id" value="<?php echo (int) $id; ?>">
                  <input type="hidden" name="return_to" value="<?php echo e($return_to); ?>">
                  <button
                    class="btn btn-primary add-to-cart"
                    type="submit"
                    <?php echo ($has_stock && $stock <= 0) ? 'disabled aria-disabled="true"' : ''; ?>
                  >
                    <i class="fas fa-cart-plus" aria-hidden="true"></i> <?php echo ($has_stock && $stock <= 0) ? 'Indisponible' : 'Ajouter'; ?>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Message vide -->
    <div class="no-products" id="noProductsMessage" style="display: <?php echo empty($products) ? 'block' : 'none'; ?>;">
      <i class="fas fa-box-open" aria-hidden="true"></i>
      <?php if ($db_error): ?>
        <h3>Impossible de charger le catalogue</h3>
        <p>Veuillez réessayer dans un instant.</p>
      <?php else: ?>
        <h3>Aucun produit disponible</h3>
        <p>Essayez une autre catégorie ou revenez plus tard.</p>
      <?php endif; ?>
    </div>

    <?php if ($total_produits > 0): ?>
      <!-- Pagination -->
      <p
        class="pagination-info"
        id="paginationInfo"
        data-per-page="<?php echo $produits_par_page; ?>"
        data-total="<?php echo $total_produits; ?>"
      >
        Affichage de <?php echo $index_debut; ?>–<?php echo $index_fin; ?> sur <?php echo $total_produits; ?> produits
      </p>

      <div class="pagination" aria-label="Pagination">
        <?php
          $build_url = function (int $page) use ($categorie, $sous_categorie, $cat, $q, $sort) {
            $params = [];
            if ($cat !== '') $params['cat'] = $cat;
            if ($categorie !== '') $params['categorie'] = $categorie;
            if ($sous_categorie !== '') $params['sous_categorie'] = $sous_categorie;
            if ($q !== '') $params['q'] = $q;
            if ($sort !== '') $params['sort'] = $sort;
            $params['page'] = $page;
            return 'catalogue.php?' . http_build_query($params);
          };
        ?>

        <?php if ($page_courante <= 1): ?>
          <span class="pagination-btn prev-btn is-disabled" aria-disabled="true">
            <i class="fas fa-chevron-left" aria-hidden="true"></i> Précédent
          </span>
        <?php else: ?>
          <a class="pagination-btn prev-btn" href="<?php echo htmlspecialchars($build_url($page_courante - 1)); ?>">
            <i class="fas fa-chevron-left" aria-hidden="true"></i> Précédent
          </a>
        <?php endif; ?>

        <div class="page-numbers" aria-label="Pages">
          <?php for ($p = 1; $p <= $derniere_page; $p += 1): ?>
            <a class="pagination-btn <?php echo $p === $page_courante ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($build_url($p)); ?>">
              <?php echo $p; ?>
            </a>
          <?php endfor; ?>
        </div>

        <?php if ($page_courante >= $derniere_page): ?>
          <span class="pagination-btn next-btn is-disabled" aria-disabled="true">
            Suivant <i class="fas fa-chevron-right" aria-hidden="true"></i>
          </span>
        <?php else: ?>
          <a class="pagination-btn next-btn" href="<?php echo htmlspecialchars($build_url($page_courante + 1)); ?>">
            Suivant <i class="fas fa-chevron-right" aria-hidden="true"></i>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Section FAQ Catalogue -->
<section class="faq-section">
  <div class="container">
    <h2>Questions fréquentes - Catalogue</h2>
    <div class="faq-grid">
      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false" aria-controls="faq-a1">
          <h3 id="faq-q1">Comment rechercher un produit ?</h3>
          <span class="faq-toggle" aria-hidden="true"><i class="fas fa-plus"></i></span>
        </button>
        <div class="faq-answer" id="faq-a1" role="region" aria-labelledby="faq-q1" aria-hidden="true">
          <p>Utilisez la barre de recherche en haut de la page ou filtrez par catégorie via le menu déroulant.</p>
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false" aria-controls="faq-a2">
          <h3 id="faq-q2">Que signifie le code produit (SKU) ?</h3>
          <span class="faq-toggle" aria-hidden="true"><i class="fas fa-plus"></i></span>
        </button>
        <div class="faq-answer" id="faq-a2" role="region" aria-labelledby="faq-q2" aria-hidden="true">
          <p>Le SKU est un code unique qui identifie chaque produit. Utilisez-le pour référencer rapidement un article.</p>
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false" aria-controls="faq-a3">
          <h3 id="faq-q3">Les produits sont-ils disponibles immédiatement ?</h3>
          <span class="faq-toggle" aria-hidden="true"><i class="fas fa-plus"></i></span>
        </button>
        <div class="faq-answer" id="faq-a3" role="region" aria-labelledby="faq-q3" aria-hidden="true">
          <p>Oui, tous les produits affichés sont en stock et disponibles pour livraison rapide.</p>
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false" aria-controls="faq-a4">
          <h3 id="faq-q4">Que faire si un produit n’est plus disponible ?</h3>
          <span class="faq-toggle" aria-hidden="true"><i class="fas fa-plus"></i></span>
        </button>
        <div class="faq-answer" id="faq-a4" role="region" aria-labelledby="faq-q4" aria-hidden="true">
          <p>Si un article est en rupture, vous pouvez choisir un produit similaire ou revenir plus tard. Notre catalogue est mis à jour régulièrement.</p>
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false" aria-controls="faq-a5">
          <h3 id="faq-q5">Puis-je payer à la livraison ?</h3>
          <span class="faq-toggle" aria-hidden="true"><i class="fas fa-plus"></i></span>
        </button>
        <div class="faq-answer" id="faq-a5" role="region" aria-labelledby="faq-q5" aria-hidden="true">
          <p>Absolument ! Le paiement se fait à la livraison, avec confirmation à la réception.</p>
        </div>
      </div>
    </div>
  </div>
</section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
