<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/cart.php';
require_once __DIR__ . '/../app/models/ProductModel.php';
require_once __DIR__ . '/../app/services/ProductVariantService.php';

function e($value) {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function normalize_image_url($path, $base_url) {
  $path = trim((string) $path);
  if ($path === '') return '';
  if (preg_match('#^https?://#i', $path)) return $path;
  if (strpos($path, '/') === 0) return $path;
  $path = str_replace('\\', '/', $path);

  $pos = stripos($path, 'uploads/products/');
  if ($pos !== false) {
    $path = substr($path, $pos);
  } elseif (preg_match('/^[a-zA-Z]:\\//', $path)) {
    $path = basename($path);
  }

  if (strpos($path, '/') === false) {
    $path = 'uploads/products/' . ltrim($path, '/');
  }

  $fs = base_path($path);
  if (!is_file($fs)) {
    return rtrim((string) $base_url, '/') . '/assets/images/placeholders/product-placeholder.svg';
  }

  return rtrim((string) $base_url, '/') . '/' . ltrim($path, '/');
}

function add_flash($type, $message) {
  if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
    $_SESSION['flash'] = array();
  }
  $_SESSION['flash'][] = array(
    'type' => (string) $type,
    'message' => (string) $message,
  );
}

function consume_flash() {
  $messages = array();
  if (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    $messages = $_SESSION['flash'];
  }
  unset($_SESSION['flash']);
  return $messages;
}

$cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : array();
$cart = cart_normalize_map($cart);
$_SESSION['cart'] = $cart;

$db_error = '';
$products_by_id = array();
$variants_by_id = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = (string) $_POST['action'];
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    add_flash('error', 'Action refusée. Veuillez réessayer.');
    header('Location: ' . $base_url . 'pages/panier.php');
    exit;
  }

  try {
    $pdo = db();
    $variantService = new ProductVariantService($pdo);

    if ($action === 'update_qty') {
      $itemKey = trim((string) ($_POST['item_key'] ?? ''));
      $qty = filter_input(INPUT_POST, 'qty', FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));

      if ($itemKey === '' || !$qty || !isset($_SESSION['cart'][$itemKey])) {
        add_flash('error', 'Quantité invalide.');
        header('Location: ' . $base_url . 'pages/panier.php');
        exit;
      }

      $parsed = cart_parse_key($itemKey);
      $productId = (int) ($parsed['product_id'] ?? 0);
      $variantId = (int) ($parsed['variant_id'] ?? 0);

      if ($variantId > 0) {
        $variant = $variantService->findById($variantId);
        if (!$variant || (int) ($variant['is_active'] ?? 0) !== 1) {
          unset($_SESSION['cart'][$itemKey]);
          add_flash('info', 'Variante supprimée du panier (introuvable).');
          header('Location: ' . $base_url . 'pages/panier.php');
          exit;
        }
        $stock = (int) ($variant['stock'] ?? 0);
      } else {
        $stmt = $pdo->prepare('SELECT stock FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(array(':id' => $productId));
        $row = $stmt->fetch();
        if (!$row) {
          unset($_SESSION['cart'][$itemKey]);
          add_flash('info', 'Produit supprimé du panier (introuvable).');
          header('Location: ' . $base_url . 'pages/panier.php');
          exit;
        }
        $stock = (int) ($row['stock'] ?? 0);
      }

      if ($stock <= 0) {
        $_SESSION['cart'][$itemKey] = 1;
        add_flash('error', 'Ce produit est en rupture. Supprimez-le pour commander.');
        header('Location: ' . $base_url . 'pages/panier.php');
        exit;
      }

      if ($qty > $stock) {
        $qty = $stock;
        add_flash('info', 'Quantité ajustée selon le stock disponible.');
      } else {
        add_flash('success', 'Quantité mise à jour.');
      }

      $_SESSION['cart'][$itemKey] = (int) $qty;
      header('Location: ' . $base_url . 'pages/panier.php');
      exit;
    }

    if ($action === 'remove_item') {
      $itemKey = trim((string) ($_POST['item_key'] ?? ''));
      if ($itemKey !== '') {
        unset($_SESSION['cart'][$itemKey]);
        add_flash('success', 'Produit supprimé du panier.');
      }
      header('Location: ' . $base_url . 'pages/panier.php');
      exit;
    }

    if ($action === 'clear_cart') {
      $_SESSION['cart'] = array();
      add_flash('success', 'Panier vidé.');
      header('Location: ' . $base_url . 'pages/panier.php');
      exit;
    }
  } catch (Throwable $e) {
    add_flash('error', 'Impossible de mettre à jour le panier.');
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
      error_log('[panier.php] ' . $e->getMessage());
    }
    header('Location: ' . $base_url . 'pages/panier.php');
    exit;
  }
}

