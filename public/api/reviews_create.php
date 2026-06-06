<?php
declare(strict_types=1);

/* Point d'entrée pour la création d'avis côté public. */

require_once __DIR__ . '/_common.php';

api_require_method('POST');
api_rate_limit('reviews_create', 20, 60);
api_require_csrf();

/**
 * @return array<string,mixed>
 */
function reviews_read_input(): array
{
  /* Accepte les envois JSON et formulaire. */
  $ct = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
  if (strpos($ct, 'application/json') !== false) {
    return json_read_body();
  }

  // form-data / x-www-form-urlencoded
  return array(
    'name' => $_POST['name'] ?? '',
    'city' => $_POST['city'] ?? '',
    'rating' => $_POST['rating'] ?? '',
    'message' => $_POST['message'] ?? '',
  );
}

$body = reviews_read_input();
$name = trim((string) ($body['name'] ?? ''));
$city = trim((string) ($body['city'] ?? ''));
$ratingRaw = $body['rating'] ?? null;
$message = trim((string) ($body['message'] ?? ''));

$rating = filter_var($ratingRaw, FILTER_VALIDATE_INT, array(
  'options' => array('min_range' => 1, 'max_range' => 5),
));

if ($name === '' || strlen($name) > 100) {
  json_response(400, array('ok' => false, 'message' => 'Nom invalide.'));
}
if ($city === '' || strlen($city) > 100) {
  json_response(400, array('ok' => false, 'message' => 'Ville invalide.'));
}
if ($rating === false) {
  json_response(400, array('ok' => false, 'message' => 'Note invalide.'));
}
if ($message === '' || strlen($message) < 10 || strlen($message) > 1000) {
  json_response(400, array('ok' => false, 'message' => 'Message invalide (10 à 1000 caractères).'));
}

try {
  $pdo = api_pdo();
  $sql = 'INSERT INTO reviews (name, city, rating, message, is_approved) VALUES (:name, :city, :rating, :message, 0)';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(array(
    'name' => $name,
    'city' => $city,
    'rating' => (int) $rating,
    'message' => $message,
  ));

  json_response(200, array('ok' => true));
} catch (Throwable $e) {
  error_log('[api/reviews_create] ' . $e->getMessage());
  json_response(500, array('ok' => false, 'message' => 'Erreur serveur.'));
}

