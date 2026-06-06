<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/cart.php';
require_once __DIR__ . '/../app/models/ProductModel.php';
require_once __DIR__ . '/../app/services/ProductVariantService.php';
require_once __DIR__ . '/../includes/Logger.php';

function cart_add_safe_return_to(?string $value): string
{
  $v = trim((string) $value);
  if ($v === '') return 'pages/panier.php';

  if (strpos($v, '://') !== false) {
    return 'pages/panier.php';
  }
  if (strpos($v, '//') === 0) {
    return 'pages/panier.php';
  }
  if (strpos($v, '..') !== false) {
    return 'pages/panier.php';
  }

  // Normaliser: pas de slash au debut
  $v = ltrim($v, '/');

  // Autoriser uniquement pages/* ou index.php
  if (!(str_starts_with($v, 'pages/') || $v === 'index.php')) {
    return 'pages/panier.php';
  }

  return $v;
}

function cart_add_set_flash_error(string $message): void
{
  if (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    foreach ($_SESSION['flash'] as $flashRow) {
      $type = (string) ($flashRow['type'] ?? '');
      $text = (string) ($flashRow['message'] ?? '');
      if ($type === 'error' && $text === $message) {
        return;
      }
    }
  }

  if (function_exists('set_flash')) {
    try {
      set_flash('error', $message);
      return;
    } catch (Throwable $e) {
      // fallback
    }
  }
  if (function_exists('flash')) {
    try {
      flash('error', $message);
      return;
    } catch (Throwable $e) {
      // fallback
    }
  }
  if (function_exists('add_flash')) {
    try {
      add_flash('error', $message);
      return;
    } catch (Throwable $e) {
      // fallback
    }
  }

  if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
    $_SESSION['flash'] = array();
  }
  $_SESSION['flash'][] = array(
    'type' => 'error',
    'message' => $message,
  );
}

function cart_add_user_message(Throwable $e): string
{
  $raw = trim((string) $e->getMessage());
  if ($raw === 'Choix de taille obligatoire.') {
    return 'Veuillez choisir une taille sur la fiche produit avant d ajouter cet article au panier.';
  }

  return "Impossible d'ajouter au panier. Reessayez.";
}

function cart_add_log_failed(int $productId, int $qty, string $error): void
{
  $ctx = array(
    'product_id' => $productId,
    'qty' => $qty,
    'error' => $error,
  );

  if (class_exists('Logger')) {
    Logger::error('cart_add_failed', $ctx);
    return;
  }
  error_log('[cart_add_failed] ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  redirect('pages/catalogue.php');
}

if (!csrf_verify($_POST['_csrf'] ?? null)) {
  redirect('pages/catalogue.php');
}

$productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$variantId = isset($_POST['variant_id']) ? (int) $_POST['variant_id'] : 0;
$qty = isset($_POST['qty']) ? max(1, (int) $_POST['qty']) : 1;
$returnTo = cart_add_safe_return_to($_POST['return_to'] ?? null);

if ($productId <= 0) {
  cart_add_set_flash_error("Impossible d'ajouter au panier. Réessayez.");
  redirect($returnTo);
}

try {
  $model = new ProductModel(db());
  $product = $model->getById($productId);
  if (!$product) {
    throw new RuntimeException('Produit introuvable.');
  }

  $isActive = (int) ($product['is_active'] ?? 1);
  $stock = (int) ($product['stock'] ?? 0);
  if ($isActive !== 1 || $stock <= 0) {
    throw new RuntimeException('Produit indisponible ou stock insuffisant.');
  }

  $variantService = new ProductVariantService(db());
  $lineStock = $stock;
  $lineKey = cart_item_key($productId, null);

  if ($variantId > 0) {
    $variant = $variantService->findForProduct($productId, $variantId, true);
    if (!$variant) {
      throw new RuntimeException('Variante introuvable.');
    }
    if (!$variantService->isPurchasableVariant($variant)) {
      throw new RuntimeException('Variante invalide.');
    }
    $lineStock = (int) ($variant['stock'] ?? 0);
    $lineKey = cart_item_key($productId, $variantId);
  } elseif ($variantService->hasAnyVariants($productId)) {
    throw new RuntimeException('Choix de taille obligatoire.');
  }

  if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
  }
  $_SESSION['cart'] = cart_normalize_map($_SESSION['cart']);

  $current = isset($_SESSION['cart'][$lineKey]) ? (int) $_SESSION['cart'][$lineKey] : 0;
  $next = min($current + $qty, $lineStock);
  $_SESSION['cart'][$lineKey] = $next;
} catch (Throwable $e) {
  cart_add_log_failed($productId, $qty, (string) $e->getMessage());
  cart_add_set_flash_error("Impossible d'ajouter au panier. Réessayez.");
}

redirect($returnTo);