$cartLines = cart_normalize_lines($cart);
if ($cartLines) {
  try {
    $pdo = db();
    $model = new ProductModel($pdo);
    $variantService = new ProductVariantService($pdo);

    $productIds = array();
    foreach ($cartLines as $line) {
      $productId = (int) ($line['product_id'] ?? 0);
      $variantId = (int) ($line['variant_id'] ?? 0);
      if ($variantId > 0) {
        $variant = $variantService->findById($variantId);
        if ($variant) {
          $variants_by_id[$variantId] = $variant;
          $productId = (int) ($variant['product_id'] ?? 0);
        }
      }
      if ($productId > 0) {
        $productIds[$productId] = true;
      }
    }

    if ($productIds) {
      foreach ($model->findByIds(array_keys($productIds)) as $row) {
        $products_by_id[(int) ($row['id'] ?? 0)] = $row;
      }
    }

    $changed = false;
    foreach ($cartLines as $line) {
      $itemKey = (string) ($line['key'] ?? '');
      $productId = (int) ($line['product_id'] ?? 0);
      $variantId = (int) ($line['variant_id'] ?? 0);
      if ($variantId > 0) {
        $variant = $variants_by_id[$variantId] ?? null;
        if (!$variant) {
          unset($_SESSION['cart'][$itemKey]);
          $changed = true;
          continue;
        }
        $productId = (int) ($variant['product_id'] ?? 0);
      }
      if ($productId <= 0 || !isset($products_by_id[$productId])) {
        unset($_SESSION['cart'][$itemKey]);
        $changed = true;
      }
    }

    if ($changed) {
      $cart = cart_normalize_map($_SESSION['cart']);
      $_SESSION['cart'] = $cart;
      $cartLines = cart_normalize_lines($cart);
      add_flash('info', 'Certains articles ont été retirés (introuvables).');
    }
  } catch (Throwable $e) {
    $db_error = 'Connexion à la base de données impossible pour le moment. Vérifiez la configuration dans config.php.';
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
      $db_error_details = $e->getMessage();
    }
    $products_by_id = array();
    $variants_by_id = array();
  }
}

$flash_messages = consume_flash();
$items = array();
$total = 0;
$has_out_of_stock = false;

foreach ($cartLines as $line) {
  $itemKey = (string) ($line['key'] ?? '');
  $productId = (int) ($line['product_id'] ?? 0);
  $variantId = (int) ($line['variant_id'] ?? 0);
  $qty = (int) ($line['qty'] ?? 0);
  $variant = null;

  if ($variantId > 0) {
    $variant = $variants_by_id[$variantId] ?? null;
    if (!$variant) continue;
    $productId = (int) ($variant['product_id'] ?? 0);
  }
  if (!isset($products_by_id[$productId])) continue;

  $p = $products_by_id[$productId];
  $price = isset($p['price']) ? (int) $p['price'] : 0;
  $stock = $variant ? (int) ($variant['stock'] ?? 0) : (int) ($p['stock'] ?? 0);

  if ($stock > 0 && $qty > $stock) {
    $qty = $stock;
    $_SESSION['cart'][$itemKey] = $qty;
    add_flash('info', 'Quantité ajustée selon le stock disponible.');
  }

  if ($stock <= 0) {
    $has_out_of_stock = true;
  }

  $line_total = $price * $qty;
  $total += $line_total;

  $items[] = array(
    'item_key' => $itemKey,
    'id' => (int) ($p['id'] ?? 0),
    'name' => (string) ($p['name'] ?? ''),
    'sku' => (string) ($p['sku'] ?? ''),
    'price' => $price,
    'price_display' => function_exists('format_price') ? format_price($price) : (number_format($price, 0, ',', ' ') . ' FCFA'),
    'image' => normalize_image_url($p['image'] ?? '', $base_url),
    'stock' => $stock,
    'qty' => $qty,
    'size' => $variant ? (string) ($variant['size'] ?? '') : '',
    'color' => $variant ? (string) ($variant['color'] ?? '') : '',
    'line_total' => $line_total,
    'line_total_display' => function_exists('format_price') ? format_price($line_total) : (number_format($line_total, 0, ',', ' ') . ' FCFA'),
  );
}

