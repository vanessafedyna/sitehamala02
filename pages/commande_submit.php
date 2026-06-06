<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/OrderModel.php';
require_once __DIR__ . '/../app/models/CustomerModel.php';
require_once __DIR__ . '/../app/helpers/checkout_submit.php';
require_once __DIR__ . '/../includes/NotificationService.php';
require_once __DIR__ . '/../includes/Logger.php';

function checkout_redirect(string $path): void
{
  if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
    header('Location: ' . $path, true, 302);
    exit;
  }
  redirect($path);
}

function checkout_set_errors(array $errors, array $old = array()): void
{
  $_SESSION['checkout_errors'] = array_values(array_filter(array_map('strval', $errors)));
  $_SESSION['checkout_old'] = $old;
}

function checkout_clear_lock(): void
{
  unset($_SESSION['checkout_lock']);
}

function checkout_prepare_success_session(string $orderNumber, string $fingerprint): string
{
  $token = bin2hex(random_bytes(16));
  $_SESSION['order_success_token'] = $token;
  $_SESSION['order_success_order_number'] = $orderNumber;
  $_SESSION['order_success_expires_at'] = time() + 1800;
  $_SESSION['checkout_lock'] = array(
    'fingerprint' => $fingerprint,
    'ts' => time(),
    'order_number' => $orderNumber,
  );

  return $token;
}

/**
 * @param array<int,int> $normalizedCart
 */
function checkout_fingerprint(
  string $fullName,
  string $phoneStored,
  string $city,
  string $couponCode,
  string $paymentMethod,
  array $normalizedCart
): string {
  ksort($normalizedCart);
  $payload = array(
    'full_name' => strtolower(trim($fullName)),
    'phone' => $phoneStored,
    'city' => strtolower(trim($city)),
    'coupon_code' => strtoupper(trim($couponCode)),
    'payment_method' => $paymentMethod,
    'cart' => $normalizedCart,
  );

  return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  checkout_clear_lock();
  checkout_redirect('pages/commande.php');
}

if (!csrf_verify($_POST['_csrf'] ?? null)) {
  checkout_clear_lock();
  checkout_set_errors(array('Session expiree. Veuillez reessayer.'));
  checkout_redirect('pages/commande.php');
}

$payload = checkout_read_post_payload($_POST);
$fullName = $payload['fullName'];
$phoneRaw = $payload['phoneRaw'];
$email = $payload['email'];
$city = $payload['city'];
$landmark = $payload['landmark'];
$couponCode = $payload['couponCode'];
$paymentMethod = $payload['paymentMethod'];

$old = checkout_build_old_input($payload);
$errors = checkout_validate_surface_payload($payload);

$cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : array();
$normalizedCart = checkout_normalize_cart_map($cart);

if (!$normalizedCart) {
  $fromJson = checkout_parse_cart_json($payload['cartJson'] ?? null);
  if ($fromJson) {
    $normalizedCart = $fromJson;
  }
}

if (!$normalizedCart) {
  $errors[] = 'Votre panier est vide.';
}

if ($errors) {
  checkout_clear_lock();
  checkout_set_errors($errors, $old);
  checkout_redirect('pages/commande.php');
}

