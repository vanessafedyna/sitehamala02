<?php
declare(strict_types=1);

require_once __DIR__ . '/cart.php';

/**
 * @param array<string,mixed> $post
 * @return array{
 *   fullName:string,
 *   phoneRaw:string,
 *   email:string,
 *   city:string,
 *   district:string,
 *   landmark:string,
 *   couponCode:string,
 *   paymentMethod:string,
 *   cartJson:?string
 * }
 */
function checkout_read_post_payload(array $post): array
{
  $paymentMethod = 'cod';

  return array(
    'fullName' => trim((string) ($post['fullName'] ?? '')),
    'phoneRaw' => trim((string) ($post['phone'] ?? '')),
    'email' => trim((string) ($post['email'] ?? '')),
    'city' => trim((string) ($post['city'] ?? '')),
    'district' => '',
    'landmark' => trim((string) ($post['landmark'] ?? '')),
    'couponCode' => strtoupper(trim((string) ($post['coupon_code'] ?? ''))),
    'paymentMethod' => $paymentMethod,
    'cartJson' => isset($post['cart_json']) ? (string) $post['cart_json'] : null,
  );
}

/**
 * @param array{
 *   fullName:string,
 *   phoneRaw:string,
 *   email:string,
 *   city:string,
 *   district:string,
 *   landmark:string,
 *   couponCode:string,
 *   paymentMethod?:string
 * } $payload
 * @return array<string,string>
 */
function checkout_build_old_input(array $payload): array
{
  return array(
    'fullName' => (string) ($payload['fullName'] ?? ''),
    'phone' => (string) ($payload['phoneRaw'] ?? ''),
    'email' => (string) ($payload['email'] ?? ''),
    'city' => (string) ($payload['city'] ?? ''),
    'landmark' => (string) ($payload['landmark'] ?? ''),
    'coupon_code' => (string) ($payload['couponCode'] ?? ''),
    'payment_method' => (string) ($payload['paymentMethod'] ?? 'cod'),
  );
}

/**
 * @param array{
 *   fullName:string,
 *   phoneRaw:string,
 *   email:string,
 *   city:string,
 *   district:string,
 *   couponCode:string,
 *   paymentMethod?:string
 * } $payload
 * @return string[]
 */
function checkout_validate_surface_payload(array $payload): array
{
  $fullName = (string) ($payload['fullName'] ?? '');
  $phoneRaw = (string) ($payload['phoneRaw'] ?? '');
  $email = (string) ($payload['email'] ?? '');
  $city = (string) ($payload['city'] ?? '');
  $couponCode = (string) ($payload['couponCode'] ?? '');
  $paymentMethod = (string) ($payload['paymentMethod'] ?? 'cod');

  $errors = array();
  if ($fullName === '') $errors[] = 'Le nom complet est obligatoire.';
  if ($phoneRaw === '') $errors[] = 'Le téléphone est obligatoire.';
  if ($phoneRaw !== '' && !checkout_is_valid_phone($phoneRaw)) $errors[] = 'Téléphone invalide.';
  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
  if ($city === '') $errors[] = 'Veuillez sélectionner une ville.';
  if ($couponCode !== '' && !preg_match('/^[A-Z0-9_-]{2,40}$/', $couponCode)) {
    $errors[] = 'Code promo invalide.';
  }
  if ($paymentMethod !== 'cod') {
    $errors[] = 'Mode de paiement invalide.';
  }
  return $errors;
}

/**
 * @param array<mixed,mixed> $cart
 * @return array<string,int>
 */
function checkout_normalize_cart_map(array $cart): array
{
  return cart_normalize_map($cart);
}

function checkout_normalize_phone(string $raw): string
{
  $v = trim($raw);
  $v = preg_replace('/[\\s().-]/', '', $v);
  $v = (string) $v;
  if ($v === '') return '';

  // 00XXXXXXXX -> +XXXXXXXX
  if (strpos($v, '00') === 0) {
    $v = '+' . substr($v, 2);
  }

  if (strpos($v, '+') === 0) {
    $digits = (string) preg_replace('/\\D+/', '', substr($v, 1));
    if (strpos($digits, '223') !== 0) {
      return '';
    }
    $local = substr($digits, 3);
    return preg_match('/^\\d{8}$/', $local) ? ('+223' . $local) : '';
  }

  $digits = (string) preg_replace('/\\D+/', '', $v);
  if ($digits === '') return '';

  if (strpos($digits, '223') === 0) {
    $digits = substr($digits, 3);
  }

  return preg_match('/^\\d{8}$/', $digits) ? ('+223' . $digits) : '';
}

function checkout_is_valid_phone(string $raw): bool
{
  return checkout_normalize_phone($raw) !== '';
}

/**
 * Parse un panier envoyé depuis le front (bridge localStorage).
 * Format accepté: JSON objet { "12": 2 } ou tableau [{product_id, qty}, ...]
 *
 * @return array<string,int>
 */
function checkout_parse_cart_json(?string $raw): array
{
  $raw = trim((string) $raw);
  if ($raw === '') return array();
  if (strlen($raw) > 20000) return array(); // éviter abus

  try {
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
  } catch (Throwable $e) {
    return array();
  }

  $out = array();

  if (!is_array($decoded)) {
    return array();
  }

  $isAssoc = array_keys($decoded) !== range(0, count($decoded) - 1);
  if ($isAssoc) {
    foreach ($decoded as $k => $v) {
      $parsed = cart_parse_key($k);
      $id = (int) ($parsed['product_id'] ?? 0);
      $variantId = isset($parsed['variant_id']) ? (int) ($parsed['variant_id'] ?? 0) : 0;
      $qty = (int) $v;
      if ($variantId <= 0 && $id <= 0) continue;
      if ($qty < 1) $qty = 1;
      $out[cart_item_key($id, $variantId > 0 ? $variantId : null)] = $qty;
    }
    return $out;
  }

  foreach ($decoded as $item) {
    if (!is_array($item)) continue;
    $id = (int) ($item['product_id'] ?? ($item['id'] ?? 0));
    $variantId = (int) ($item['variant_id'] ?? 0);
    $qty = (int) ($item['qty'] ?? ($item['quantity'] ?? 0));
    if ($variantId <= 0 && $id <= 0) continue;
    if ($qty < 1) $qty = 1;
    $out[cart_item_key($id, $variantId > 0 ? $variantId : null)] = $qty;
  }

  return $out;
}
