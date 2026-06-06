<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/ProductModel.php';
require_once __DIR__ . '/../app/services/ProductVariantService.php';

$page_title = 'Recherche produit';
$page_css = array('pages/catalogue.css', 'pages/recherche.css');
$page_js = 'pages/recherche.js';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$products = array();
$products_with_variants = array();
$db_error = false;

if ($q !== '') {
  try {
    $model = new ProductModel(db());
    $products = $model->search($q, 50);
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
    $products = array();
    $products_with_variants = array();
    error_log('[recherche] DB error: ' . $e->getMessage());
    if (function_exists('log_error')) {
      log_error('[recherche] DB error: ' . $e->getMessage());
    }
  }
}

function format_fcfa(int $price): string
{
  return number_format($price, 0, ',', ' ') . ' FCFA';
}

function stock_indicator(int $stock): array
{
  if ($stock <= 0) {
    return array('Rupture de stock', 'stock-out');
  }
  if ($stock <= 5) {
    return array('Stock limité (' . $stock . ')', 'stock-low');
  }
  return array('En stock (' . $stock . ')', 'stock-ok');
}

// Retour après "Ajouter au panier" depuis la recherche (relatif à app_base_url()).
$return_to = 'pages/recherche.php';
try {
  $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
  $base = app_base_url(); // ex: /xampp/site_v1/
  if ($uri !== '' && $base !== '' && str_starts_with($uri, $base)) {
    $return_to = ltrim(substr($uri, strlen($base)), '/');
  } elseif ($uri !== '') {
    $return_to = ltrim($uri, '/');
  }
} catch (Throwable $e) {
  $return_to = 'pages/recherche.php';
}