try {
  $phoneStored = checkout_normalize_phone($phoneRaw);
  $fingerprint = checkout_fingerprint($fullName, $phoneStored, $city, $couponCode, $paymentMethod, $normalizedCart);

  $lock = isset($_SESSION['checkout_lock']) && is_array($_SESSION['checkout_lock']) ? $_SESSION['checkout_lock'] : null;
  if ($lock) {
    $lockedFp = (string) ($lock['fingerprint'] ?? '');
    $lockedTs = (int) ($lock['ts'] ?? 0);
    $isSame = ($lockedFp !== '' && hash_equals($lockedFp, $fingerprint));
    $isRecent = (time() - $lockedTs) < 30;

    if ($isSame && $isRecent) {
      $lockedOrderNumber = trim((string) ($lock['order_number'] ?? ''));
      if ($lockedOrderNumber !== '') {
        $lockedToken = trim((string) ($_SESSION['order_success_token'] ?? ''));
        $successUrl = 'pages/order_success.php?order_number=' . urlencode($lockedOrderNumber);
        if ($lockedToken !== '') {
          $successUrl .= '&t=' . urlencode($lockedToken);
        }
        checkout_redirect($successUrl);
      }
      checkout_clear_lock();
      checkout_set_errors(array('Commande deja en cours de traitement. Veuillez patienter quelques secondes.'), $old);
      checkout_redirect('pages/commande.php');
    }
  }

  $_SESSION['checkout_lock'] = array(
    'fingerprint' => $fingerprint,
    'ts' => time(),
  );

  $user = current_user();
  $customerId = $user ? (int) ($user['id'] ?? 0) : 0;
  if ($customerId <= 0) {
    $customerId = null;
  }

  $pdo = db();

  $customerProfileId = null;
  $cm = new CustomerModel($pdo);
  if ($cm->exists()) {
    $customerRes = $cm->findOrCreateByPhone(array(
      'full_name' => $fullName,
      'phone' => $phoneStored,
      'email' => ($email !== '' ? $email : ($user ? (string) ($user['email'] ?? '') : '')),
      'city' => $city,
      'district' => '',
      'address_note' => $landmark,
    ));
    $customerProfileId = (int) ($customerRes['customer_id'] ?? 0);
    $isBlacklisted = (bool) ($customerRes['is_blacklisted'] ?? false);
    if ($isBlacklisted) {
      throw new RuntimeException('Ce numero est bloque. Contactez le support.');
    }
  }

  $model = new OrderModel($pdo);
  $orderRes = $model->createFromCart(array(
    'customer_name' => $fullName,
    'customer_phone' => $phoneStored,
    'customer_email' => $email,
    'city' => $city,
    'district' => '',
    'landmark' => $landmark,
    'customer_id' => $customerId,
    'customer_profile_id' => $customerProfileId,
    'coupon_code' => $couponCode,
    'payment_method' => $paymentMethod,
  ), $normalizedCart);

  $orderNumber = (string) ($orderRes['order_number'] ?? '');

  try {
    $data = $model->findWithItems((int) ($orderRes['id'] ?? 0));
    if ($data && isset($data['order'], $data['items'])) {
      NotificationService::notifyAdminNewOrder((array) $data['order'], (array) $data['items']);
      NotificationService::notifyClientOrderCreated((array) $data['order']);
    }
  } catch (Throwable $e) {
    // best-effort
  }

  $_SESSION['cart'] = array();
  unset($_SESSION['checkout_errors'], $_SESSION['checkout_old']);

  $token = checkout_prepare_success_session($orderNumber, $fingerprint);
  $_SESSION['checkout_lock']['payment_method'] = 'cod';
  checkout_redirect('pages/order_success.php?order_number=' . urlencode($orderNumber) . '&t=' . urlencode($token));
  exit;
} catch (Throwable $e) {
  checkout_clear_lock();
  $raw = trim((string) $e->getMessage());
  if (class_exists('Logger')) {
    Logger::error('checkout_failed', array(
      'error' => $raw,
      'customer_phone' => checkout_normalize_phone($phoneRaw),
      'customer_phone_raw' => substr((string) $phoneRaw, 0, 40),
    ));
  } else {
    error_log('[checkout] ' . $raw);
  }
  $msg = $raw;
  $rawLower = strtolower($raw);
  if (
    $raw === 'Erreur de connexion'
    || strpos($rawLower, 'server has gone away') !== false
    || (strpos($rawLower, 'sqlstate[hy000]') !== false && strpos($rawLower, ' 2006 ') !== false)
    || strpos($rawLower, ' 10061') !== false
  ) {
    $msg = 'Base de donnees indisponible (MySQL). Redemarrez MySQL dans XAMPP puis reessayez.';
  }
  if (
    $msg === ''
    || str_starts_with($msg, 'SQLSTATE[')
    || strpos($rawLower, 'sqlstate[') !== false
    || strpos($rawLower, 'integrity constraint') !== false
    || strpos($rawLower, 'pdoexception') !== false
    || strpos($rawLower, 'duplicate entry') !== false
    || strpos($rawLower, 'syntax error') !== false
  ) {
    $msg = 'Impossible de creer la commande. Veuillez reessayer.';
  }
  checkout_set_errors(array($msg), $old);
  checkout_redirect('pages/commande.php');
}
