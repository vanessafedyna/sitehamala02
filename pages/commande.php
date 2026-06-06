<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/cart.php';
require_once __DIR__ . '/../app/models/ProductModel.php';
require_once __DIR__ . '/../app/models/OrderModel.php';
require_once __DIR__ . '/../app/models/CustomerModel.php';
require_once __DIR__ . '/../app/services/ProductImageService.php';
require_once __DIR__ . '/../app/services/ProductVariantService.php';

$page_title = 'Finaliser ma commande';
$page_css = 'pages/commande.css';
$page_js = 'pages/commande.js';

$checkout_errors = isset($_SESSION['checkout_errors']) && is_array($_SESSION['checkout_errors']) ? $_SESSION['checkout_errors'] : array();
$checkout_old = isset($_SESSION['checkout_old']) && is_array($_SESSION['checkout_old']) ? $_SESSION['checkout_old'] : array();
unset($_SESSION['checkout_errors'], $_SESSION['checkout_old']);

$coupon_old = strtoupper(trim((string) ($checkout_old['coupon_code'] ?? '')));
$email_old = trim((string) ($checkout_old['email'] ?? ''));
$checkout_prefill = array(
  'fullName' => '',
  'phone' => '',
  'email' => '',
  'city' => '',
  'landmark' => '',
);

function checkout_prefill_merge(array $target, array $source): array
{
  foreach ($source as $key => $value) {
    if (!array_key_exists($key, $target)) continue;
    $v = trim((string) $value);
    if ($v === '') continue;
    $target[$key] = $v;
  }
  return $target;
}

function checkout_table_columns(PDO $pdo, string $table): array
{
  if (function_exists('db_table_columns')) {
    return db_table_columns($pdo, $table);
  }
  try {
    $rows = $pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC) ?: array();
    $cols = array();
    foreach ($rows as $r) {
      if (!empty($r['Field'])) $cols[] = (string) $r['Field'];
    }
    return $cols;
  } catch (Throwable $e) {
    return array();
  }
}

$prefillFromOld = array(
  'fullName' => trim((string) ($checkout_old['fullName'] ?? '')),
  'phone' => trim((string) ($checkout_old['phone'] ?? '')),
  'email' => $email_old,
  'city' => trim((string) ($checkout_old['city'] ?? '')),
  'landmark' => trim((string) ($checkout_old['landmark'] ?? '')),
);

$prefillFromUser = array();
$prefillFromHistory = array();

