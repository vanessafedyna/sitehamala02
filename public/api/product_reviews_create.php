<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

api_require_method('POST');
api_rate_limit('product_reviews_create', 20, 60);
api_require_csrf();

/**
 * @return array<string,mixed>
 */
function product_reviews_read_input(): array
{
  $ct = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
  if (strpos($ct, 'application/json') !== false) {
    return json_read_body();
  }

  return array(
    'product_id' => $_POST['product_id'] ?? '',
    'customer_name' => $_POST['customer_name'] ?? '',
    'customer_city' => $_POST['customer_city'] ?? '',
    'rating' => $_POST['rating'] ?? '',
    'comment' => $_POST['comment'] ?? '',
  );
}

$body = product_reviews_read_input();

$productId = filter_var($body['product_id'] ?? null, FILTER_VALIDATE_INT, array(
  'options' => array('min_range' => 1),
));
$customerName = trim((string) ($body['customer_name'] ?? ''));
$customerCity = trim((string) ($body['customer_city'] ?? ''));
$rating = filter_var($body['rating'] ?? null, FILTER_VALIDATE_INT, array(
  'options' => array('min_range' => 1, 'max_range' => 5),
));
$comment = trim((string) ($body['comment'] ?? ''));

if ($productId === false) {
  json_response(400, array('ok' => false, 'success' => false, 'message' => 'Produit invalide.'));
}
if ($customerName === '' || strlen($customerName) > 100) {
  json_response(400, array('ok' => false, 'success' => false, 'message' => 'Nom invalide.'));
}
if ($customerCity !== '' && strlen($customerCity) > 100) {
  json_response(400, array('ok' => false, 'success' => false, 'message' => 'Ville invalide.'));
}
if ($rating === false) {
  json_response(400, array('ok' => false, 'success' => false, 'message' => 'Note invalide.'));
}
if ($comment === '' || strlen($comment) > 2000) {
  json_response(400, array('ok' => false, 'success' => false, 'message' => 'Commentaire invalide.'));
}

try {
  $pdo = api_pdo();

  $check = $pdo->prepare('SELECT id FROM products WHERE id = :id LIMIT 1');
  $check->execute(array(':id' => (int) $productId));
  if (!(int) $check->fetchColumn()) {
    json_response(404, array('ok' => false, 'success' => false, 'message' => 'Produit introuvable.'));
  }

  $stmt = $pdo->prepare(
    'INSERT INTO product_reviews (product_id, customer_name, customer_city, rating, comment, is_approved)
     VALUES (:product_id, :customer_name, :customer_city, :rating, :comment, 0)'
  );
  $stmt->execute(array(
    ':product_id' => (int) $productId,
    ':customer_name' => $customerName,
    ':customer_city' => ($customerCity !== '' ? $customerCity : null),
    ':rating' => (int) $rating,
    ':comment' => $comment,
  ));

  json_response(200, array(
    'ok' => true,
    'success' => true,
    'message' => 'Merci, votre avis a été envoyé pour validation.',
  ));
} catch (Throwable $e) {
  error_log('[api/product_reviews_create] ' . $e->getMessage());
  json_response(500, array('ok' => false, 'success' => false, 'message' => 'Erreur serveur.'));
}