include __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="catalogue-header search-hero">
    <div class="container">
      <h1>Rechercher un produit</h1>
      <p class="search-subtitle">Trouvez rapidement un produit par son nom ou son code produit (SKU).</p>

      <div class="filters-section search-cta">
        <form class="search-bar" id="searchForm" method="get" action="recherche.php" role="search" aria-label="Recherche produit">
          <label class="sr-only" for="searchInput">Nom du produit ou code SKU</label>
          <input
            id="searchInput"
            type="text"
            class="search-input"
            name="q"
            value="<?php echo e($q); ?>"
            placeholder="Nom du produit ou code SKU (ex : TS-001)"
            autocomplete="off"
          >
          <button type="submit" class="search-btn btn-reset">Rechercher</button>
        </form>
      </div>
    </div>
  </section>

  <section class="products-section search-results">
    <div class="container">
      <div class="search-state" id="searchState" role="status" aria-live="polite">
        <?php if ($q === ''): ?>
          Utilisez le champ ci-dessus pour lancer une recherche.
        <?php elseif ($db_error): ?>
          Impossible d’effectuer la recherche pour le moment. Veuillez réessayer.
        <?php elseif (empty($products)): ?>
          Aucun résultat pour “<?php echo e($q); ?>”.
        <?php else: ?>
          <?php echo (int) count($products); ?> résultat(s) pour “<?php echo e($q); ?>”.
        <?php endif; ?>
      </div>

      <div class="products-grid" id="resultsGrid" <?php echo empty($products) ? 'hidden' : ''; ?>>
        <?php $placeholder_img = $base_url . 'assets/images/placeholders/product-placeholder.svg'; ?>

        <?php foreach ($products as $product): ?>
          <?php
            $id = (int) ($product['id'] ?? 0);
            $name = (string) ($product['name'] ?? 'Produit');
            $sku = (string) ($product['sku'] ?? '');
            $desc = (string) ($product['description'] ?? '');
            $price = (int) ($product['price'] ?? ($product['price_fcfa'] ?? ($product['prix'] ?? 0)));
            $stock = (int) ($product['stock'] ?? 0);
            $cat = (string) ($product['category'] ?? '');

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
            $product_url = $base_url . 'pages/produit.php?id=' . $id;
            $has_variants = isset($products_with_variants[$id]);
          ?>

          <div class="product-card" data-category="<?php echo htmlspecialchars($cat); ?>" data-sku="<?php echo htmlspecialchars($sku); ?>">
            <div class="product-image">
              <a href="<?php echo $product_url; ?>" class="product-media-link" aria-label="Voir le produit <?php echo htmlspecialchars($name); ?>">
                <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($name); ?>" loading="lazy">
              </a>
            </div>
            <div class="product-info">
              <h3><a href="<?php echo $product_url; ?>" class="product-title-link"><?php echo htmlspecialchars($name); ?></a></h3>
              <?php if ($sku !== ''): ?>
                <p class="product-sku">SKU: <?php echo htmlspecialchars($sku); ?></p>
              <?php endif; ?>
              <?php if ($desc !== ''): ?>
                <p class="product-description"><?php echo htmlspecialchars($desc); ?></p>
              <?php endif; ?>
              <span class="stock-indicator <?php echo $stock_class; ?>" aria-label="Disponibilité : <?php echo $stock_label; ?>">
                <?php echo $stock_label; ?>
              </span>
              <div class="product-price"><?php echo format_fcfa($price); ?></div>
              <div class="product-actions">
                <a href="<?php echo $product_url; ?>" class="btn btn-secondary">Voir détails</a>
                <?php if ($has_variants && $stock > 0): ?>
                  <a href="<?php echo $product_url; ?>" class="btn btn-primary add-to-cart add-to-cart--variant" aria-label="Choisir une taille pour <?php echo htmlspecialchars($name); ?>">
                    Choisir taille
                  </a>
                <?php else: ?>
                  <form method="post" action="<?php echo e($base_url); ?>pages/cart_add.php" class="product-actions__form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="product_id" value="<?php echo (int) $id; ?>">
                    <input type="hidden" name="return_to" value="<?php echo e($return_to); ?>">
                    <button
                      class="btn btn-primary add-to-cart"
                      type="submit"
                      <?php echo $stock <= 0 ? 'disabled aria-disabled="true"' : ''; ?>
                    >
                      <i class="fas fa-cart-plus" aria-hidden="true"></i> <?php echo $stock <= 0 ? 'Indisponible' : 'Ajouter'; ?>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="no-products" id="noResults" <?php echo (!$db_error && $q !== '' && empty($products)) ? '' : 'hidden'; ?>>
        <h3>Aucun produit trouvé pour votre recherche.</h3>
        <p>Essayez un autre nom ou un autre code SKU.</p>
        <p>
          <a class="btn btn-secondary" href="<?php echo e($base_url); ?>pages/catalogue.php">Voir le catalogue</a>
        </p>
      </div>
    </div>
  </section>

  <section class="faq-section">
    <div class="container">
      <h2>Questions fréquentes - Recherche</h2>
      <div class="faq-grid">
        <div class="faq-item">
          <button type="button" class="faq-question" aria-expanded="false" aria-controls="rf-a1">
            <h3 id="rf-q1">Comment rechercher un produit ?</h3>
            <span class="faq-toggle" aria-hidden="true">+</span>
          </button>
          <div class="faq-answer" id="rf-a1" role="region" aria-labelledby="rf-q1" aria-hidden="true">
            <p>Saisissez un nom de produit ou un code SKU (ex : TS-001), puis validez avec Entrée ou le bouton Rechercher.</p>
          </div>
        </div>

        <div class="faq-item">
          <button type="button" class="faq-question" aria-expanded="false" aria-controls="rf-a2">
            <h3 id="rf-q2">Que signifie le code SKU ?</h3>
            <span class="faq-toggle" aria-hidden="true">+</span>
          </button>
          <div class="faq-answer" id="rf-a2" role="region" aria-labelledby="rf-q2" aria-hidden="true">
            <p>Le SKU est un identifiant unique du produit. Il permet de retrouver un article rapidement.</p>
          </div>
        </div>

        <div class="faq-item">
          <button type="button" class="faq-question" aria-expanded="false" aria-controls="rf-a3">
            <h3 id="rf-q3">Puis-je payer à la livraison ?</h3>
            <span class="faq-toggle" aria-hidden="true">+</span>
          </button>
          <div class="faq-answer" id="rf-a3" role="region" aria-labelledby="rf-q3" aria-hidden="true">
            <p>Oui. SORA Collection fonctionne en V1 avec paiement à la livraison (confirmation à la réception).</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