try {
  $user = current_user();
  if ($user) {
    $pdo = db();
    $userId = (int) ($user['id'] ?? 0);

    $sessionFirstName = trim((string) ($user['name'] ?? ''));
    $sessionLastName = trim((string) ($user['last_name'] ?? ''));
    $sessionFullName = trim($sessionFirstName . ' ' . $sessionLastName);
    if ($sessionFullName === '') {
      $sessionFullName = $sessionFirstName;
    }

    $prefillFromUser = array(
      'fullName' => $sessionFullName,
      'email' => trim((string) ($user['email'] ?? '')),
    );

    if ($userId > 0) {
      $userCols = checkout_table_columns($pdo, 'users');
      if ($userCols) {
        $select = array('id');
        if (in_array('name', $userCols, true)) $select[] = 'name';
        if (in_array('last_name', $userCols, true)) $select[] = 'last_name';
        if (in_array('email', $userCols, true)) $select[] = 'email';
        if (in_array('phone', $userCols, true)) $select[] = 'phone';

        $stmt = $pdo->prepare('SELECT ' . implode(', ', $select) . ' FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $userId));
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($userRow) {
          $dbFirstName = trim((string) ($userRow['name'] ?? ''));
          $dbLastName = trim((string) ($userRow['last_name'] ?? ''));
          $dbFullName = trim($dbFirstName . ' ' . $dbLastName);
          if ($dbFullName === '') {
            $dbFullName = $dbFirstName;
          }

          $prefillFromUser = checkout_prefill_merge($prefillFromUser, array(
            'fullName' => $dbFullName,
            'email' => trim((string) ($userRow['email'] ?? '')),
            'phone' => trim((string) ($userRow['phone'] ?? '')),
          ));
        }
      }
    }

    $latestOrder = null;
    $orderModel = new OrderModel($pdo);
    if ($userId > 0) {
      $latestOrder = $orderModel->findLatestForUser($userId);
    }
    if (!$latestOrder && !empty($prefillFromUser['phone'])) {
      $latestOrder = $orderModel->findLatestByPhone((string) $prefillFromUser['phone']);
    }

    if ($latestOrder) {
      $prefillFromHistory = checkout_prefill_merge($prefillFromHistory, array(
        'fullName' => trim((string) ($latestOrder['customer_name'] ?? '')),
        'phone' => trim((string) ($latestOrder['customer_phone'] ?? '')),
        'email' => trim((string) ($latestOrder['customer_email'] ?? '')),
        'city' => trim((string) ($latestOrder['city'] ?? '')),
        'landmark' => trim((string) ($latestOrder['landmark'] ?? '')),
      ));
    }

    $customerModel = new CustomerModel($pdo);
    if ($customerModel->exists()) {
      $customer = null;
      $customerProfileId = $latestOrder ? (int) ($latestOrder['customer_profile_id'] ?? 0) : 0;
      if ($customerProfileId > 0) {
        $customer = $customerModel->findById($customerProfileId);
      }
      if (!$customer && !empty($prefillFromUser['phone'])) {
        $customer = $customerModel->findByPhone((string) $prefillFromUser['phone']);
      }

      if ($customer) {
        $prefillFromHistory = checkout_prefill_merge($prefillFromHistory, array(
          'fullName' => trim((string) ($customer['full_name'] ?? '')),
          'phone' => trim((string) ($customer['phone'] ?? '')),
          'email' => trim((string) ($customer['email'] ?? '')),
          'city' => trim((string) ($customer['city'] ?? '')),
          'landmark' => trim((string) ($customer['address_note'] ?? '')),
        ));
      }
    }
  }
} catch (Throwable $e) {
  error_log('[commande_prefill] ' . $e->getMessage());
}

$checkout_prefill = checkout_prefill_merge($checkout_prefill, $prefillFromHistory);
$checkout_prefill = checkout_prefill_merge($checkout_prefill, $prefillFromUser);
$checkout_prefill = checkout_prefill_merge($checkout_prefill, $prefillFromOld);

$cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : array();
$normalizedCart = cart_normalize_map($cart);
$_SESSION['cart'] = $normalizedCart;
$cartLines = cart_normalize_lines($normalizedCart);

function format_fcfa_html(int $amount): string
{
  $s = number_format($amount, 0, ',', ' ');
  $s = str_replace(' ', '&nbsp;', $s);
  return $s . '&nbsp;FCFA';
}

$cart_items = array();
$subtotal = 0;
$cart_notice = '';

if ($cartLines) {
  try {
    $pdo = db();
    $model = new ProductModel($pdo);
    $variantService = new ProductVariantService($pdo);

    $productIds = array();
    $variantsById = array();
    foreach ($cartLines as $line) {
      $productId = (int) ($line['product_id'] ?? 0);
      $variantId = (int) ($line['variant_id'] ?? 0);
      if ($variantId > 0) {
        $variant = $variantService->findById($variantId);
        if ($variant) {
          $variantsById[$variantId] = $variant;
          $productId = (int) ($variant['product_id'] ?? 0);
        }
      }
      if ($productId > 0) {
        $productIds[$productId] = true;
      }
    }

    $rows = $productIds ? $model->findByIds(array_keys($productIds)) : array();
    $productsById = array();
    foreach ($rows as $r) {
      $id = (int) ($r['id'] ?? 0);
      if ($id > 0) {
        $productsById[$id] = $r;
      }
    }

    $changed = false;
    foreach ($cartLines as $line) {
      $itemKey = (string) ($line['key'] ?? '');
      $productId = (int) ($line['product_id'] ?? 0);
      $variantId = (int) ($line['variant_id'] ?? 0);
      if ($variantId > 0) {
        $variant = $variantsById[$variantId] ?? null;
        if (!$variant) {
          unset($_SESSION['cart'][$itemKey]);
          $changed = true;
          continue;
        }
        $productId = (int) ($variant['product_id'] ?? 0);
      }
      if ($productId <= 0 || !isset($productsById[$productId])) {
        unset($_SESSION['cart'][$itemKey]);
        $changed = true;
      }
    }

    if ($changed) {
      $normalizedCart = cart_normalize_map($_SESSION['cart']);
      $_SESSION['cart'] = $normalizedCart;
      $cartLines = cart_normalize_lines($normalizedCart);
      $cart_notice = 'Certains articles ont été retirés (introuvables).';
    }

    foreach ($cartLines as $line) {
      $itemKey = (string) ($line['key'] ?? '');
      $productId = (int) ($line['product_id'] ?? 0);
      $variantId = (int) ($line['variant_id'] ?? 0);
      $qty = (int) ($line['qty'] ?? 0);
      $variant = null;

      if ($variantId > 0) {
        $variant = $variantsById[$variantId] ?? null;
        if (!$variant) continue;
        $productId = (int) ($variant['product_id'] ?? 0);
      }
      if (!isset($productsById[$productId])) continue;

      $p = $productsById[$productId];
      $name = (string) ($p['name'] ?? 'Produit');
      $sku = (string) ($p['sku'] ?? '');
      $price = (int) ($p['price'] ?? 0);
      $lineTotal = $price * $qty;
      $subtotal += $lineTotal;

      $img = (string) ($p['image_path'] ?? ($p['image_main'] ?? ($p['image'] ?? '')));
      $img = trim($img);
      $imgUrl = $img !== '' ? ProductImageService::toUrl($img) : base_url('assets/images/placeholders/product-placeholder.svg');

      $cart_items[] = array(
        'item_key' => $itemKey,
        'id' => $productId,
        'name' => $name,
        'sku' => $sku,
        'size' => $variant ? (string) ($variant['size'] ?? '') : '',
        'color' => $variant ? (string) ($variant['color'] ?? '') : '',
        'qty' => $qty,
        'unit_price' => $price,
        'line_total' => $lineTotal,
        'img_url' => $imgUrl,
      );
    }
  } catch (Throwable $e) {
    error_log('[commande.php] ' . $e->getMessage());
    $cart_notice = 'Impossible de charger votre panier pour le moment.';
    $cart_items = array();
    $subtotal = 0;
  }
}

$total = $subtotal;
$cart_is_empty = count($cart_items) === 0;

include __DIR__ . '/../includes/header.php';
?>

<section class="page-head">
  <div class="container">
    <h1>Finaliser ma commande</h1>
    <p class="subtitle">Commande simple et sécurisée</p>
  </div>
</section>

<main id="main" class="checkout" tabindex="-1">
  <div class="container">
    <?php if ($checkout_errors): ?>
      <div class="feature-card" role="alert">
        <p><strong>Merci de corriger :</strong></p>
        <ul>
          <?php foreach ($checkout_errors as $err): ?>
            <li><?php echo e($err); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="checkout-grid">
      <section class="card checkout-summary-card" aria-labelledby="cartTitle">
        <div class="card-head">
          <h2 id="cartTitle">Résumé du panier</h2>
          <a class="link" href="<?php echo e($base_url); ?>pages/panier.php">Modifier mon panier</a>
        </div>

        <?php if ($cart_notice !== ''): ?>
          <div class="feature-card" role="status" aria-live="polite">
            <p><strong><?php echo e($cart_notice); ?></strong></p>
          </div>
        <?php endif; ?>

        <?php if ($cart_is_empty): ?>
          <div class="feature-card" role="alert">
            <p><strong>Votre panier est vide.</strong></p>
            <p><a class="link" href="<?php echo e($base_url); ?>pages/catalogue.php">Aller au catalogue</a></p>
          </div>
        <?php else: ?>
          <ul class="cart-list" aria-label="Articles du panier (résumé)">
            <?php foreach ($cart_items as $it): ?>
              <li class="cart-item">
                <div class="cart-media" aria-hidden="true">
                  <img src="<?php echo e((string) $it['img_url']); ?>" alt="" loading="lazy" decoding="async">
                </div>
                <div class="cart-info">
                  <div class="cart-name"><?php echo e((string) $it['name']); ?></div>
                  <div class="cart-meta">
                    <?php if ((string) $it['sku'] !== ''): ?>SKU: <?php echo e((string) $it['sku']); ?> &bull; <?php endif; ?>
                    Qté: <?php echo e((string) (int) $it['qty']); ?>
                    <?php if ((string) ($it['size'] ?? '') !== ''): ?> &bull; Taille: <?php echo e((string) $it['size']); ?><?php endif; ?>
                    <?php if ((string) ($it['color'] ?? '') !== ''): ?> &bull; Couleur: <?php echo e((string) $it['color']); ?><?php endif; ?>
                  </div>
                </div>
                <div class="cart-price"><?php echo format_fcfa_html((int) $it['line_total']); ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <div class="divider" role="presentation"></div>

        <div class="totals">
          <div class="totals-row">
            <span>Sous-total</span>
            <strong><?php echo format_fcfa_html((int) $subtotal); ?></strong>
          </div>
          <div class="totals-row totals-row--grand">
            <span>Total</span>
            <strong><?php echo format_fcfa_html((int) $total); ?></strong>
          </div>
          <div class="pill" aria-label="Mode de paiement au choix">Mode de paiement au choix</div>
        </div>
        <p class="summary-help">Vérification rapide de votre commande avant validation.</p>
        <div class="summary-reassurance" aria-label="Informations de confiance">
          <span>Confirmation après validation</span>
          <span>Support client disponible</span>
        </div>
      </section>

      <section class="card checkout-form-card" aria-labelledby="shipTitle">
        <div class="card-head">
          <h2 id="shipTitle">Informations de livraison</h2>
          <span class="hint">Champs requis *</span>
        </div>

        <form
          id="checkoutForm"
          class="form"
          method="post"
          action="<?php echo e($base_url); ?>pages/commande_submit.php"
          data-cart-empty="<?php echo $cart_is_empty ? '1' : '0'; ?>"
          novalidate
        >
          <?php echo csrf_field(); ?>
          <input type="hidden" name="cart_json" id="cartJson" value="">
          <section class="form-block" aria-labelledby="checkoutContactTitle">
            <div class="form-block-head">
              <p class="form-section-title">Vos coordonnees</p>
              <h3 id="checkoutContactTitle">Vos coordonnées</h3>
              <p class="form-section-intro">Indiquez surtout votre téléphone pour que notre équipe puisse confirmer rapidement votre commande.</p>
            </div>
          <?php if ($cart_is_empty): ?>
            <div class="feature-card" role="alert">
              <p><strong>Ajoutez des produits au panier pour continuer.</strong></p>
              <p>
                <a class="btn btn-secondary" href="<?php echo e($base_url); ?>pages/catalogue.php">Aller au catalogue</a>
                <a class="btn btn-outline" href="<?php echo e($base_url); ?>pages/panier.php">Voir mon panier</a>
              </p>
            </div>
          <?php endif; ?>
          <div class="field">
            <label for="fullName">Nom complet *</label>
            <input
              id="fullName"
              name="fullName"
              type="text"
              autocomplete="name"
              required
              placeholder="Ex: Malick Sylla"
              aria-describedby="fullNameHelp fullNameError"
              value="<?php echo e((string) ($checkout_prefill['fullName'] ?? '')); ?>"
            />

            <div id="fullNameError" class="error" role="alert" aria-live="polite"></div>
          </div>

          <div class="field field--priority">
            <label for="phone">Téléphone *</label>
            <input
              id="phone"
              name="phone"
              type="tel"
              inputmode="tel"
              autocomplete="tel"
              required
              placeholder="Ex: 84449944"
              aria-describedby="phoneHelp phoneError"
              value="<?php echo e((string) ($checkout_prefill['phone'] ?? '')); ?>"
            />

        
          </section>

          <section class="form-block" aria-labelledby="checkoutAddressTitle">
            <div class="form-block-head">
              <p class="form-section-title">Adresse complete</p>
              <h3 id="checkoutAddressTitle">Adresse complete</h3>
              <p class="form-section-intro">Plus vos indications sont précises, plus la livraison est simple et rapide.</p>
            </div>
            
                      <div class="field">
            <label for="city">Pays *</label>
            <select id="city" name="city" required aria-describedby="cityHelp cityError">
              <option value="">Choisir un pays</option>
              <?php $oldCity = (string) ($checkout_prefill['city'] ?? ''); ?>
              <option value="Mali" <?php echo $oldCity === 'Mali' ? 'selected' : ''; ?>>Mali</option>
              <option value="Ivoire" <?php echo $oldCity === 'Ivoire' ? 'selected' : ''; ?>>Ivoire</option>
              <option value="Burkina" <?php echo $oldCity === 'Burkina' ? 'selected' : ''; ?>>Burkina</option>
              <option value="France" <?php echo $oldCity === 'France' ? 'selected' : ''; ?>>France</option>
              <option value="Canada" <?php echo $oldCity === 'Canada' ? 'selected' : ''; ?>>Canada</option>
              <option value="Autres" <?php echo $oldCity === 'Autres' ? 'selected' : ''; ?>>Autres</option>

            </select>
            <div id="cityHelp" class="help">Sélectionnez votre pays de livraison.</div>
            <div id="cityError" class="error" role="alert" aria-live="polite"></div>
          </div>
          </section>
            
          <div class="field field--highlight">
            <label for="landmark">Quartier</label>
            <textarea
              id="landmark"
              name="landmark"
              rows="3"
              placeholder="Ex: Bolibana, Kalaban, ACI 2000…"
              aria-describedby="landmarkHelp"
            ><?php echo e((string) ($checkout_prefill['landmark'] ?? '')); ?></textarea>

          </div>



          <section class="form-block" aria-labelledby="checkoutConfirmTitle">
            <div class="form-block-head">
              <p class="form-section-title">Confirmation commande</p>
              <h3 id="checkoutConfirmTitle">Confirmation commande</h3>
              <p class="form-section-intro">Vérifiez votre total, choisissez le paiement à la livraison et validez votre commande.</p>
            </div>

            <details class="promo-disclosure" <?php echo $coupon_old !== '' ? 'open' : ''; ?>>
              <summary>J’ai un code promo</summary>
              <div class="promo-disclosure-body">
                <div class="field field--promo">
                  <label for="couponCode">Code promo</label>
                  <input
                    id="couponCode"
                    name="coupon_code"
                    type="text"
                    inputmode="text"
                    autocomplete="off"
                    maxlength="40"
                    placeholder="Ex: SAVE10"
                    value="<?php echo e($coupon_old); ?>"
                  />
                  <div class="help">Optionnel. Le code sera vérifié lors de la validation.</div>
                </div>
              </div>
            </details>

            <p class="form-section-title">Mode de paiement</p>
          <div class="field">
            <fieldset class="payment-options" aria-describedby="paymentMethodHelp">
              <legend>Choisissez votre mode de paiement</legend>
              <label class="payment-option" for="paymentMethodCod">
                <input
                  id="paymentMethodCod"
                  name="payment_method"
                  type="radio"
                  value="cod"
                  checked
                />
                <span>
                  <strong>Paiement à la livraison</strong>
                  <small>Réglez votre commande à la réception.</small>
                </span>
              </label>
            </fieldset>
            <div id="paymentMethodHelp" class="help">Le paiement se fait actuellement à la livraison.</div>
          </div>

          <div class="card card--nested" aria-label="Total et paiement">
            <div class="totals totals--compact">
              <div class="totals-row">
                <span>Sous-total</span>
                <strong><?php echo format_fcfa_html((int) $subtotal); ?></strong>
              </div>
              <div class="totals-row totals-row--grand">
                <span>Total</span>
                <strong><?php echo format_fcfa_html((int) $total); ?></strong>
              </div>
              <div class="pill pill--strong">Paiement à la livraison</div>
            </div>
          </div>

          <div class="checkout-reassurance" aria-label="Informations de confiance avant validation">
            <span>Paiement à réception</span>
            <span>Confirmation par téléphone</span>
            <span>Assistance disponible</span>
          </div>

          <div class="cta">
            <button
              id="submitBtn"
              class="btn btn-primary"
              type="submit"
              <?php echo $cart_is_empty ? 'disabled aria-disabled="true"' : ''; ?>
            >
              Valider la commande
            </button>
            <p class="cta-microcopy">Notre équipe confirme votre commande après validation.</p>
            <p class="legal">En cliquant, vous confirmez votre commande.</p>
            <p class="cta-help">Votre mode de paiement sera pris en compte avec la commande.</p>
          </div>
          </section>

          <div id="toast" class="toast" role="status" aria-live="polite" aria-atomic="true" hidden></div>
        </form>
      </section>
    </div>

    <section class="card faq" aria-labelledby="faqTitle">
      <h2 id="faqTitle">FAQ</h2>
      <div class="accordion" data-accordion>
        <div class="accordion-item">
          <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="faq1">
            Puis-je payer à la livraison ?
            <span class="accordion-icon" aria-hidden="true">+</span>
          </button>
          <div class="accordion-panel" id="faq1" aria-hidden="true" hidden>
            <p>Oui. Le paiement se fait à la livraison, après réception de votre commande.</p>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="faq2">
            Comment se passe la livraison ?
            <span class="accordion-icon" aria-hidden="true">+</span>
          </button>
          <div class="accordion-panel" id="faq2" aria-hidden="true" hidden>
            <p>Un livreur vous contacte par téléphone. Les indications de ville et le point de repère recommandé facilitent la remise.</p>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="faq3">
            Puis-je modifier ma commande après validation ?
            <span class="accordion-icon" aria-hidden="true">+</span>
          </button>
          <div class="accordion-panel" id="faq3" aria-hidden="true" hidden>
            <p>Contactez-nous rapidement. Tant que la commande n’est pas expédiée, une modification est souvent possible.</p>
          </div>
        </div>
      </div>
    </section>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