if (isset($_SESSION['flash']) && is_array($_SESSION['flash']) && count($_SESSION['flash']) > 0) {
  $flash_messages = array_merge($flash_messages, consume_flash());
}

$page_title = 'Mon panier';
$page_css = 'pages/panier.css';
$page_js = 'pages/panier.js';
include __DIR__ . '/../includes/header.php';
?>

<main id="main">
<section class="cart-page">
  <div class="container">
    <h1>Mon panier</h1>

    <?php if ($db_error): ?>
      <div class="cart-notice cart-notice--error" role="alert">
        <?php echo e($db_error); ?>
      </div>
      <?php if (isset($db_error_details) && defined('DEBUG_MODE') && DEBUG_MODE): ?>
        <details class="cart-notice cart-notice--info">
          <summary>Détails (dev)</summary>
          <pre style="white-space: pre-wrap; margin: 10px 0 0;"><?php echo e($db_error_details); ?></pre>
        </details>
      <?php endif; ?>
    <?php endif; ?>

    <?php foreach ($flash_messages as $msg): ?>
      <?php
        $type = isset($msg['type']) ? (string) $msg['type'] : 'info';
        $class = 'cart-notice';
        if ($type === 'success') $class .= ' cart-notice--success';
        if ($type === 'error') $class .= ' cart-notice--error';
        if ($type === 'info') $class .= ' cart-notice--info';
      ?>
      <div class="<?php echo e($class); ?>" role="<?php echo $type === 'error' ? 'alert' : 'status'; ?>">
        <?php echo e($msg['message'] ?? ''); ?>
      </div>
    <?php endforeach; ?>

    <?php if (count($items) === 0): ?>
      <div class="cart-empty">
        <p>Votre panier est vide.</p>
        <a class="btn btn-secondary" href="<?php echo e($base_url); ?>pages/catalogue.php">Découvrir le catalogue</a>
      </div>
    <?php else: ?>
      <div class="cart-layout">
        <div class="cart-items" aria-label="Articles du panier">
          <div class="cart-head" aria-hidden="true">
            <div>Produit</div>
            <div>Prix</div>
            <div>Quantité</div>
            <div>Sous-total</div>
            <div></div>
          </div>

          <?php foreach ($items as $it): ?>
            <?php
              $stock = (int) $it['stock'];
              $stock_label = ($stock > 5) ? 'En stock' : (($stock > 0) ? 'Stock limité' : 'Rupture');
              $stock_class = ($stock > 5) ? 'stock-pill--ok' : (($stock > 0) ? 'stock-pill--low' : 'stock-pill--out');
            ?>
            <div class="cart-item">
              <div class="cart-product">
                <a class="cart-product__media" href="<?php echo e($base_url); ?>pages/produit.php?id=<?php echo (int) $it['id']; ?>" aria-label="Voir <?php echo e($it['name']); ?>">
                  <?php if ($it['image']): ?>
                    <img src="<?php echo e($it['image']); ?>" alt="<?php echo e($it['name']); ?>" loading="lazy" decoding="async">
                  <?php else: ?>
                    <div class="cart-media-placeholder" aria-hidden="true">
                      <i class="fas fa-image"></i>
                    </div>
                  <?php endif; ?>
                </a>
                <div class="cart-product__info">
                  <a class="cart-product__name" href="<?php echo e($base_url); ?>pages/produit.php?id=<?php echo (int) $it['id']; ?>">
                    <?php echo e($it['name']); ?>
                  </a>
                  <div class="cart-product__meta">
                    <span class="cart-sku">SKU: <?php echo e($it['sku']); ?></span>
                    <?php if ((string) $it['size'] !== ''): ?><span>Taille: <?php echo e($it['size']); ?></span><?php endif; ?>
                    <?php if ((string) $it['color'] !== ''): ?><span>Couleur: <?php echo e($it['color']); ?></span><?php endif; ?>
                    <span class="stock-pill <?php echo e($stock_class); ?>"><?php echo e($stock_label); ?></span>
                  </div>
                </div>
              </div>

              <div class="cart-price" data-label="Prix unitaire">
                <?php echo e($it['price_display']); ?>
              </div>

              <div class="cart-qty" data-label="Quantité">
                <form method="post" class="qty-form" data-qty-form>
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="update_qty">
                  <input type="hidden" name="item_key" value="<?php echo e((string) $it['item_key']); ?>">
                  <div class="qty-selector" role="group" aria-label="Sélecteur de quantité">
                    <button class="qty-btn qty-btn--minus" type="button" data-qty-minus aria-label="Diminuer la quantité">-</button>
                    <input
                      class="qty-input"
                      type="number"
                      name="qty"
                      value="<?php echo (int) $it['qty']; ?>"
                      min="1"
                      <?php echo $stock > 0 ? 'max="' . (int) $stock . '"' : ''; ?>
                      inputmode="numeric"
                    >
                    <button class="qty-btn qty-btn--plus" type="button" data-qty-plus aria-label="Augmenter la quantité">+</button>
                  </div>
                  <button class="btn btn-secondary qty-update" type="submit">Mettre à jour</button>
                </form>
              </div>

              <div class="cart-subtotal" data-label="Sous-total">
                <?php echo e($it['line_total_display']); ?>
              </div>

              <div class="cart-remove">
                <form method="post">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="remove_item">
                  <input type="hidden" name="item_key" value="<?php echo e((string) $it['item_key']); ?>">
                  <button class="btn btn-secondary btn-remove" type="submit" aria-label="Supprimer <?php echo e($it['name']); ?>">
                    <i class="fas fa-trash" aria-hidden="true"></i>
                    Supprimer
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>

          <form method="post" class="cart-clear">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="clear_cart">
            <button type="submit" class="btn btn-secondary">Vider le panier</button>
          </form>
        </div>

        <aside class="cart-summary" aria-label="Récapitulatif panier">
          <div class="summary-card">
            <h2>Récapitulatif</h2>
            <div class="summary-row">
              <span>Sous-total</span>
              <strong><?php echo e(function_exists('format_price') ? format_price($total) : (number_format($total, 0, ',', ' ') . ' FCFA')); ?></strong>
            </div>
            <div class="summary-row summary-row--total">
              <span>Total</span>
              <strong><?php echo e(function_exists('format_price') ? format_price($total) : (number_format($total, 0, ',', ' ') . ' FCFA')); ?></strong>
            </div>

            <p class="summary-note">
              <i class="fas fa-truck" aria-hidden="true"></i>
              Paiement à la livraison
            </p>

            <?php if ($has_out_of_stock): ?>
              <p class="summary-warning" role="alert">
                Un ou plusieurs articles sont en rupture. Supprimez-les pour commander.
              </p>
            <?php endif; ?>

            <div class="summary-actions">
              <a class="btn btn-secondary" href="<?php echo e($base_url); ?>pages/catalogue.php">Continuer mes achats</a>
              <a class="btn btn-primary <?php echo $has_out_of_stock ? 'is-disabled' : ''; ?>" href="<?php echo e($base_url); ?>pages/commande.php" <?php echo $has_out_of_stock ? 'aria-disabled="true"' : ''; ?>>
                Commander maintenant
              </a>
            </div>
          </div>
        </aside>
      </div>

      <section class="cart-faq">
        <h2>FAQ</h2>
        <div class="accordion" data-accordion>
          <div class="accordion-item">
            <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="cfaq1">
              Puis-je modifier mon panier ?
              <span class="accordion-icon" aria-hidden="true">+</span>
            </button>
            <div class="accordion-panel" id="cfaq1" hidden>
              <p>Oui. Vous pouvez changer la quantité ou supprimer un article directement ici.</p>
            </div>
          </div>
          <div class="accordion-item">
            <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="cfaq2">
              Le paiement se fait-il à la livraison ?
              <span class="accordion-icon" aria-hidden="true">+</span>
            </button>
            <div class="accordion-panel" id="cfaq2" hidden>
              <p>Oui. Le paiement se fait à la livraison, après réception de votre commande.</p>
            </div>
          </div>
          <div class="accordion-item">
            <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="cfaq3">
              Comment supprimer un article ?
              <span class="accordion-icon" aria-hidden="true">+</span>
            </button>
            <div class="accordion-panel" id="cfaq3" hidden>
              <p>Cliquez sur “Supprimer” sur la ligne de l’article.</p>
            </div>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </div>
</section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
